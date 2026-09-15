-- ==============================================
-- V-MILLION — Migração: destinos urbanos gravados e reutilizáveis
-- 2026-08-09
--
-- Âmbito: cada destino escrito por um passageiro numa viagem urbana fica
-- gravado (nome + coordenadas geocodificadas) para poder ser sugerido a
-- outros passageiros antes de irem à pesquisa externa (Nominatim) — não
-- aparece como ponto público como pontos_partida, é só uma lista de
-- autocomplete (api/passageiro/destinos_urbanos.php). Aplica-se sobre
-- kabugo_v2 já em produção — apenas ADD. Backup prévio em
-- database/backups/kabugo_v2_pre_destinos_urbanos_20260809.sql.
-- ==============================================

USE kabugo_v2;

CREATE TABLE IF NOT EXISTS destinos_urbanos (
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
