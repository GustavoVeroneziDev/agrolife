-- Agro Life — Migration 009: ícones de espécie viram arquivo PNG em vez de emoji

ALTER TABLE Especies MODIFY COLUMN Icone VARCHAR(50) DEFAULT NULL;

UPDATE Especies SET Icone = 'dog.png'    WHERE Nome = 'Cão';
UPDATE Especies SET Icone = 'cat.png'    WHERE Nome = 'Gato';
UPDATE Especies SET Icone = 'horse.png'  WHERE Nome = 'Cavalo';
UPDATE Especies SET Icone = 'cow.png'    WHERE Nome = 'Vaca';
UPDATE Especies SET Icone = 'bird.png'   WHERE Nome = 'Ave';
UPDATE Especies SET Icone = 'rodent.png' WHERE Nome = 'Roedor';
UPDATE Especies SET Icone = 'lizard.png' WHERE Nome = 'Réptil';
UPDATE Especies SET Icone = 'paw.png'    WHERE Nome = 'Outro';
