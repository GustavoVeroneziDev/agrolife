-- ============================================================
-- Agro Life — Migration 002: Seed de espécies e catálogo de vacinas
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO Especies (IDEspecie, Nome, Icone) VALUES
(UUID(), 'Cão',     'dog.png'),
(UUID(), 'Gato',    'cat.png'),
(UUID(), 'Ave',     'bird.png'),
(UUID(), 'Réptil',  'lizard.png'),
(UUID(), 'Roedor',  'rodent.png'),
(UUID(), 'Outro',   'paw.png')
ON DUPLICATE KEY UPDATE Icone = VALUES(Icone);

INSERT INTO TiposVacina (IDTipo, Nome, Descricao, IntervaloMeses, FKEspecie) VALUES
(UUID(), 'V10',                'Polivalente canina 10 em 1',        12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cão')),
(UUID(), 'V8',                 'Polivalente canina 8 em 1',         12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cão')),
(UUID(), 'Antirrábica',        'Raiva — obrigatória por lei',       12, NULL),
(UUID(), 'Giardia',            'Vacina contra Giardia',             12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cão')),
(UUID(), 'Gripe Canina',       'Tosse dos canis (Bordetella)',       6, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cão')),
(UUID(), 'Quádrupla Felina',   'V4 felina',                         12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Gato')),
(UUID(), 'FeLV',               'Leucemia felina',                   12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Gato')),
(UUID(), 'Leishmaniose',       'Dose única + reforço anual',        12, (SELECT IDEspecie FROM Especies WHERE Nome = 'Cão'))
ON DUPLICATE KEY UPDATE Descricao = VALUES(Descricao), IntervaloMeses = VALUES(IntervaloMeses);
-- IntervaloMeses NULL = dose única (ex: vacinas de viagem)

INSERT INTO ConfiguracoesSistema (IDConfig, Chave, Valor) VALUES
(UUID(), 'nome_clinica', 'Agro Life'),
(UUID(), 'telefone_clinica', ''),
(UUID(), 'endereco_clinica', ''),
(UUID(), 'msg_vacina_semana',
'Olá {nome_dono}! 🐾 Passando para lembrar que a vacina *{vacina}* do(a) *{nome_animal}* vence em uma semana, no dia *{data}*.

Agende um horário com antecedência para não perder a data!'),
(UUID(), 'msg_vacina_dia',
'Olá {nome_dono}! 🐾 Hoje, *{data}*, vence a vacina *{vacina}* do(a) *{nome_animal}*.

Entre em contato para agendar a aplicação o quanto antes.')
ON DUPLICATE KEY UPDATE Valor = Valor;
