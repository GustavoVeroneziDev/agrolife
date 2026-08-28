-- Agro Life — Migration 010: categoria de cuidado (vacina/medicamento) e
-- catálogo pras espécies que ainda não tinham nada (cavalo, vaca, ave,
-- roedor, réptil) — dados pesquisados em fontes veterinárias reais.
--
-- Ave, roedor e réptil não têm vacina padrão de rotina como cão/gato/
-- cavalo/vaca — pra esses, o cuidado preventivo recorrente de verdade é
-- exame parasitológico/vermífugo periódico, por isso a categoria
-- 'medicamento' além de 'vacina'.

ALTER TABLE TiposVacina
    ADD COLUMN Categoria ENUM('vacina','medicamento') NOT NULL DEFAULT 'vacina' AFTER Nome;

INSERT INTO TiposVacina (IDTipo, Nome, Categoria, Descricao, IntervaloMeses, FKEspecie) VALUES
-- Cavalo — núcleo de vacinas essenciais (AAEP)
(UUID(), 'Antitetânica Equina',                  'vacina', 'Tétano — uma das vacinas essenciais do cavalo', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cavalo')),
(UUID(), 'Encefalomielite Equina (EEE/WEE)',      'vacina', 'Encefalomielite equina do leste e do oeste', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cavalo')),
(UUID(), 'Febre do Nilo Ocidental (Equina)',      'vacina', 'West Nile Virus — essencial em regiões de risco', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cavalo')),
(UUID(), 'Influenza Equina',                      'vacina', 'Gripe equina — recomendada pra cavalos com contato com outros animais', 6, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cavalo')),

-- Vaca — protocolo básico de rebanho
(UUID(), 'Clostridiose (Manqueira)',              'vacina', 'Vacina polivalente clostridial', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Vaca')),
(UUID(), 'Complexo Respiratório Bovino (IBR/BVD)', 'vacina', 'IBR, BVD, PI3 e BRSV', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Vaca')),
(UUID(), 'Brucelose (Bezerras)',                  'vacina', 'Dose única obrigatória em bezerras de 4 a 12 meses', NULL, (SELECT IDEspecie FROM Especies WHERE Nome = 'Vaca')),
(UUID(), 'Leptospirose Bovina',                   'vacina', NULL, 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Vaca')),

-- Ave — sem vacina padrão pra ave de estimação; o de rotina é acompanhamento
(UUID(), 'Avaliação Clínica e Parasitológica (Ave)', 'medicamento', 'Aves não têm vacina de rotina — check-up + exame de fezes é o cuidado preventivo padrão', 12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Ave')),

-- Roedor — sem vacina padrão; controle parasitário é o cuidado de rotina
(UUID(), 'Vermífugo Periódico (Roedor)',          'medicamento', 'Roedores não têm vacina de rotina — controle parasitário periódico é o cuidado padrão', 6, (SELECT IDEspecie FROM Especies WHERE Nome = 'Roedor')),

-- Réptil — sem vacina; exame de fezes periódico é o padrão
(UUID(), 'Exame Parasitológico de Fezes (Réptil)', 'medicamento', 'Répteis não têm vacina — exame de fezes periódico é o padrão de prevenção', 6, (SELECT IDEspecie FROM Especies WHERE Nome = 'Réptil'));
