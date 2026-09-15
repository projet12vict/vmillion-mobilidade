-- V-MILLION — Migração: localização GPS em tempo real do passageiro.
-- Aditiva e não destrutiva (colunas NULL por omissão): permite ao condutor
-- ver a posição exata do passageiro no mapa, e não apenas o ponto de partida.

ALTER TABLE reservas
    ADD COLUMN passageiro_lat DECIMAL(9,6) NULL AFTER ponto_descida_lng,
    ADD COLUMN passageiro_lng DECIMAL(9,6) NULL AFTER passageiro_lat,
    ADD COLUMN passageiro_localizacao_em DATETIME NULL AFTER passageiro_lng;
