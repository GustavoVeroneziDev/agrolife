-- Agro Life — Migration 016: adiciona a espécie Suíno (porco), na posição
-- 8 — que já estava livre entre Réptil (7) e Outro (9). Ícone (pig.png)
-- sobe separado, direto em assets/img/especies/ (mesmo padrão dos outros).

INSERT INTO Especies (IDEspecie, Nome, Icone, Ordem) VALUES
(UUID(), 'Suíno', 'pig.png', 8);

SELECT Nome, Icone, Ordem FROM Especies ORDER BY Ordem;
