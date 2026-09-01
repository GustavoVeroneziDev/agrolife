-- Agro Life — Migration 020: intervalo cíclico customizável (não fixo no
-- catálogo — a pessoa escolhe "a cada X semanas/meses/anos" na hora) e
-- suporte a datas futuras planejadas manualmente (sequência), que ainda não
-- foram de fato aplicadas — por isso DataAplicacao vira opcional: NULL
-- identifica um lembrete futuro planejado, não uma dose já dada.

ALTER TABLE RegistrosVacinas
    MODIFY COLUMN DataAplicacao DATE NULL,
    ADD COLUMN IntervaloCiclicoValor   INT NULL AFTER Ciclica,
    ADD COLUMN IntervaloCiclicoUnidade ENUM('semana','mes','ano') NULL AFTER IntervaloCiclicoValor;
