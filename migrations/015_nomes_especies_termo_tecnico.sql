-- Agro Life — Migration 015: renomeia espécies pro termo técnico/veterinário
-- correto, mesmo motivo da migration 012 (Vaca -> Bovino) — Cão/Gato/Cavalo
-- eram os únicos que ainda não seguiam o padrão "-ino" já usado em Bovino e
-- já usado nos próprios nomes de vacina (ex: "Antitetânica Equina",
-- "Complexo Respiratório Bovino" — migration 010). Ave, Roedor e Réptil já
-- são o termo correto/mais amplo, não precisam mudar.
--
-- Usa Ordem (não Nome) no WHERE — mesmo motivo das migrations 009b/012:
-- comparar string acentuada pode falhar silenciosamente dependendo da
-- codificação da sessão. IDEspecie também não serve aqui — é gerado com
-- UUID() no insert original, então difere entre local e produção.

UPDATE Especies SET Nome = 'Canino' WHERE Ordem = 1;
UPDATE Especies SET Nome = 'Felino' WHERE Ordem = 2;
UPDATE Especies SET Nome = 'Equino' WHERE Ordem = 3;

-- Duas descrições de vacina ainda diziam "cavalo(s)" em texto corrido —
-- troca por "equino(s)" pra não ficar inconsistente com a espécie ao lado
-- na mesma tela de catálogo. Casa por 'cavalo' sem acento de propósito
-- (mesma cautela de charset); REPLACE cobre singular e plural de uma vez
-- (substring de "cavalos" já contém "cavalo").
UPDATE TiposVacina SET Descricao = REPLACE(Descricao, 'cavalo', 'equino') WHERE Descricao LIKE '%cavalo%';

SELECT Nome, Ordem FROM Especies ORDER BY Ordem;
