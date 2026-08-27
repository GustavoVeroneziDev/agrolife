-- ============================================================
-- Agro Life — Migration 005: PesoKg passa a ter 3 decimais (grama)
-- ============================================================
-- DECIMAL(5,2) só ia até centésimos (10g de precisão). Vira
-- DECIMAL(6,3) — precisão de 1 grama, até 999,999 kg.

SET NAMES utf8mb4;

ALTER TABLE Animais MODIFY COLUMN PesoKg DECIMAL(6,3) NULL;
