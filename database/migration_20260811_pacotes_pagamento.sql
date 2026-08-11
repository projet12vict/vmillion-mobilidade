-- V-MILLION — Pacotes de pagamento (diário/semanal/mensal/anual) para condutores.
-- Substitui a taxa fixa única (config_precos.taxa_operacao_rota, sempre 30
-- dias) por preços/duração configuráveis pelo Super Admin, e liga o
-- comprovativo diretamente ao pedido de pagamento do condutor (antes só o
-- admin conseguia anexar um comprovativo, via a tabela `comprovativos`
-- separada usada na aprovação inicial da conta).

CREATE TABLE pacotes_pagamento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL UNIQUE, -- 'diario','semanal','mensal','anual' (ou outro definido pelo Super Admin)
    descricao VARCHAR(255) NULL,
    preco DECIMAL(10,2) NOT NULL,
    duracao_dias INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pacote_criado_por FOREIGN KEY (criado_por) REFERENCES administradores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO pacotes_pagamento (nome, descricao, preco, duracao_dias) VALUES
('diario', 'Acesso por 1 dia', 100.00, 1),
('semanal', 'Acesso por 7 dias', 500.00, 7),
('mensal', 'Acesso por 30 dias', 1500.00, 30),
('anual', 'Acesso por 365 dias', 15000.00, 365);

-- pacote_id fica NULL a propósito para os pagamentos antigos (taxa fixa
-- sem pacote) — histórico preservado, nunca reescrito.
ALTER TABLE pagamentos_condutores
    ADD COLUMN pacote_id INT UNSIGNED NULL AFTER rota_id,
    ADD COLUMN comprovativo_path VARCHAR(255) NULL AFTER recibo_path,
    ADD COLUMN comprovativo_tipo ENUM('imagem','pdf') NULL AFTER comprovativo_path,
    ADD COLUMN observacao_admin TEXT NULL AFTER comprovativo_tipo,
    ADD COLUMN aprovado_em DATETIME NULL AFTER aprovado_por,
    ADD CONSTRAINT fk_pagcond_pacote FOREIGN KEY (pacote_id) REFERENCES pacotes_pagamento(id) ON DELETE SET NULL;
