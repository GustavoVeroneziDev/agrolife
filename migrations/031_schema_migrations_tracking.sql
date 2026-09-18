-- Agro Life — Migration 031: tabela de controle de migration aplicada.
-- Gap conhecido desde o início do projeto (ver PADROES_DESENVOLVIMENTO.md,
-- seção 5 e "Gaps conhecidos" #1) — sem isso, aplicar migration em produção
-- dependia de lembrar manualmente quais já rodaram. painel/migrations.php
-- também cria essa tabela sozinho se ela não existir (evita o problema de
-- "essa própria migration precisa ter rodado pra ferramenta funcionar").

CREATE TABLE IF NOT EXISTS SchemaMigrations (
    Nome       VARCHAR(150) NOT NULL,
    AplicadaEm TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Modo       ENUM('executada', 'marcada_manualmente') NOT NULL DEFAULT 'executada',
    FKUsuario  VARCHAR(36)  NULL COMMENT 'quem aplicou/marcou, pelo painel — nulo pra registro anterior à existência dessa coluna',
    PRIMARY KEY (Nome),
    CONSTRAINT fk_schemamigrations_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
