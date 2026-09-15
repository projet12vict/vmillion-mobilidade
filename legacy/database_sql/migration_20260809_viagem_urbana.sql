-- ==============================================
-- V-MILLION — Migração: distinguir viagem urbana de intermunicipal
-- 2026-08-09
--
-- Âmbito: reservas passa a guardar o tipo de viagem. Para viagens urbanas,
-- o destino já não é um pontos_partida escolhido de uma lista — reaproveita
-- o mecanismo já existente de ponto de descida (nome/lat/lng, com
-- autocomplete Nominatim), que passa a ser obrigatório nesse caso em vez de
-- opcional. destino_id mantém-se preenchido (= ponto_partida_id) apenas
-- para satisfazer a FK, sem influenciar o preço nas viagens urbanas — ver
-- includes/pricing.php (kg_calcular_preco_urbano) e api/passageiro/reservar.php.
-- Aplica-se sobre kabugo_v2 já em produção — apenas ADD, nada é eliminado.
-- Backup prévio em database/backups/kabugo_v2_pre_urbano_20260809.sql.
-- ==============================================

USE kabugo_v2;

ALTER TABLE reservas
    ADD COLUMN tipo_viagem ENUM('urbano','intermunicipal') NOT NULL DEFAULT 'intermunicipal' AFTER motivo;
