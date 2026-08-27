-- ============================================================
-- VetSul — Migration 004: Raça e Sexo passam a ser obrigatórios
-- ============================================================
-- Seguro rodar agora — tabela Animais ainda não tem registros reais
-- em nenhum ambiente. Se algum dia houver linha com Raca/Sexo NULL,
-- essa migration falha (proposital: força corrigir o dado antes).

SET NAMES utf8mb4;

ALTER TABLE Animais MODIFY COLUMN Raca VARCHAR(100) NOT NULL;
ALTER TABLE Animais MODIFY COLUMN Sexo ENUM('macho','femea','indeterminado') NOT NULL;
