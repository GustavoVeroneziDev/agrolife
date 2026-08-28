-- Agro Life — corrige ícones de espécie que não foram atualizados pela
-- migration 009 (nomes acentuados como "Cão"/"Réptil" podem não bater
-- dependendo da codificação da sessão) — usa Ordem em vez de Nome, sem acento.

UPDATE Especies SET Icone = 'dog.png'    WHERE Ordem = 1;
UPDATE Especies SET Icone = 'cat.png'    WHERE Ordem = 2;
UPDATE Especies SET Icone = 'horse.png'  WHERE Ordem = 3;
UPDATE Especies SET Icone = 'cow.png'    WHERE Ordem = 4;
UPDATE Especies SET Icone = 'bird.png'   WHERE Ordem = 5;
UPDATE Especies SET Icone = 'rodent.png' WHERE Ordem = 6;
UPDATE Especies SET Icone = 'lizard.png' WHERE Ordem = 7;
UPDATE Especies SET Icone = 'paw.png'    WHERE Ordem = 9;

SELECT Nome, Icone, Ordem FROM Especies ORDER BY Ordem;
