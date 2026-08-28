-- Agro Life — Migration 012: renomeia a espécie "Vaca" pra "Bovino" (termo
-- correto pra atender a espécie inteira — boi, vaca, novilho — não só a
-- fêmea adulta).
--
-- Usa Ordem em vez de Nome no WHERE — mesmo motivo da migration 009b: rodar
-- via CLI/phpMyAdmin sem forçar utf8mb4 pode fazer uma comparação por nome
-- falhar silenciosamente. "Vaca" não tem acento, mas mantém o padrão seguro
-- mesmo assim. Rode com charset UTF-8 selecionado no phpMyAdmin.

UPDATE Especies SET Nome = 'Bovino' WHERE Ordem = 4;
