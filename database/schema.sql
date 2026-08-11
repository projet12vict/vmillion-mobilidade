-- ==============================================
-- V-MILLION — Schema MySQL/MariaDB
-- Charset utf8mb4 | InnoDB | FKs
-- ==============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS kabugo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kabugo;

-- ----------------------------------------------
-- UTILIZADORES (passageiros e condutores)
-- ----------------------------------------------
CREATE TABLE utilizadores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('passageiro','condutor') NOT NULL,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL UNIQUE,
    nif CHAR(9) NOT NULL UNIQUE,
    email VARCHAR(150) NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    status ENUM('ativo','pendente','suspenso') NOT NULL DEFAULT 'ativo',
    proprietario_id INT UNSIGNED NULL,
    tentativas_login TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_telefone CHECK (telefone REGEXP '^\\+238[0-9]{7}$'),
    CONSTRAINT chk_nif CHECK (nif REGEXP '^[0-9]{9}$')
) ENGINE=InnoDB;

CREATE INDEX idx_utilizadores_tipo_status ON utilizadores(tipo, status);

-- ----------------------------------------------
-- PROPRIETÁRIOS (donos de frota, podem não ser condutores)
-- ----------------------------------------------
CREATE TABLE proprietarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL UNIQUE,
    nif CHAR(9) NOT NULL UNIQUE,
    utilizador_condutor_id INT UNSIGNED NULL, -- preenchido quando o próprio proprietário também conduz
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_prop_telefone CHECK (telefone REGEXP '^\\+238[0-9]{7}$'),
    CONSTRAINT chk_prop_nif CHECK (nif REGEXP '^[0-9]{9}$')
) ENGINE=InnoDB;

ALTER TABLE utilizadores
    ADD CONSTRAINT fk_utilizador_proprietario
    FOREIGN KEY (proprietario_id) REFERENCES proprietarios(id) ON DELETE SET NULL;

