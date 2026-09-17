-- Agro Life — Migration 030: "vacina" vira um Tipo de agendamento de
-- verdade, não só "procedimento" genérico (registro de vacina já cria um
-- retorno futuro na Agenda; agora dá pra também agendar uma vacina
-- diretamente pela Agenda, igual qualquer outro tipo de atendimento).

ALTER TABLE Agendamentos
    MODIFY COLUMN Tipo ENUM('cirurgia','consulta','exame','procedimento','vacina','observacao','outro') NOT NULL DEFAULT 'consulta';
