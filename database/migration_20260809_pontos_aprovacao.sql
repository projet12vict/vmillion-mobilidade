-- ==============================================
-- V-MILLION — Migração: fluxo de aprovação de pontos de partida
-- 2026-08-09
--
-- Âmbito: pontos_partida passa a ter um estado de aprovação
-- (pendente/aprovado/recusado) em vez de aparecer imediatamente. Os pontos
-- já em produção (criados antes deste fluxo existir) ficam 'aprovado'
-- retroativamente, para não interromper o piloto em curso. Aplica-se sobre
-- kabugo_v2 já em produção — apenas ADD/UPDATE, nada é eliminado ou
-- reescrito. Backup prévio em database/backups/kabugo_v2_pre_pontos_aprovacao_20260809.sql.
-- ==============================================

USE kabugo_v2;

ALTER TABLE pontos_partida
    ADD COLUMN status ENUM('pendente','aprovado','recusado') NOT NULL DEFAULT 'pendente' AFTER zona,
    ADD COLUMN aprovado_por INT UNSIGNED NULL AFTER criado_por,
    ADD COLUMN aprovado_em DATETIME NULL AFTER criado_em;

ALTER TABLE pontos_partida
    ADD CONSTRAINT fk_ponto_aprovado_por FOREIGN KEY (aprovado_por) REFERENCES administradores(id) ON DELETE SET NULL;

-- Pontos já ativos em produção foram usados sem fluxo de aprovação —
-- consideram-se aprovados retroativamente (a data de aprovação fica igual
-- à de criação, já que não há registo de uma aprovação explícita anterior).
UPDATE pontos_partida SET status = 'aprovado', aprovado_em = criado_em WHERE ativo = 1;
