-- ==============================================
-- V-MILLION — Migração: pedido de viagem urbana em aberto (sem veículo escolhido)
-- 2026-08-09
--
-- Âmbito: até agora toda a reserva exigia um veículo/lugar escolhido no
-- momento da criação — por isso um condutor só via um passageiro depois de
-- esse passageiro já o ter escolhido especificamente. Isso deixava de fora
-- os condutores aprovados que não estão num ponto (a circular, em casa):
-- nunca viam a procura urbana. veiculo_id/assento_id passam a poder ficar
-- NULL enquanto a reserva urbana está "em aberto" — qualquer condutor
-- aprovado e em dia pode reclamá-la (ver api/condutor/recolher_urbano.php);
-- a partir daí segue exatamente o mesmo fluxo já existente (confirmar,
-- embarcar, chat, WhatsApp) sem mais nenhuma alteração. As FKs continuam
-- válidas — uma FK não se aplica a um valor NULL.
-- Aplica-se sobre kabugo_v2 já em produção. Backup prévio em
-- database/backups/kabugo_v2_pre_broadcast_urbano_20260809.sql.
-- ==============================================

USE kabugo_v2;

ALTER TABLE reservas
    MODIFY COLUMN veiculo_id INT UNSIGNED NULL,
    MODIFY COLUMN assento_id INT UNSIGNED NULL;
