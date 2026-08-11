-- V-MILLION — Tipo de serviço por pacote de pagamento (urbano/intermunicipal/ambos).
-- Sem isto, um condutor Urbano podia escolher (e pagar) um pacote pensado
-- para Intermunicipal e vice-versa. Pacotes já existentes (criados antes
-- desta distinção existir) ficam 'ambos' via o DEFAULT — continuam válidos
-- para qualquer veículo, tal como já funcionavam; histórico preservado.

ALTER TABLE pacotes_pagamento
    ADD COLUMN tipo_servico ENUM('urbano', 'intermunicipal', 'ambos') NOT NULL DEFAULT 'ambos' AFTER nome,
    DROP INDEX nome,
    ADD UNIQUE KEY uq_pacote_tipo_nome (tipo_servico, nome);
