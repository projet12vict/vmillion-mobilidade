-- ==============================================
-- V-MILLION — Migração: piloto operacional em Santiago
-- 2026-08-08
--
-- Âmbito: preços por limite de cidade, distinção urbana/intermunicipal por
-- veículo, pagamentos de condutores por rota com recibo, avaliações,
-- sugestões/reclamações, notificações do Super Admin e comunicação
-- passageiro-condutor. Aplica-se sobre a base kabugo_v2 já em produção —
-- apenas ADD/CREATE, nada é eliminado ou reescrito.
-- ==============================================

USE kabugo_v2;

-- ----------------------------------------------
-- 1) Configurações gerais de preço (valor mínimo/máximo, taxa de operação)
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS config_precos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(50) UNIQUE NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    descricao VARCHAR(255) NULL,
    atualizado_por INT UNSIGNED NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_precos_admin FOREIGN KEY (atualizado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO config_precos (chave, valor, descricao) VALUES
('valor_minimo', 100.00, 'Valor mínimo da viagem (CVE)'),
('valor_maximo', 5000.00, 'Valor máximo da viagem (CVE)'),
('taxa_operacao_rota', 50.00, 'Taxa que o condutor paga por rota completa (ida e volta), em CVE');

-- ----------------------------------------------
-- 2) Limites de cidade (raio a partir de um centro, para segmentar
--    troços urbanos/intermunicipais de uma viagem entre cidades)
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS limites_cidades (
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

-- Seed a partir das cidades já usadas em pontos_partida (raio de referência,
-- ajustável pelo Super Admin em Admin > Preços > Limites de cidade).
INSERT IGNORE INTO limites_cidades (cidade, lat, lng, raio_km) VALUES
('Praia', 14.9177, -23.5092, 6.0),
('Santa Catarina', 15.0983, -23.6703, 4.0),
('Tarrafal', 15.2785, -23.7519, 3.5),
('Ribeira Grande de Santiago', 14.9153, -23.6047, 3.0);

-- ----------------------------------------------
-- 3) Veículos: tipo de serviço e rota fixa associada
-- ----------------------------------------------
-- Nota: este script destina-se a correr uma única vez sobre kabugo_v2 (tal
-- como database/backups/migration_20260807.sql) — não é idempotente nos ALTER.
ALTER TABLE veiculos
    ADD COLUMN tipo_servico ENUM('urbano','intermunicipal','ambos') NOT NULL DEFAULT 'ambos' AFTER tipo,
    ADD COLUMN rota_fixa_id INT UNSIGNED NULL AFTER destino_id;

ALTER TABLE veiculos
    ADD CONSTRAINT fk_veiculo_rota_fixa FOREIGN KEY (rota_fixa_id) REFERENCES precos_rotas(id) ON DELETE SET NULL;

-- ----------------------------------------------
-- 4) Preços fixos por rota: distância total (para fracionamento proporcional
--    quando um passageiro embarca/desce num ponto intermédio da rota)
-- ----------------------------------------------
ALTER TABLE precos_rotas
    ADD COLUMN distancia_km DECIMAL(8,2) NULL AFTER preco_fixo_cve;

-- Preenche a distância das rotas fixas já existentes (Haversine em SQL).
UPDATE precos_rotas pr
JOIN pontos_partida po ON po.id = pr.ponto_origem_id
JOIN pontos_partida pd ON pd.id = pr.ponto_destino_id
SET pr.distancia_km = 6371 * 2 * ASIN(SQRT(
    POWER(SIN(RADIANS(pd.lat - po.lat) / 2), 2) +
    COS(RADIANS(po.lat)) * COS(RADIANS(pd.lat)) * POWER(SIN(RADIANS(pd.lng - po.lng) / 2), 2)
))
WHERE pr.distancia_km IS NULL;

-- ----------------------------------------------
-- 5) Pagamentos dos condutores (taxa de operação por rota, com recibo PDF)
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos_condutores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condutor_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    rota_id INT UNSIGNED NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    referencia VARCHAR(40) NOT NULL UNIQUE,
    data_pagamento DATETIME NULL,
    data_validade DATETIME NULL,
    aprovado_por INT UNSIGNED NULL,
    status ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente',
    recibo_path VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagcond_condutor FOREIGN KEY (condutor_id) REFERENCES utilizadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagcond_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagcond_rota FOREIGN KEY (rota_id) REFERENCES precos_rotas(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagcond_admin FOREIGN KEY (aprovado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_pagcond_status ON pagamentos_condutores(condutor_id, status, data_validade);

-- ----------------------------------------------
-- 6) Avaliações de condutores (gosto/não gosto, públicas)
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS avaliacoes_condutores (
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
-- 7) Sugestões e reclamações
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS sugestoes (
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
-- 8) Notificações do Super Admin (broadcast / individuais)
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS notificacoes (
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
-- 9) Comunicação (autofalante) entre passageiros e condutor numa viagem
-- ----------------------------------------------
CREATE TABLE IF NOT EXISTS comunicacoes_veiculo (
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
-- 10) Proprietário pode ser condutor: já suportado por utilizadores.proprietario_id
--     (um utilizador tipo='condutor' pode apontar para o proprietario que também é).
--     Aqui apenas marcamos explicitamente quando o próprio proprietário conduz.
-- ----------------------------------------------
ALTER TABLE proprietarios
    ADD COLUMN utilizador_condutor_id INT UNSIGNED NULL AFTER nif;

ALTER TABLE proprietarios
    ADD CONSTRAINT fk_proprietario_condutor FOREIGN KEY (utilizador_condutor_id) REFERENCES utilizadores(id) ON DELETE SET NULL;
