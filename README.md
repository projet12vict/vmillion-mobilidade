# V-MILLION

Mobilidade inteligente para Cabo Verde: liga passageiros a condutores de Hiace
e táxi, com posição em tempo real, reserva com escolha de assento, chamada de
voz entre condutor e passageiro, e botão SOS.

Aplicação **Laravel 13** sobre **PHP 8.5** e **MySQL**, com um processo
**Node/Socket.io** à parte para os eventos em tempo real.

## Arranque rápido

```bash
composer install
cp .env.example .env && php artisan key:generate

# Base de dados (o schema depende de ENUM, CHECK e ON UPDATE CURRENT_TIMESTAMP)
mysql -uroot -e "CREATE DATABASE kabugo_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed

php artisan serve
node realtime/server.js   # noutro terminal
```

O seed cria o Super Admin `admin@vmillion.cv` com a senha temporária
`VMILLION#2026`, que tem de ser trocada no primeiro login.

## Estrutura

| Pasta | Conteúdo |
|---|---|
| `app/Models` | Um model por tabela, com os nomes de domínio em português (`Utilizador`, `Veiculo`, `Reserva`, …). |
| `app/Services` | Regras de negócio partilhadas: motor de preços, OSRM, geografia, chamadas, recibos, auditoria. |
| `app/Http/Controllers` | Agrupados por ator: `Passageiro`, `Condutor`, `Admin`, `Auth`, `Chamada`, `Comunicacao`, `Pagina`. |
| `app/Http/Middleware` | Headers de segurança e CSP, guardas de tipo/nível, expulsão de suspensos, timeout de inatividade. |
| `routes/api.php` | Os ~70 endpoints JSON consumidos pelos painéis. |
| `routes/web.php` | As páginas HTML. |
| `resources/views` | Os três painéis e as páginas de autenticação, em Blade. |
| `realtime/` | Serviço Node/Socket.io, independente do Laravel. |
| `legacy/` | O sistema anterior em PHP puro, guardado como referência. |

### Duas populações de contas

Passageiros e condutores vivem em `utilizadores`; o backoffice vive em
`administradores`. São dois guards de sessão distintos (`utilizador` e
`admin`, em `config/auth.php`) e nunca se cruzam: um admin autenticado não é
um utilizador, e vice-versa.

### API com sessão, não stateless

As rotas de `/api` correm no grupo de middleware `web` e usam a mesma sessão e
o mesmo token CSRF das páginas — é assim que os painéis as chamam (`fetch` com
`credentials: 'same-origin'`). Os erros saem sempre no formato `{"erro": "..."}`
que o frontend espera.

### Ficheiros privados

Comprovativos de pagamento e recibos PDF ficam em `storage/app/private`, fora
do document root, e só são servidos por endpoints autenticados
(`/api/condutor/recibo?id=`, `/api/admin/comprovativo?id=`).

### Notificações push das chamadas e do ponto

O aviso de chamada a receber chega com a app fechada ou o telemóvel bloqueado,
por Web Push: o Service Worker (`public/sw.js`) é acordado pelo sistema sem
nenhum separador aberto. O polling de `kg-chamada.js` e o socket.io só existem
enquanto há um painel vivo — sem isto, quem tivesse acabado de arrumar o
telemóvel não dava por nada.

Pela mesma razão são avisados os instantes da viagem que ninguém adivinha de
fora: o veículo entrou no ponto, entrou na fila, saiu do ponto e chegou ao
ponto. Tudo isso se decide dentro do carro (o condutor guarda o ponto, entra
na fila, carrega em "Iniciar rota") ou é detetado pelo GPS, sem ninguém
carregar em nada — e quem espera no ponto tem o telemóvel no bolso, não o
painel aberto à frente dos olhos. Quem fica sem lugar por o veículo ter saído
do ponto leva a mensagem que lhe diz isso mesmo, para não continuar à espera
de um carro que já partiu.

Cada acontecimento chega a quem lhe diz respeito: os passageiros do veículo,
quem ainda espera nesse ponto por um carro qualquer (até à recolha — depois
disso já saiu do mercado), o próprio condutor e os administradores. Um veículo
ainda por aprovar não incomoda quem espera: não aparece na lista de
disponíveis, portanto não há carro nenhum para reservar. Além do push, cada
aviso deixa uma linha em `notificacoes` — é o sino do painel, para quem só
abrir a app mais tarde; o backoffice tem apenas essa linha, já que as
subscrições de push são de utilizadores e não de administradores. Nessas
linhas `remetente_id` fica a `NULL`: não é ninguém a escrever a ninguém, é o
sistema a relatar o que o veículo fez.
`App\Services\AvisoViagemService` trata de todos, sempre depois da resposta já
ter seguido para o condutor e sem nunca poder fazer falhar a viagem.

