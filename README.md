# V-MILLION — Plataforma de Mobilidade Inteligente para Cabo Verde

> Anteriormente conhecido como "KabuGo" — nome alterado para V-MILLION. As referências a "KabuGo" mantidas neste documento a partir daqui (base de dados `kabugo`/`kabugo_v2`, alias `/kabugo`, ficheiros e contas antigas) são registo histórico do que foi efetivamente criado nessa altura, não o nome atual do produto.

Implementação inicial conforme a especificação técnica (piloto Ilha de Santiago).

## Stack

PHP 8.2+ (PDO) · MySQL/MariaDB · MapLibre GL JS (3D) com fallback Leaflet (2D) · OSRM (instância própria, com cache Redis e fallback Haversine/linha reta) · Socket.io (Node) com fallback polling.

## Instalação a partir do Git

```
git clone <url-do-repositório> vmillion
cd vmillion
```

`config/config.php` e `config/db.php` **vêm no repositório e não contêm nenhuma credencial** — leem tudo de variáveis de ambiente com valores por omissão seguros para desenvolvimento local (ver secção 2 abaixo). Não existe um `config/db.example.php` para copiar: em produção, definam as variáveis de ambiente reais (`KG_DB_PASS`, etc.) no servidor/Apache em vez de editar estes ficheiros — assim nunca há uma credencial real para versionar por engano. `uploads/` e `database/backups/` ficam de fora do Git (`.gitignore`) porque contêm dados reais de condutores e passageiros — recriam-se localmente, nunca vêm do clone.

## Arranque local (XAMPP)

1. **Base de dados**: importe `database/schema.sql` no MySQL/MariaDB (cria a BD `kabugo`, tabelas e dados semente: Super Admin + 5 pontos de Santiago + preços base).
   ```
   mysql -u root -P <porta> < database/schema.sql
   ```
   O Super Admin semente é `admin@vmillion.cv` com senha temporária `VMILLION#2026` — troque-a no primeiro login (é forçado automaticamente).

   > **Neste ambiente**, a base já existe e chama-se `kabugo_v2` (não precisa de reimportar o schema) — ver a secção "Migração de dados" abaixo. O login de administrador a usar é `victorallissson@gmail.com` com a senha já existente dessa conta; o `admin@kabugo.cv` semente foi desativado para não haver dois Super Admin em simultâneo.

2. **Configuração**: as variáveis de ligação à BD lêem-se de variáveis de ambiente (`KG_DB_HOST`, `KG_DB_PORT`, `KG_DB_NAME`, `KG_DB_USER`, `KG_DB_PASS`) com valores por omissão em `config/config.php`. Por omissão liga a `127.0.0.1:3307` (porta usada pelo MySQL do XAMPP neste ambiente) à base `kabugo_v2`, com utilizador `root` sem senha. Ajuste por variável de ambiente se a sua instância for diferente.

   O motor de rotas (OSRM) e o cache de rotas (Redis) seguem o mesmo padrão: `KG_OSRM_URL` (omissão `http://localhost:5001`), `KG_OSRM_TIMEOUT_S` (omissão `4`), `KG_REDIS_HOST`/`KG_REDIS_PORT` (omissão `127.0.0.1:6379`). Nenhum é obrigatório — sem OSRM cai-se sempre para linha reta/Haversine, e sem Redis (ou sem a extensão `redis` instalada) o sistema continua a funcionar, só sem cache.

   > **Em produção**: definam `KG_ENV=production` mais as variáveis reais (`KG_DB_HOST`, `KG_DB_PORT`, `KG_DB_NAME`, `KG_DB_USER`, `KG_DB_PASS`, e as de OSRM/Redis se aplicável) como variáveis de ambiente do servidor/Apache (`SetEnv` no VirtualHost, ou no serviço systemd/painel de hosting) — nunca as escrevam diretamente em `config/config.php`, para esse ficheiro continuar seguro para estar no Git.

3. **Apache**: aponte um VirtualHost (ou o `htdocs` do XAMPP) para a pasta `public/` deste projeto — é a document root. As pastas `config/`, `includes/`, `database/` ficam fora do document root por segurança.

