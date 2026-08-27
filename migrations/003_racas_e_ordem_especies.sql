-- ============================================================
-- VetSul — Migration 003: ordena espécies por comum, adiciona
-- Vaca/Cavalo, cria catálogo de raças por espécie
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Espécies já existentes (001/002) não têm coluna Ordem ainda.
-- "IF NOT EXISTS" em ADD COLUMN é extensão do MariaDB — não roda no
-- MySQL puro (ex: HostGator). Sintaxe simples, roda uma vez só.
-- ------------------------------------------------------------
ALTER TABLE Especies ADD COLUMN Ordem INT NOT NULL DEFAULT 0
    COMMENT 'ordem de exibicao — mais comum primeiro, nao alfabetica';

CREATE TABLE IF NOT EXISTS Racas (
    IDRaca    VARCHAR(36)  NOT NULL,
    FKEspecie VARCHAR(36)  NOT NULL,
    Nome      VARCHAR(100) NOT NULL,
    Ordem     INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (IDRaca),
    UNIQUE KEY uq_raca_especie_nome (FKEspecie, Nome),
    CONSTRAINT fk_raca_especie FOREIGN KEY (FKEspecie) REFERENCES Especies(IDEspecie) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Reordena as espécies existentes por comum (cão/gato à frente;
-- cavalo/vaca logo depois — clínica atende porte grande também)
-- ------------------------------------------------------------
UPDATE Especies SET Ordem = 1 WHERE Nome = 'Cão';
UPDATE Especies SET Ordem = 2 WHERE Nome = 'Gato';
UPDATE Especies SET Ordem = 5 WHERE Nome = 'Ave';
UPDATE Especies SET Ordem = 6 WHERE Nome = 'Roedor';
UPDATE Especies SET Ordem = 7 WHERE Nome = 'Réptil';
UPDATE Especies SET Ordem = 9 WHERE Nome = 'Outro';

INSERT INTO Especies (IDEspecie, Nome, Icone, Ordem) VALUES
(UUID(), 'Cavalo', '🐴', 3),
(UUID(), 'Vaca',   '🐄', 4)
ON DUPLICATE KEY UPDATE Icone = VALUES(Icone), Ordem = VALUES(Ordem);

-- ------------------------------------------------------------
-- Catálogo de raças — sempre com "Sem Raça Definida" primeiro
-- (SRD/vira-lata é a opção mais comum na prática de clínica)
-- ------------------------------------------------------------
INSERT INTO Racas (IDRaca, FKEspecie, Nome, Ordem)
SELECT UUID(), e.IDEspecie, r.Nome, r.Ordem
FROM (
    SELECT 'Cão' AS Especie, 'Sem Raça Definida (SRD)' AS Nome, 0 AS Ordem UNION ALL
    SELECT 'Cão', 'Labrador Retriever', 1 UNION ALL
    SELECT 'Cão', 'Golden Retriever', 2 UNION ALL
    SELECT 'Cão', 'Poodle', 3 UNION ALL
    SELECT 'Cão', 'Bulldog Francês', 4 UNION ALL
    SELECT 'Cão', 'Pastor Alemão', 5 UNION ALL
    SELECT 'Cão', 'Shih Tzu', 6 UNION ALL
    SELECT 'Cão', 'Yorkshire Terrier', 7 UNION ALL
    SELECT 'Cão', 'Rottweiler', 8 UNION ALL
    SELECT 'Cão', 'Border Collie', 9 UNION ALL
    SELECT 'Cão', 'Chihuahua', 10 UNION ALL
    SELECT 'Cão', 'Dachshund (Salsicha)', 11 UNION ALL
    SELECT 'Cão', 'Pinscher', 12 UNION ALL
    SELECT 'Cão', 'Beagle', 13 UNION ALL
    SELECT 'Cão', 'Boxer', 14 UNION ALL
    SELECT 'Cão', 'Husky Siberiano', 15 UNION ALL
    SELECT 'Cão', 'Lhasa Apso', 16 UNION ALL
    SELECT 'Cão', 'Maltês', 17 UNION ALL
    SELECT 'Cão', 'Pug', 18 UNION ALL
    SELECT 'Cão', 'Akita', 19 UNION ALL
    SELECT 'Cão', 'Cocker Spaniel', 20 UNION ALL
    SELECT 'Cão', 'Dálmata', 21 UNION ALL
    SELECT 'Cão', 'Doberman', 22 UNION ALL
    SELECT 'Cão', 'Fox Paulistinha', 23 UNION ALL
    SELECT 'Cão', 'Basset Hound', 24 UNION ALL

    SELECT 'Gato', 'Sem Raça Definida (SRD)', 0 UNION ALL
    SELECT 'Gato', 'Siamês', 1 UNION ALL
    SELECT 'Gato', 'Persa', 2 UNION ALL
    SELECT 'Gato', 'Maine Coon', 3 UNION ALL
    SELECT 'Gato', 'Angorá', 4 UNION ALL
    SELECT 'Gato', 'Sphynx', 5 UNION ALL
    SELECT 'Gato', 'Ragdoll', 6 UNION ALL
    SELECT 'Gato', 'Bengal', 7 UNION ALL
    SELECT 'Gato', 'British Shorthair', 8 UNION ALL
    SELECT 'Gato', 'Munchkin', 9 UNION ALL

    SELECT 'Cavalo', 'Sem Raça Definida (SRD)', 0 UNION ALL
    SELECT 'Cavalo', 'Quarto de Milha', 1 UNION ALL
    SELECT 'Cavalo', 'Mangalarga Marchador', 2 UNION ALL
    SELECT 'Cavalo', 'Crioulo', 3 UNION ALL
    SELECT 'Cavalo', 'Puro Sangue Inglês', 4 UNION ALL
    SELECT 'Cavalo', 'Andaluz', 5 UNION ALL
    SELECT 'Cavalo', 'Appaloosa', 6 UNION ALL
    SELECT 'Cavalo', 'Árabe', 7 UNION ALL
    SELECT 'Cavalo', 'Campolina', 8 UNION ALL
    SELECT 'Cavalo', 'Brasileiro de Hipismo', 9 UNION ALL

    SELECT 'Vaca', 'Sem Raça Definida (SRD)', 0 UNION ALL
    SELECT 'Vaca', 'Nelore', 1 UNION ALL
    SELECT 'Vaca', 'Holandesa', 2 UNION ALL
    SELECT 'Vaca', 'Gir', 3 UNION ALL
    SELECT 'Vaca', 'Jersey', 4 UNION ALL
    SELECT 'Vaca', 'Angus', 5 UNION ALL
    SELECT 'Vaca', 'Girolando', 6 UNION ALL
    SELECT 'Vaca', 'Brahman', 7 UNION ALL
    SELECT 'Vaca', 'Simental', 8 UNION ALL
    SELECT 'Vaca', 'Guzerá', 9 UNION ALL

    SELECT 'Ave', 'Sem Raça Definida', 0 UNION ALL
    SELECT 'Ave', 'Calopsita', 1 UNION ALL
    SELECT 'Ave', 'Periquito Australiano', 2 UNION ALL
    SELECT 'Ave', 'Canário', 3 UNION ALL
    SELECT 'Ave', 'Papagaio Verdadeiro', 4 UNION ALL
    SELECT 'Ave', 'Agapornis', 5 UNION ALL
    SELECT 'Ave', 'Cacatua', 6 UNION ALL
    SELECT 'Ave', 'Arara', 7 UNION ALL

    SELECT 'Roedor', 'Sem Raça Definida', 0 UNION ALL
    SELECT 'Roedor', 'Hamster Sírio', 1 UNION ALL
    SELECT 'Roedor', 'Hamster Anão Russo', 2 UNION ALL
    SELECT 'Roedor', 'Porquinho-da-índia', 3 UNION ALL
    SELECT 'Roedor', 'Chinchila', 4 UNION ALL
    SELECT 'Roedor', 'Coelho', 5 UNION ALL
    SELECT 'Roedor', 'Rato/Camundongo', 6 UNION ALL

    SELECT 'Réptil', 'Sem Raça Definida', 0 UNION ALL
    SELECT 'Réptil', 'Jabuti', 1 UNION ALL
    SELECT 'Réptil', 'Iguana', 2 UNION ALL
    SELECT 'Réptil', 'Jibóia', 3 UNION ALL
    SELECT 'Réptil', 'Corn Snake', 4 UNION ALL
    SELECT 'Réptil', 'Gecko Leopardo', 5 UNION ALL
    SELECT 'Réptil', 'Cágado', 6 UNION ALL

    SELECT 'Outro', 'Sem Raça Definida / Não especificado', 0
) r
JOIN Especies e ON e.Nome = r.Especie
ON DUPLICATE KEY UPDATE Ordem = VALUES(Ordem);
