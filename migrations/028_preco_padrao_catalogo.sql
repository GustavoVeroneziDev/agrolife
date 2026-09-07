-- Agro Life — Migration 028: preço padrão no catálogo de vacinas/cuidados e
-- de procedimentos.
--
-- Até aqui o "Valor" de um atendimento (migration 024) sempre abria em
-- branco ao concluir, mesmo pro mesmo procedimento pela centésima vez —
-- sem preço-base nenhum guardado em lugar algum, tinha que digitar de
-- cabeça toda vez. Opcional: quem não quiser usar preço fixo (ex: cirurgia
-- que varia por porte) deixa em branco e continua digitando na hora.

ALTER TABLE TiposVacina
    ADD COLUMN Preco DECIMAL(10,2) NULL AFTER IntervaloMeses;

ALTER TABLE TiposProcedimento
    ADD COLUMN Preco DECIMAL(10,2) NULL AFTER DuracaoPadraoMinutos;
