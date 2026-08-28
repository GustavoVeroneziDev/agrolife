-- Agro Life — Migration 011: catálogo de procedimentos específicos por tipo,
-- cada um com duração padrão pré-configurada (evita escolher duração "no
-- olho" toda vez — o vet escolhe o procedimento, a duração já vem certa,
-- mas continua editável se o caso for diferente do normal).
--
-- Durações de partida pesquisadas em fontes veterinárias gerais (consulta de
-- rotina 15-30min, castração 30-90min conforme porte, etc.) — pensadas como
-- ponto de partida ajustável pela clínica, não como padrão fixo/rígido.

CREATE TABLE IF NOT EXISTS TiposProcedimento (
    IDTipo               VARCHAR(36)  NOT NULL,
    Categoria            ENUM('cirurgia','consulta','exame','procedimento','observacao','outro') NOT NULL,
    Nome                 VARCHAR(100) NOT NULL,
    DuracaoPadraoMinutos INT          NOT NULL DEFAULT 30,
    Ordem                INT          NOT NULL DEFAULT 0,
    Ativo                TINYINT(1)   NOT NULL DEFAULT 1,
    MomentoRegistro      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDTipo),
    UNIQUE KEY uq_tipoproc_categoria_nome (Categoria, Nome),
    INDEX idx_tipoproc_categoria (Categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO TiposProcedimento (IDTipo, Categoria, Nome, DuracaoPadraoMinutos, Ordem) VALUES
(UUID(), 'consulta',     'Consulta de rotina',              30, 1),
(UUID(), 'consulta',     'Primeira consulta',               45, 2),
(UUID(), 'consulta',     'Retorno',                         20, 3),
(UUID(), 'cirurgia',     'Castração',                       60, 1),
(UUID(), 'cirurgia',     'Cirurgia de tecidos moles',       90, 2),
(UUID(), 'cirurgia',     'Cirurgia ortopédica',            120, 3),
(UUID(), 'cirurgia',     'Outra cirurgia',                  60, 4),
(UUID(), 'exame',        'Exame de sangue',                 15, 1),
(UUID(), 'exame',        'Raio-X',                          30, 2),
(UUID(), 'exame',        'Ultrassom',                       30, 3),
(UUID(), 'exame',        'Exame parasitológico',            15, 4),
(UUID(), 'exame',        'Outro exame',                     20, 5),
(UUID(), 'procedimento', 'Aplicação de medicamento',        15, 1),
(UUID(), 'procedimento', 'Curativo / troca de curativo',    20, 2),
(UUID(), 'procedimento', 'Limpeza dentária',                45, 3),
(UUID(), 'procedimento', 'Outro procedimento',              30, 4),
(UUID(), 'observacao',   'Observação pós-procedimento',     30, 1),
(UUID(), 'outro',        'Outro',                           30, 1);
