-- Agro Life — Migration 014: reformula os níveis de acesso.
--
-- NivelAcesso vira cliente/funcionario/admin (era cliente/veterinario/
-- admin) — o nome "veterinario" não representava a pessoa certa: os
-- donos, que SÃO veterinários de formação, ficam como "admin" (acesso
-- total), e "veterinario" sobrava pra quem NÃO é veterinário formado
-- (a equipe de atendimento) — confuso.
--
-- Cargo é um campo novo, independente do nível de acesso, só pra dizer
-- a função da pessoa na clínica (Veterinário, Vendedor, Atendente,
-- Auxiliar, Outro) — usado pra filtrar quem aparece como "veterinário
-- responsável" num agendamento/vacina/registro clínico, não pra
-- controlar permissão de escrita (isso é só NivelAcesso = admin).
--
-- Quem já era 'veterinario' vira 'funcionario' por padrão (mais
-- seguro — não presume que alguém é dono) e ganha Cargo='veterinario'
-- (mantém aparecendo no picker de veterinário responsável). Promova
-- manualmente quem for dono de verdade (José, Dayvid, você) pra
-- NivelAcesso='admin' depois de rodar isso.

-- Passo 1: expande o enum temporariamente pra caber os dois nomes
ALTER TABLE Usuarios MODIFY COLUMN NivelAcesso ENUM('cliente','veterinario','funcionario','admin') NOT NULL DEFAULT 'cliente';

-- Passo 2: campo novo + migra quem já era veterinario
ALTER TABLE Usuarios ADD COLUMN Cargo VARCHAR(50) NULL AFTER NivelAcesso;
UPDATE Usuarios SET Cargo = 'veterinario' WHERE NivelAcesso = 'veterinario';
UPDATE Usuarios SET NivelAcesso = 'funcionario' WHERE NivelAcesso = 'veterinario';

-- Passo 3: ninguém mais usa 'veterinario' — remove do enum
ALTER TABLE Usuarios MODIFY COLUMN NivelAcesso ENUM('cliente','funcionario','admin') NOT NULL DEFAULT 'cliente';