A cifra (RFC 8291) e a assinatura VAPID (RFC 8292) são feitas com o `openssl`
do PHP em `App\Support\CifraWebPush`, sem dependências novas; o conteúdo vai
cifrado de ponta a ponta, por isso o serviço de push do fabricante encaminha o
pacote sem conseguir ler quem ligou a quem. Gerar as chaves uma vez, por
instalação:

```bash
php artisan push:chaves   # imprime as duas linhas a colar no .env
```

Sem essas chaves o push fica desligado e o resto funciona como antes. Cada
utilizador ativa as notificações no painel (Preferências de som → "Avisar mesmo
com a app fechada"): a permissão é pedida a partir desse clique, que é o que o
Safari e o Chrome em iOS exigem. Em iOS, o site tem de estar instalado no ecrã
principal para receber push.

### Visibilidade limitada ao município

Condutor e passageiro só se veem um ao outro dentro do mesmo concelho. A
fronteira é a delimitação administrativa dos 22 municípios de Cabo Verde
(`public/assets/geo/cv-municipios.geojson`, gerado por
`docs/gerar-municipios.py` a partir do geoBoundaries/OpenStreetMap), lida no
servidor por `App\Services\MunicipioService` e desenhada no mapa dos painéis
pelo mesmo traçado — o que o condutor vê no ecrã é exatamente o alcance que o
servidor lhe dá.

* `/api/condutor/passageiros_urbanos` só devolve os pedidos em aberto que
  caem no concelho onde o condutor está agora (GPS do painel; sem ele, a
  última posição gravada do veículo). Sem posição nenhuma não há lista: a
  resposta explica que é preciso ligar o GPS.
* `/api/passageiro/veiculos_disponiveis` só devolve os veículos cuja posição
  atual cai no concelho da procura — o do GPS do passageiro numa viagem
  urbana, o do ponto de partida escolhido numa intermunicipal (a carreira sai
  de lá, e o lugar pode ser comprado de outro concelho).

Não é um raio em quilómetros: na Praia dois pontos a 5 km podem estar em
concelhos diferentes e no Porto Novo 20 km continuam a ser o mesmo. Os limites
de `limites_cidades` (centro + raio) são outra coisa e continuam a servir só o
motor de preços, para separar o troço urbano do intermunicipal.

Se o GeoJSON faltar ou estiver ilegível a regra não é aplicada e as listas
saem completas, com um aviso no log: mais vale a procura de sempre do que
deixar toda a gente sem ninguém para ver por causa de um ficheiro em falta.

### Tempo real

O Apache faz proxy de `/socket.io/` para o processo Node em `127.0.0.1:3001`
(ver `public/.htaccess`), para o browser falar sempre com a própria origem e o
`wss://` funcionar com o certificado da página. Sem os módulos `mod_proxy_*`
ativos, o cliente cai sozinho no fallback de polling.

## Configuração

As regras de negócio (limites geográficos de Cabo Verde, formato do telefone,
layout de assentos, tarifários) estão em `config/vmillion.php`. O que muda
entre ambientes vem do `.env`:

| Variável | Para quê |
|---|---|
| `VMILLION_OSRM_URL` | Instância própria do OSRM. É a via principal do cálculo de rotas. |
| `VMILLION_OSRM_PUBLIC_URL` | Instância pública, usada só quando a própria falha. |
| `VMILLION_ROTA_CACHE_TTL` | Validade da cache de rotas (24 h por omissão). |
| `VMILLION_LOGIN_MAX_TENTATIVAS` / `_BLOQUEIO_MINUTOS` | Bloqueio de conta por tentativas falhadas. |
| `SESSION_INACTIVITY_TIMEOUT` | Minutos de inatividade até a sessão ser destruída. |
| `VMILLION_PUSH_CHAVE_PUBLICA` / `_PRIVADA` | Par VAPID das notificações push (`php artisan push:chaves`). Vazias = push desligado. |
| `VMILLION_PUSH_ASSUNTO` | Contacto que vai no JWT VAPID, para o serviço de push avisar de problemas. |
| `KG_REALTIME_PORT` / `KG_CORS_ORIGIN` | Lidas pelo processo Node, não pelo Laravel. |

Em produção usa-se `CACHE_STORE=redis` (a cache de rotas do OSRM é o que mais
alivia o motor de rotas). Sem Redis o sistema continua a funcionar: a cache é
sempre um atalho, nunca uma dependência.

## Testes

```bash
php artisan test
```

Correm contra MySQL, não SQLite, porque o schema usa ENUM, CHECK constraints e
`ON UPDATE CURRENT_TIMESTAMP`. Criar a base de testes uma vez:

```bash
mysql -uroot -e "CREATE DATABASE kabugo_v2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
