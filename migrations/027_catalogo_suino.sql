-- Agro Life — Migration 027: catálogo de cuidado preventivo pra Suíno
--
-- A espécie Suíno foi adicionada na migration 016, depois do catálogo
-- (migration 010) já ter sido povoado pras demais espécies — ficou sem
-- nenhum item próprio, só o genérico sem espécie definida (Antirrábica).
-- Dados pesquisados em fontes veterinárias reais: a vacina polivalente
-- (parvovirose + leptospirose + erisipela) é a essencial mais citada pra
-- suíno no Brasil, com reforço semestral; vermifugação periódica é o
-- cuidado de rotina de controle parasitário, mesmo padrão já usado pra
-- roedor/réptil (categoria 'medicamento').

INSERT INTO TiposVacina (IDTipo, Nome, Categoria, Descricao, IntervaloMeses, FKEspecie) VALUES
(UUID(), 'Polivalente Suína (Parvovirose, Leptospirose e Erisipela)', 'vacina',
 'Vacina essencial mais recomendada pra suíno no Brasil — reforço semestral', 6,
 (SELECT IDEspecie FROM Especies WHERE Nome = 'Suíno')),
(UUID(), 'Vermifugação Periódica (Suíno)', 'medicamento',
 'Controle parasitário de rotina', 6,
 (SELECT IDEspecie FROM Especies WHERE Nome = 'Suíno'));
