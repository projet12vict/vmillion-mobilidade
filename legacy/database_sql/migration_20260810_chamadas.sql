-- ==============================================
-- V-MILLION — Migração: chamadas simuladas (passageiro <-> condutor)
-- 2026-08-10
--
-- Âmbito: a chamada simulada (aviso de "a chamar"/"chamada recebida" dentro
-- da app, com atender/recusar/desligar manuais) dependia só do socket.io
-- (realtime/server.js). Nos ambientes onde esse processo Node não está a
-- correr, o destinatário nunca via nada — o mesmo problema que já tinha
-- acontecido com o autofalante (comunicacoes_veiculo), por isso resolvido
-- da mesma forma aqui: o estado da chamada passa a ficar persistido em BD,
-- e cada painel confirma por polling (api/chamada/verificar.php), com o
-- socket.io só como atalho para latência mais baixa quando está disponível.
-- Aplica-se sobre kabugo_v2 já em produção.
-- ==============================================

USE kabugo_v2;

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
