-- Agro Life — Migration 018: vincula um agendamento ao agendamento que o
-- originou (ex: retirada de pontos agendada automaticamente ao concluir uma
-- cirurgia) — sem isso não dava pra distinguir um retorno de um agendamento
-- avulso qualquer.

ALTER TABLE Agendamentos
    ADD COLUMN FKAgendamentoOrigem VARCHAR(36) NULL AFTER FKRegistroClinico,
    ADD CONSTRAINT fk_ag_origem FOREIGN KEY (FKAgendamentoOrigem) REFERENCES Agendamentos(IDAgendamento) ON DELETE SET NULL,
    ADD INDEX idx_ag_origem (FKAgendamentoOrigem);
