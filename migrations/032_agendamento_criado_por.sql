-- Agro Life — Migration 032: quem criou o agendamento (equipe ou cliente),
-- independente do Status atual. Sem isso, um agendamento que nasceu de um
-- Pedido do cliente e depois foi remarcado pela equipe (Status volta pra
-- 'pendente' de novo, ver painel/agenda.php acao=remarcar) fica
-- indistinguível de um agendamento novo criado direto pela equipe que
-- também está pendente — hoje os dois têm exatamente o mesmo Status.
-- Nome CriadoPor (não "Origem") de propósito — a tabela já tem
-- FKAgendamentoOrigem, que é outra coisa (o agendamento anterior de uma
-- cadeia de retorno), não quem criou.

ALTER TABLE Agendamentos
    ADD COLUMN CriadoPor ENUM('equipe','cliente') NOT NULL DEFAULT 'equipe' AFTER Status;
