-- Agro Life — Migration 019: vacinas cíclicas (reforço anual/periódico que
-- se repete sozinho, ex: antirrábica) — sem isso, cada ciclo exigia o
-- vet reaplicar a vacina de verdade só pra gerar a próxima data de novo.

ALTER TABLE RegistrosVacinas
    ADD COLUMN Ciclica TINYINT(1) NOT NULL DEFAULT 0 AFTER ProximaData;