4. **Serviço de tempo real** (opcional para testar posições/SOS ao vivo):
   ```
   cd realtime
   npm install
   npm start
   ```
   Corre por omissão na porta 3001. Se não estiver a correr, o cliente cai automaticamente para polling a cada 5s — nada quebra.

   Se o Apache serve o site em HTTPS (comum no XAMPP), o `public/.htaccess`
   faz proxy de `/socket.io/` para este processo Node, para o cliente poder
   ligar em `wss://` à mesma origem da página em vez de `https://<host>:3001`
   (que falha com `ERR_SSL_PROTOCOL_ERROR` — o Node não tem certificado TLS).
   Isto requer os módulos `proxy_module`, `proxy_http_module` e
   `proxy_wstunnel_module` ativos no Apache: em `xampp/apache/conf/httpd.conf`,
   remova o `#` destas linhas e reinicie o Apache:
   ```
   LoadModule proxy_module modules/mod_proxy.so
   LoadModule proxy_http_module modules/mod_proxy_http.so
   LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
   ```
   Sem estes módulos o bloco de proxy no `.htaccess` fica inativo e o
   cliente continua a cair no fallback de polling, como antes.

## Testes automatizados

```
php tests/run_tests.php
```
Cobre validações (telefone/NIF/senha), geolocalização (bbox de Cabo Verde, Haversine), layout de 14 assentos e o exemplo de preços da secção 12.3 da especificação (25/25 testes).

## Verificado nesta fase (testes reais, ponta-a-ponta, contra base de dados MySQL)

- Registo de passageiro e condutor (com validação de telefone/NIF/senha e unicidade).
- Condutor fica `pendente` e não consegue iniciar sessão até aprovação.
- Login (passageiro, condutor, admin) com CSRF, `session_regenerate_id`, e **rate limiting real de 5 tentativas → bloqueio 15 min** (verificado a bloquear no 5º falhanço e a devolver 429).
- Fluxo de comprovativo → aprovação de condutor → registo e aprovação de veículo.
- Condutor escolhe ponto/destino, entra na fila (posição atribuída corretamente).
- Passageiro vê o veículo na fila, escolhe assento (layout 5 filas × 3), reserva — preço calculado e persistido.
- Contacto do condutor **só** é revelado ao passageiro após confirmação da reserva (antes disso é `null` na resposta da API).
- Condutor confirma reserva; lugar fica ocupado; `lugares_livres` decrementa corretamente.
- Deteção automática de chegada ao destino (<100m): reservas marcadas `concluido`, lugares libertados, ponto de partida/destino invertidos automaticamente.
- SOS: alerta criado pelo utilizador aparece imediatamente na central de alarmes do admin.
- 503 gracioso (sem stack trace) quando a base de dados está indisponível; páginas protegidas redirecionam (302) em vez de rebentar quando não há sessão.

## Migração de dados: `kabugo` → `kabugo_v2` (2026-08-07)

Antes desta fase existia já uma base `kabugo` (porta 3307 do XAMPP) com dados reais de uma iteração anterior do projeto, com um esquema diferente (incluía um módulo de emergência/hospitais não presente na especificação atual). Foi feita uma migração seletiva, não um `mysqldump` cego, precisamente para não arrastar dados incompletos ou incompatíveis para o novo schema.

**Passos executados, por esta ordem:**

