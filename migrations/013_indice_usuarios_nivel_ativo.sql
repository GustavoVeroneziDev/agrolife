-- Agro Life — Migration 013: índice composto pra filtro por
-- NivelAcesso + Ativo em Usuarios, usado em pelo menos 7 lugares
-- (lista de clientes, lista de equipe, pickers de dono/veterinário em
-- agenda/registrar_vacina/registrar_clinico/animais, busca de clientes)
-- e até agora sem nenhum índice cobrindo essas colunas — todos esses
-- pontos faziam full table scan em Usuarios.

ALTER TABLE Usuarios ADD INDEX idx_usuarios_nivel_ativo (NivelAcesso, Ativo);
