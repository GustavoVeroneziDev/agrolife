-- Agro Life — Migration 029: lembrete automático de WhatsApp pra atendimento
-- agendado (cron, mesma ideia do lembrete de vacina que já existia, só que
-- pra Agendamentos) + auditoria desse envio em LogsWhatsApp.

ALTER TABLE Agendamentos
    ADD COLUMN NotificacaoLembreteEnviada TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'flag: lembrete de atendimento já enviado pro agendamento atual'
        AFTER StatusPagamento;

-- LogsWhatsApp até aqui só registrava envio de vacina (FKRegistroVacina
-- aponta só pra RegistrosVacinas). Em vez de generalizar essa coluna pra um
-- FK "genérico" sem integridade referencial de verdade, adiciona uma coluna
-- própria pra Agendamentos — mesmo padrão de FK nomeado e com constraint que
-- o resto do banco já usa.
ALTER TABLE LogsWhatsApp
    MODIFY COLUMN TipoMensagem ENUM('vacina_semana','vacina_dia','agendamento_lembrete','manual') NOT NULL DEFAULT 'manual',
    ADD COLUMN FKAgendamento VARCHAR(36) NULL AFTER FKRegistroVacina,
    ADD CONSTRAINT fk_log_agendamento FOREIGN KEY (FKAgendamento) REFERENCES Agendamentos(IDAgendamento) ON DELETE SET NULL;