1. **Backup em ficheiro** de toda a `kabugo` original antes de qualquer alteração: `database/backups/kabugo_backup_20260807.sql` (`mysqldump --single-transaction`).
2. **Migração seletiva** dos registos que satisfaziam integralmente as regras do novo schema (script auditável em `database/backups/migration_20260807.sql`):
   - **1 proprietário** (Claudina) — telefone normalizado para `+238...`.
   - **1 administrador real**, `victorallissson@gmail.com` (nível `super`, hash bcrypt existente preservado — a senha atual continua a funcionar sem alteração nenhuma). O Super Admin semente (`admin@kabugo.cv`) foi **desativado** (`ativo=0`, não eliminado) para respeitar a regra "Super Admin: único" da secção 3.
   - **1 condutor** ("PR", `+2389392659`) e **1 passageiro** ("TL", `+2385947134`) — os únicos 2 dos 5 utilizadores antigos com telefone e NIF completos e válidos no novo formato.
   - **1 veículo** ("ST - 01 - GG", 14 lugares, condutor "PR") com os respetivos 14 assentos recriados via `kg_criar_assentos_veiculo()`.
   - **1 ponto de partida novo** (Calheta) — os outros 5 pontos antigos já correspondiam, por nome e coordenadas, ao seed que já existia em `kabugo_v2`, por isso não foram duplicados.
   - **1 preço fixo de rota** (Estádio da Várzea → Calheta, 300 CVE).
   - **3 parques de estacionamento** (Sucupira, Assomada, Tarrafal) — mapeamento direto, sem perdas.
3. **Não migrado** (por não haver forma honesta de o fazer sem inventar dados):
   - **3 utilizadores** (`victor Tavares semedo`, `Elias`, `Radicar`) — sem telefone/NIF preenchidos no sistema antigo, campos agora obrigatórios. Precisam de ser contactados para completar o registo antes de poderem ser migrados manualmente.
   - **2 veículos** — pertenciam aos condutores acima (dados incompletos) ou tinham 15 lugares em vez dos 14 fixos do novo schema.
   - **4 reservas** — todas do mesmo passageiro sem telefone/NIF, sem assento nem preço associados no sistema antigo (o modelo de dados era diferente: `reservas`+`filas` separadas, sem ligação direta a um assento). São claramente artefactos de teste (3 delas em minutos umas das outras), não reservas reais em curso.
   - Nenhum destes dados foi apagado — continuam completos e consultáveis em `kabugo_backup_20260807` (a base) e no ficheiro `.sql` acima.
4. **Verificação**: `tests/run_tests.php` (25/25, testes de lógica pura, não dependem da BD) + teste manual ponta-a-ponta contra `kabugo_v2` (registo, login, listagem do veículo migrado visível a um passageiro real no ponto "Assomada").
5. **`kabugo` original renomeada para backup**: como o MariaDB não tem `RENAME DATABASE`, foi feito via `RENAME TABLE` tabela-a-tabela para uma nova base `kabugo_backup_20260807`, e a `kabugo` (agora vazia) foi eliminada. Nenhum dado foi perdido nesse processo — confirmado por contagem de linhas antes/depois.
6. **`config/config.php`** atualizado: valores por omissão passam a `KG_DB_PORT=3307` e `KG_DB_NAME=kabugo_v2` (a porta real usada pelo XAMPP neste ambiente).

**Nota para quem seguir este histórico**: `kabugo_backup_20260807` fica disponível no servidor MySQL como base de consulta (só leitura recomendada); o ficheiro `.sql` é a cópia portátil independente do servidor.

### Correção e fecho da migração (2026-08-08)