-- ----------------------------------------------
-- ADMINISTRADORES
-- ----------------------------------------------
CREATE TABLE administradores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    nivel ENUM('gestor','admin','super') NOT NULL DEFAULT 'admin',
    senha_temporaria TINYINT(1) NOT NULL DEFAULT 1,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    tentativas_login TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    criado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_criado_por FOREIGN KEY (criado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- PONTOS DE PARTIDA
-- ----------------------------------------------
CREATE TABLE pontos_partida (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL UNIQUE,
    cidade VARCHAR(100) NOT NULL,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    zona ENUM('urbana','intermunicipal') NOT NULL DEFAULT 'urbana',
    -- Fluxo de aprovação (migration_20260809_pontos_aprovacao.sql): só um
    -- ponto 'aprovado' é visível a passageiros/condutores.
    status ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT UNSIGNED NULL,
    aprovado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aprovado_em DATETIME NULL,
    CONSTRAINT chk_ponto_lat CHECK (lat BETWEEN 14.7 AND 17.2),
    CONSTRAINT chk_ponto_lng CHECK (lng BETWEEN -25.4 AND -22.7),
    CONSTRAINT fk_ponto_admin FOREIGN KEY (criado_por) REFERENCES administradores(id) ON DELETE SET NULL,
    CONSTRAINT fk_ponto_aprovado_por FOREIGN KEY (aprovado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- PARQUES DE ESTACIONAMENTO
-- ----------------------------------------------
CREATE TABLE parques (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    morada VARCHAR(200) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    capacidade_total SMALLINT UNSIGNED NOT NULL,
    vagas_ocupadas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_parque_lat CHECK (lat BETWEEN 14.7 AND 17.2),
    CONSTRAINT chk_parque_lng CHECK (lng BETWEEN -25.4 AND -22.7),
    CONSTRAINT chk_parque_vagas CHECK (vagas_ocupadas <= capacidade_total),
    CONSTRAINT fk_parque_admin FOREIGN KEY (criado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- VEÍCULOS
-- ----------------------------------------------
CREATE TABLE veiculos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    matricula VARCHAR(20) NOT NULL UNIQUE,
    tipo ENUM('hiace','taxi','autocarro') NOT NULL,
    tipo_servico ENUM('urbano','intermunicipal','ambos') NOT NULL DEFAULT 'ambos',
    cor VARCHAR(40) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    lugares_total TINYINT UNSIGNED NOT NULL DEFAULT 14,
    lugares_livres TINYINT UNSIGNED NOT NULL DEFAULT 14,
    estado ENUM('no_ponto','na_fila','em_movimento','partiu_da_fila','chegou_destino') NOT NULL DEFAULT 'no_ponto',
    aprovado TINYINT(1) NOT NULL DEFAULT 0,
    ponto_partida_id INT UNSIGNED NULL,
    destino_id INT UNSIGNED NULL,
    rota_fixa_id INT UNSIGNED NULL, -- rota de precos_rotas associada (fracionamento proporcional)
    posicao_fila SMALLINT UNSIGNED NULL,
    lat DECIMAL(9,6) NULL,
    lng DECIMAL(9,6) NULL,
    ultima_posicao_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_veiculo_lugares CHECK (lugares_total = 14 AND lugares_livres <= 14),
    CONSTRAINT fk_veiculo_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_veiculo_ponto FOREIGN KEY (ponto_partida_id) REFERENCES pontos_partida(id) ON DELETE SET NULL,
    CONSTRAINT fk_veiculo_destino FOREIGN KEY (destino_id) REFERENCES pontos_partida(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_veiculos_estado ON veiculos(estado, aprovado, ponto_partida_id);

-- ----------------------------------------------
-- ASSENTOS POR VEÍCULO (14 lugares fixos: fila 0 = 2, filas 1-4 = 3 cada)
-- ----------------------------------------------
CREATE TABLE assentos_veiculo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT UNSIGNED NOT NULL,
    numero TINYINT UNSIGNED NOT NULL, -- 1..14
    fila TINYINT UNSIGNED NOT NULL,   -- 0..4
    coluna TINYINT UNSIGNED NOT NULL, -- posição na fila
    ocupado TINYINT(1) NOT NULL DEFAULT 0,
    reserva_id INT UNSIGNED NULL,
    UNIQUE KEY uq_assento_veiculo (veiculo_id, numero),
    CONSTRAINT fk_assento_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------
-- PREÇOS: rota fixa e por km (urbano / intermunicipal)
-- ----------------------------------------------
CREATE TABLE precos_rotas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ponto_origem_id INT UNSIGNED NOT NULL,
    ponto_destino_id INT UNSIGNED NOT NULL,
    preco_fixo_cve DECIMAL(10,2) NOT NULL,
    distancia_km DECIMAL(8,2) NULL, -- usado para fracionar o preço em paragens intermédias
    definido_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rota (ponto_origem_id, ponto_destino_id),
    CONSTRAINT fk_preco_rota_origem FOREIGN KEY (ponto_origem_id) REFERENCES pontos_partida(id) ON DELETE CASCADE,
    CONSTRAINT fk_preco_rota_destino FOREIGN KEY (ponto_destino_id) REFERENCES pontos_partida(id) ON DELETE CASCADE,
    CONSTRAINT fk_preco_rota_admin FOREIGN KEY (definido_por) REFERENCES administradores(id)
) ENGINE=InnoDB;

ALTER TABLE veiculos
    ADD CONSTRAINT fk_veiculo_rota_fixa FOREIGN KEY (rota_fixa_id) REFERENCES precos_rotas(id) ON DELETE SET NULL;

CREATE TABLE precos_km (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    zona ENUM('urbana','intermunicipal') NOT NULL UNIQUE,
    preco_por_km_cve DECIMAL(10,2) NOT NULL,
    atualizado_por INT UNSIGNED NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_preco_km_admin FOREIGN KEY (atualizado_por) REFERENCES administradores(id)
) ENGINE=InnoDB;

-- ----------------------------------------------
-- CONFIGURAÇÕES GERAIS DE PREÇO (valor mínimo/máximo, taxa de operação)
-- ----------------------------------------------
CREATE TABLE config_precos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255) NULL,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_precos_admin FOREIGN KEY (atualizado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- LIMITES DE CIDADE (raio para segmentar troços urbanos/intermunicipais)
-- ----------------------------------------------
CREATE TABLE limites_cidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cidade VARCHAR(100) NOT NULL UNIQUE,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    raio_km DECIMAL(6,2) NOT NULL,
    atualizado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_limite_lat CHECK (lat BETWEEN 14.7 AND 17.2),
    CONSTRAINT chk_limite_lng CHECK (lng BETWEEN -25.4 AND -22.7),
    CONSTRAINT chk_limite_raio CHECK (raio_km > 0),
    CONSTRAINT fk_limite_admin FOREIGN KEY (atualizado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- RESERVAS
-- ----------------------------------------------
CREATE TABLE reservas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    passageiro_id INT UNSIGNED NOT NULL,
    -- NULL enquanto um pedido de viagem urbana está em aberto (nenhum
    -- condutor o reclamou ainda) — ver api/condutor/recolher_urbano.php.
    veiculo_id INT UNSIGNED NULL,
    assento_id INT UNSIGNED NULL,
    ponto_partida_id INT UNSIGNED NOT NULL,
    destino_id INT UNSIGNED NOT NULL,
    ponto_descida_nome VARCHAR(200) NULL,
    ponto_descida_lat DECIMAL(9,6) NULL,
    ponto_descida_lng DECIMAL(9,6) NULL,
    passageiro_lat DECIMAL(9,6) NULL, -- posição GPS ao vivo do passageiro (secção 5.3 do mapa)
    passageiro_lng DECIMAL(9,6) NULL,
    passageiro_localizacao_em DATETIME NULL,
    lugares SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    motivo ENUM('normal','grupo','passeio') NOT NULL DEFAULT 'normal',
    -- Viagem urbana: destino_id fica = ponto_partida_id (só para satisfazer
    -- a FK) — o destino real é o ponto de descida (nome/lat/lng acima),
    -- escrito/geocodificado pelo passageiro, não escolhido de uma lista.
    tipo_viagem ENUM('urbano','intermunicipal') NOT NULL DEFAULT 'intermunicipal',
    preco_final DECIMAL(10,2) NOT NULL,
    estado ENUM('pendente','confirmado','recusado','a_bordo','concluido') NOT NULL DEFAULT 'pendente',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reserva_passageiro FOREIGN KEY (passageiro_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_assento FOREIGN KEY (assento_id) REFERENCES assentos_veiculo(id) ON DELETE CASCADE,
    CONSTRAINT fk_reserva_ponto FOREIGN KEY (ponto_partida_id) REFERENCES pontos_partida(id),
    CONSTRAINT fk_reserva_destino FOREIGN KEY (destino_id) REFERENCES pontos_partida(id)
) ENGINE=InnoDB;

ALTER TABLE assentos_veiculo
    ADD CONSTRAINT fk_assento_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE SET NULL;

CREATE INDEX idx_reservas_estado ON reservas(estado, veiculo_id);

-- ----------------------------------------------
-- ALARMES / SOS
-- ----------------------------------------------
CREATE TABLE alarmes_sos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNSIGNED NOT NULL,
    tipo_utilizador ENUM('passageiro','condutor') NOT NULL,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    estado ENUM('pendente','em_curso','resolvido') NOT NULL DEFAULT 'pendente',
    resolvido_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sos_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_sos_admin FOREIGN KEY (resolvido_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_sos_estado ON alarmes_sos(estado);

-- ----------------------------------------------
-- COMPROVATIVOS DE PAGAMENTO (condutores/proprietários)
-- ----------------------------------------------
CREATE TABLE comprovativos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    ficheiro_path VARCHAR(255) NOT NULL,
    valor_cve DECIMAL(10,2) NULL,
    estado ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
    revisto_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revisto_em DATETIME NULL,
    CONSTRAINT fk_comprovativo_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_comprovativo_admin FOREIGN KEY (revisto_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- FATURAS
-- ----------------------------------------------
CREATE TABLE faturas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    referencia VARCHAR(50) NOT NULL UNIQUE,
    valor_cve DECIMAL(10,2) NOT NULL,
    estado ENUM('pendente','paga','vencida') NOT NULL DEFAULT 'pendente',
    vencimento DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fatura_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------
-- PACOTES DE PAGAMENTO (diário/semanal/mensal/anual, preço definido pelo Super Admin)
-- ----------------------------------------------
CREATE TABLE pacotes_pagamento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL,
    tipo_servico ENUM('urbano', 'intermunicipal', 'ambos') NOT NULL DEFAULT 'ambos',
    descricao VARCHAR(255) NULL,
    preco DECIMAL(10,2) NOT NULL,
    duracao_dias INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pacote_tipo_nome (tipo_servico, nome),
    CONSTRAINT fk_pacote_criado_por FOREIGN KEY (criado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------
-- PAGAMENTOS DE CONDUTORES (pacote diário/semanal/mensal/anual + comprovativo, com recibo PDF)
-- ----------------------------------------------
CREATE TABLE pagamentos_condutores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    rota_id INT UNSIGNED NULL,
    pacote_id INT UNSIGNED NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    referencia VARCHAR(40) NOT NULL UNIQUE,
    data_pagamento DATETIME NULL,
    data_validade DATETIME NULL,
    aprovado_por INT UNSIGNED NULL,
    aprovado_em DATETIME NULL,
    status ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
    recibo_path VARCHAR(255) NULL,
    comprovativo_path VARCHAR(255) NULL,
    comprovativo_tipo ENUM('imagem','pdf') NULL,
    observacao_admin TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagcond_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagcond_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagcond_rota FOREIGN KEY (rota_id) REFERENCES precos_rotas(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagcond_pacote FOREIGN KEY (pacote_id) REFERENCES pacotes_pagamento(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagcond_admin FOREIGN KEY (aprovado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pagcond_status ON pagamentos_condutores(condutor_id, status, data_validade);

-- ----------------------------------------------
-- AVALIAÇÕES DE CONDUTORES (gosto/não gosto, públicas)
-- ----------------------------------------------
CREATE TABLE avaliacoes_condutores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    passageiro_id INT UNSIGNED NOT NULL,
    reserva_id INT UNSIGNED NOT NULL,
    avaliacao TINYINT UNSIGNED NOT NULL,
    comentario VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avaliacao_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_passageiro FOREIGN KEY (passageiro_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    CONSTRAINT chk_avaliacao_valor CHECK (avaliacao BETWEEN 1 AND 5),
    UNIQUE KEY uq_avaliacao_reserva (reserva_id)
) ENGINE=InnoDB;

-- ----------------------------------------------
-- SUGESTÕES E RECLAMAÇÕES
-- ----------------------------------------------
CREATE TABLE sugestoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT UNSIGNED NOT NULL,
    tipo ENUM('sugestao','reclamacao') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    condutor_id INT UNSIGNED NULL,
    status ENUM('pendente','visto','implementado','resolvido') NOT NULL DEFAULT 'pendente',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sugestao_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugestao_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_sugestoes_tipo_status ON sugestoes(tipo, status);

-- ----------------------------------------------
-- NOTIFICAÇÕES DO SUPER ADMIN (broadcast / individuais)
-- ----------------------------------------------
CREATE TABLE notificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- destinatario_id refere utilizadores(id) para condutores/passageiros/todos,
    -- ou administradores(id) quando destinatario_tipo = 'admins' — por isso não
    -- tem FK direta (a origem depende de destinatario_tipo, validada em código).
    destinatario_id INT UNSIGNED NULL,
    destinatario_tipo ENUM('todos','admins','condutores','passageiros','individual') NOT NULL DEFAULT 'individual',
    remetente_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('alerta','informativo','urgente') NOT NULL DEFAULT 'informativo',
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificacao_remetente FOREIGN KEY (remetente_id) REFERENCES administradores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notificacoes_destinatario ON notificacoes(destinatario_id, destinatario_tipo, lida);

-- ----------------------------------------------
-- DESTINOS URBANOS (escritos/geocodificados por passageiros, reutilizáveis
-- como sugestão de autocomplete — não são pontos públicos como pontos_partida)
-- ----------------------------------------------
CREATE TABLE destinos_urbanos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    lat DECIMAL(9,6) NOT NULL,
    lng DECIMAL(9,6) NOT NULL,
    criado_por INT UNSIGNED NOT NULL,
    usos INT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_destino_urbano_lat CHECK (lat BETWEEN 14.7 AND 17.2),
    CONSTRAINT chk_destino_urbano_lng CHECK (lng BETWEEN -25.4 AND -22.7),
    CONSTRAINT fk_destino_urbano_criador FOREIGN KEY (criado_por) REFERENCES utilizadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_destino_urbano_nome (nome)
) ENGINE=InnoDB;

CREATE INDEX idx_destinos_urbanos_nome ON destinos_urbanos(nome);

-- ----------------------------------------------
-- COMUNICAÇÃO (AUTOFALANTE) ENTRE PASSAGEIROS E CONDUTOR NUMA VIAGEM
-- ----------------------------------------------
CREATE TABLE comunicacoes_veiculo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT UNSIGNED NOT NULL,
    remetente_id INT UNSIGNED NOT NULL,
    destinatario_id INT UNSIGNED NULL,
    mensagem VARCHAR(500) NOT NULL,
    tipo ENUM('texto','sistema') NOT NULL DEFAULT 'texto',
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comunicacao_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    CONSTRAINT fk_comunicacao_remetente FOREIGN KEY (remetente_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_comunicacao_destinatario FOREIGN KEY (destinatario_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_comunicacoes_veiculo ON comunicacoes_veiculo(veiculo_id, criado_em);

-- ----------------------------------------------
-- CHAMADAS (simuladas, passageiro <-> condutor — ver migration_20260810_chamadas.sql)
-- ----------------------------------------------
CREATE TABLE chamadas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT UNSIGNED NOT NULL,
    destinatario_id INT UNSIGNED NOT NULL,
    estado ENUM('iniciada', 'atendida', 'recusada', 'terminada') NOT NULL DEFAULT 'iniciada',
    iniciada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida_em DATETIME NULL,
    terminada_em DATETIME NULL,
    atualizada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_chamada_remetente FOREIGN KEY (remetente_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_chamada_destinatario FOREIGN KEY (destinatario_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_chamadas_participantes ON chamadas(destinatario_id, remetente_id, estado);

-- ----------------------------------------------
-- SINALIZAÇÃO WEBRTC DAS CHAMADAS (ver migration_20260810_sinalizacao_chamada.sql)
-- ----------------------------------------------
CREATE TABLE sinalizacao_chamada (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chamada_id INT UNSIGNED NOT NULL,
    remetente_id INT UNSIGNED NOT NULL,
    tipo ENUM('offer', 'answer', 'ice') NOT NULL,
    payload TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sinal_chamada FOREIGN KEY (chamada_id) REFERENCES chamadas(id) ON DELETE CASCADE,
    CONSTRAINT fk_sinal_remetente FOREIGN KEY (remetente_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_sinal_chamada ON sinalizacao_chamada(chamada_id, id);

-- ----------------------------------------------
-- LOGS DE AUDITORIA
-- ----------------------------------------------
CREATE TABLE logs_auditoria (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    acao VARCHAR(100) NOT NULL,
    entidade VARCHAR(60) NULL,
    entidade_id INT UNSIGNED NULL,
    detalhes TEXT NULL,
    ip VARCHAR(45) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_logs_criado_em ON logs_auditoria(criado_em);

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------
-- SEED: Super Admin + pontos iniciais de Santiago + preços base
-- ----------------------------------------------
-- Senha temporária "VMILLION#2026" (hash bcrypt real, gerado com password_hash) — DEVE ser trocada no primeiro login.
INSERT INTO administradores (nome, email, senha_hash, nivel, senha_temporaria, ativo)
VALUES ('Super Admin', 'admin@vmillion.cv', '$2y$10$sxC8LOlpg4WNpzrt8pClHO74Ro6pX3l5awjzAH/S2FFPWkFv4X/gK', 'super', 1, 1);

INSERT INTO pontos_partida (nome, cidade, lat, lng, zona, status, ativo, aprovado_em) VALUES
('Mercado de Sucupira', 'Praia', 14.917500, -23.509400, 'urbana', 'aprovado', 1, CURRENT_TIMESTAMP),
('Estádio da Várzea', 'Praia', 14.915800, -23.515000, 'urbana', 'aprovado', 1, CURRENT_TIMESTAMP),
('Assomada', 'Santa Catarina', 15.098300, -23.670300, 'intermunicipal', 'aprovado', 1, CURRENT_TIMESTAMP),
('Tarrafal', 'Tarrafal', 15.278500, -23.751900, 'intermunicipal', 'aprovado', 1, CURRENT_TIMESTAMP),
('Cidade Velha', 'Ribeira Grande de Santiago', 14.915300, -23.604700, 'intermunicipal', 'aprovado', 1, CURRENT_TIMESTAMP);

INSERT INTO precos_km (zona, preco_por_km_cve, atualizado_por) VALUES
('urbana', 5.00, 1),
('intermunicipal', 10.00, 1);

INSERT INTO config_precos (chave, valor, descricao) VALUES
('valor_minimo', 100.00, 'Valor mínimo da viagem (CVE)'),
('valor_maximo', 5000.00, 'Valor máximo da viagem (CVE)'),
('taxa_operacao_rota', 50.00, 'Taxa que o condutor paga por rota completa (ida e volta), em CVE');

INSERT INTO limites_cidades (cidade, lat, lng, raio_km) VALUES
('Praia', 14.9177, -23.5092, 6.0),
('Santa Catarina', 15.0983, -23.6703, 4.0),
('Tarrafal', 15.2785, -23.7519, 3.5),
('Ribeira Grande de Santiago', 14.9153, -23.6047, 3.0);

-- Pacotes 'ambos' por omissão (servem qualquer veículo, urbano ou
-- intermunicipal); o Super Admin pode criar variantes específicas por tipo
-- de serviço (preços diferentes) a partir do painel.
INSERT INTO pacotes_pagamento (nome, tipo_servico, descricao, preco, duracao_dias) VALUES
('diario', 'ambos', 'Acesso por 1 dia', 100.00, 1),
('semanal', 'ambos', 'Acesso por 7 dias', 500.00, 7),
('mensal', 'ambos', 'Acesso por 30 dias', 1500.00, 30),
('anual', 'ambos', 'Acesso por 365 dias', 15000.00, 365);
