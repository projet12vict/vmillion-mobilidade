-- ==============================================
-- V-MILLION — Migração: sinalização WebRTC das chamadas (offer/answer/ICE)
-- 2026-08-10
--
-- Âmbito: a chamada simulada (migration_20260810_chamadas.sql) já avisa e
-- deixa atender/recusar/desligar, mas sem áudio — cada lado só via o
-- estado, nunca se ouviam. Isto acrescenta a troca de SDP/ICE necessária
-- para o WebRTC (RTCPeerConnection) ligar os microfones ponto-a-ponto; o
-- servidor nunca vê nem guarda áudio, só mensagens de sinalização de texto
-- (a mesma troca que normalmente iria por WebSocket, aqui por polling,
-- para não introduzir mais nenhuma dependência).
-- Aplica-se sobre kabugo_v2 já em produção.
-- ==============================================

USE kabugo_v2;

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