Ao retomar este trabalho verificou-se que o passo 5 acima **não chegou a ser executado no servidor**: a base `kabugo` continuava viva (não havia `kabugo_backup_20260807` no MySQL), e a cópia antiga do projeto em `C:\xampp\htdocs\kabugo` (que liga diretamente a `kabugo` via `includes/db.php`, porta 3307) continuou a receber uso real depois de 2026-08-07 — incluindo um condutor novo (`Valdir Tavares`, sem telefone/NIF) e 3 reservas adicionais (#28, #29 com o condutor "PR" e a passageira "TL", já migrados; #31 com o condutor "Valdir", não migrável) até 2026-08-08 12:14.

Auditoria completa de `kabugo` confirmou que **nenhum destes registos adicionais é migrável honestamente** para `kabugo_v2`, pelas mesmas razões já documentadas acima:
- `Valdir Tavares` (condutor, criado 2026-08-08 12:05): sem telefone/NIF — mesma categoria de exclusão que `Elias`/`Radicar`.
- Veículo `ST - 60 - RF` (do Valdir): 15 lugares (schema novo exige exatamente 14) e condutor incompleto.
- Reservas **#28 e #29** (condutor "PR", passageira "TL" — ambos já migrados): tecnicamente entre utilizadores válidos, mas o modelo antigo (`filas`+`reservas`, sem assento nem preço) não tem os campos obrigatórios do novo schema (`assento_id`, `preco_final`) — migrá-las exigiria inventar o assento ocupado e recalcular o preço, o que violaria o mesmo princípio de "não inventar dados" usado no resto desta migração.
- Reserva **#31** (condutor "Valdir", não migrável): mesma razão do condutor, adicionada à falta de assento/preço.

**Conclusão**: `kabugo_v2` já continha, e continua a conter, tudo o que era honestamente migrável — não houve alterações a `kabugo_v2` nesta revisão. Foi feito um novo backup de ambas as bases antes desta auditoria: `database/backups/kabugo_pre_migracao_20260808_213629.sql` e `database/backups/kabugo_v2_pre_migracao_20260808_213629.sql`. A base `kabugo` **mantém-se apenas como arquivo de consulta** (a cópia antiga em `C:\xampp\htdocs\kabugo` que lhe ligava foi movida para `C:\backup_kabugo_htdocs\` — deixou de correr). Recomenda-se, quando possível, aplicar agora sim o `RENAME`/eliminação descrito no passo 5, ou simplesmente deixar `kabugo` como está (só leitura) — já não há nenhum processo ativo a escrever nela.

## Simplificações conhecidas (documentadas no código, por transparência)

- **Saída de fila com passageiros a bordo**: as reservas afetadas são libertadas e notificadas de imediato via tempo real para reservarem outro veículo no mesmo ponto, em vez de uma fila de reatribuição automática entre veículos (motor de emparelhamento fora do âmbito desta fase) — ver `public/api/condutor/fila_sair.php`.
- **Comprovativos**: upload funcional (PDF/JPG/PNG, 5MB) mas sem integração com gateway de pagamento — é um registo manual verificado pelo admin. O mesmo se aplica aos pagamentos de condutores (secção seguinte): "solicitar" regista a intenção, o admin aprova manualmente.
- **Recibo em PDF**: gerado por um escritor de PDF mínimo e sem dependências (`includes/pdf_recibo.php`, texto simples + fonte Helvetica base) em vez de uma biblioteca como dompdf/TCPDF, que não está instalada neste ambiente (sem Composer/vendor). Produz um PDF válido (verificado com `file` e por leitura do xref) mas sem logótipo gráfico — só texto.
- **Notificações e comunicação (autofalante) em broadcast**: usam "fan-out" (uma linha por destinatário) em vez de um único registo partilhado, precisamente para que o estado lido/não-lido seja individual por utilizador.
- **Segmentação urbana/intermunicipal por limite de cidade**: usa uma projeção plana local (equirretangular) e interseção reta/círculo — exata o suficiente para a escala de Santiago, mas é uma aproximação geométrica (não segue a geometria real das estradas/OSRM ponto a ponto dentro do círculo).

## Piloto operacional em Santiago (2026-08-08)

Adiciona política de preços completa, pagamentos de condutores com recibo PDF, avaliações, sugestões/reclamações, notificações do Super Admin e comunicação (autofalante) passageiro-condutor — ver `database/migration_20260808_operacional.sql` para o script aplicado sobre `kabugo_v2` (backup prévio em `database/backups/kabugo_v2_backup_20260808.sql`) e `database/schema.sql` para o schema completo já atualizado (novas instalações).

**Novas tabelas**: `config_precos`, `limites_cidades`, `pagamentos_condutores`, `avaliacoes_condutores`, `sugestoes`, `notificacoes`, `comunicacoes_veiculo`. **Colunas novas**: `veiculos.tipo_servico`/`rota_fixa_id`, `precos_rotas.distancia_km`, `proprietarios.utilizador_condutor_id`.

**Motor de preços** (`includes/pricing.php`): a antiga aproximação por metade da rota foi substituída por segmentação geométrica real usando `limites_cidades` (centro+raio por cidade); rotas fixas com `distancia_km` são fracionadas proporcionalmente ao troço de cada passageiro (embarque/desembarque), com o piso/teto de `config_precos` aplicado sempre no fim.

**Novos endpoints**: `api/admin/{limites_cidades,pagamentos,recibo,proprietarios,notificacoes,sugestoes}.php`, `api/condutor/{pagamentos,recibo,rotas_fixas}.php`, `api/passageiro/meus_condutores.php`, `api/avaliacao/{criar,consultar}.php`, `api/sugestoes/enviar.php`, `api/comunicacao/{enviar,listar,marcar_lida}.php`, `api/notificacoes/{listar,marcar_lida}.php`. `api/condutor/reserva_estado.php` ganhou as ações `embarcar`/`chegou` (liberta o lugar do passageiro específico, sem esperar a chegada de todo o veículo).

**Testado ponta-a-ponta contra `kabugo_v2` real** (contas temporárias criadas e eliminadas no fim, dados reais — Victor/PR/TL — intocados): login super admin; preços/limites de cidade/proprietários via API; fracionamento de rota fixa (Praia→Calheta a meio = exatamente metade do preço); registo de veículo com `tipo_servico`; filtro urbana/intermunicipal em `veiculos_disponiveis.php`; reserva com o motor de preços novo (via OSRM real); ciclo confirmar→embarcar→chegou com libertação do lugar; gate de pagamento (bloqueia o mapa do condutor sem pagamento válido, liberta após aprovação); aprovação de pagamento com geração e download real do recibo PDF; avaliação do condutor (com bloqueio de reavaliação da mesma reserva); reclamação de passageiro visível ao admin; chat passageiro↔condutor incluindo autofalante (broadcast); notificação urgente do Super Admin recebida pelo passageiro. `php -l` sem erros em todos os ficheiros do projeto; `tests/run_tests.php` com 32/32 testes a passar (7 novos, cobrindo limites de preço e a segmentação urbana/intermunicipal).

## Estrutura

```
config/        configuração + ligação PDO
includes/      segurança, validação geo, preços, layout de assentos, guardas de página
database/      schema.sql
public/        document root (páginas + /api + /assets)
realtime/      serviço Socket.io (Node)
tests/         testes automatizados de lógica pura
```

## Arrancar rapidamente (um comando)

Sem precisar de configurar um VirtualHost do Apache, o servidor de desenvolvimento embutido do PHP serve diretamente a pasta `public/` (foi o método usado em todos os testes ponta-a-ponta desta fase):

```powershell
C:\xampp\php\php.exe -S localhost:8080 -t public
```

Depois abra `http://localhost:8080/index.php` no browser. Para tempo real (opcional), noutro terminal:

```powershell
cd realtime; npm start
```

Se preferir mesmo o endereço `http://localhost/kabugo` via Apache do XAMPP (em vez da porta 8080), diga-me e configuro um VirtualHost/alias — implica editar `httpd-vhosts.conf` do XAMPP, por isso não o fiz sem confirmar consigo primeiro.

## Por fazer / próxima fase

Ver checklist da secção 20 da especificação para testes visuais/dispositivo real (responsividade, contraste WCAG AA, `prefers-reduced-motion` em hardware de gama baixa) — requerem um browser real, não são verificáveis por este ambiente de testes por linha de comandos.

## Licença

Ainda por decidir. Este é um piloto comercial com dados reais de utilizadores — a escolha entre licença aberta (MIT, GPL) e proprietária/fechada tem implicações legais e de negócio que não me cabe decidir. Enquanto não houver uma licença explícita, o código está protegido por direitos de autor por omissão (ninguém mais pode legalmente redistribuir ou reutilizar sem autorização) — se o objetivo é publicar num repositório privado só para a equipa, isto já é suficiente; se o repositório for público, recomenda-se escolher e adicionar um ficheiro `LICENSE` antes de o tornar público.
