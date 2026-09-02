-- Agro Life — Migration 021: vincula uma vacina planejada (data futura, sem
-- aplicação ainda) a um compromisso real na Agenda — sem isso, uma vacina
-- "planejada" só existia na carteirinha, o vet não via nada no calendário
-- pra lembrar de atender naquele dia.

ALTER TABLE RegistrosVacinas
    ADD COLUMN FKAgendamento VARCHAR(36) NULL AFTER FKTipoVacina,
    ADD CONSTRAINT fk_rv_agendamento FOREIGN KEY (FKAgendamento) REFERENCES Agendamentos(IDAgendamento) ON DELETE SET NULL,
    ADD INDEX idx_rv_agendamento (FKAgendamento);
