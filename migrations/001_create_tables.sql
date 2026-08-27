-- ============================================================
-- VetSul — Migration 001: Criação das tabelas
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ------------------------------------------------------------
-- Usuarios (donos + equipe da clínica)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Usuarios (
    IDUsuario       VARCHAR(36)  NOT NULL,
    Nome            VARCHAR(100) NOT NULL,
    Email           VARCHAR(150) NOT NULL,
    Telefone        VARCHAR(20)  NULL,
    Senha           VARCHAR(255) NOT NULL,
    NivelAcesso     ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    Ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    MomentoRegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AtualizadoEm    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IDUsuario),
    UNIQUE KEY uq_email (Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TokensLembrarMe (remember-me)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS TokensLembrarMe (
    IDToken   VARCHAR(36) NOT NULL,
    FKUsuario VARCHAR(36) NOT NULL,
    TokenHash VARCHAR(64) NOT NULL COMMENT 'SHA-256 do token plain; nunca armazena o token em si',
    Expira    DATETIME    NOT NULL,
    CriadoEm  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDToken),
    CONSTRAINT fk_lembrar_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE CASCADE,
    INDEX idx_lembrar_usuario (FKUsuario),
    INDEX idx_lembrar_expira  (Expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TokensVerificacaoEmail (reservado para uso futuro)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS TokensVerificacaoEmail (
    IDToken   VARCHAR(36) NOT NULL,
    FKUsuario VARCHAR(36) NOT NULL,
    TokenHash VARCHAR(64) NOT NULL,
    Expira    DATETIME    NOT NULL,
    CriadoEm  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDToken),
    CONSTRAINT fk_verif_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE CASCADE,
    INDEX idx_verif_usuario (FKUsuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TokensResetSenha
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS TokensResetSenha (
    IDToken   VARCHAR(36) NOT NULL,
    FKUsuario VARCHAR(36) NOT NULL,
    TokenHash VARCHAR(64) NOT NULL COMMENT 'SHA-256 do token plain',
    Expira    DATETIME    NOT NULL,
    CriadoEm  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDToken),
    CONSTRAINT fk_reset_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE CASCADE,
    INDEX idx_reset_usuario (FKUsuario),
    INDEX idx_reset_expira  (Expira)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ConfiguracoesSistema (chave/valor)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ConfiguracoesSistema (
    IDConfig        VARCHAR(36)  NOT NULL,
    Chave           VARCHAR(100) NOT NULL,
    Valor           TEXT         NULL,
    MomentoRegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AtualizadoEm    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IDConfig),
    UNIQUE KEY uq_chave (Chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Especies (seed fixo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Especies (
    IDEspecie VARCHAR(36) NOT NULL,
    Nome      VARCHAR(50) NOT NULL,
    Icone     VARCHAR(10) NULL,
    Ordem     INT         NOT NULL DEFAULT 0 COMMENT 'ordem de exibicao — mais comum primeiro, nao alfabetica',
    PRIMARY KEY (IDEspecie),
    UNIQUE KEY uq_especie_nome (Nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Racas (catalogo por especie — evita nome de raça variando)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Racas (
    IDRaca    VARCHAR(36)  NOT NULL,
    FKEspecie VARCHAR(36)  NOT NULL,
    Nome      VARCHAR(100) NOT NULL,
    Ordem     INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (IDRaca),
    UNIQUE KEY uq_raca_especie_nome (FKEspecie, Nome),
    CONSTRAINT fk_raca_especie FOREIGN KEY (FKEspecie) REFERENCES Especies(IDEspecie) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Animais
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Animais (
    IDAnimal        VARCHAR(36)   NOT NULL,
    FKDono          VARCHAR(36)   NOT NULL,
    FKEspecie       VARCHAR(36)   NOT NULL,
    Nome            VARCHAR(100)  NOT NULL,
    Raca            VARCHAR(100)  NOT NULL,
    DataNascimento  DATE          NULL,
    Sexo            ENUM('macho','femea','indeterminado') NOT NULL,
    Pelagem         VARCHAR(100)  NULL,
    PesoKg          DECIMAL(5,2)  NULL,
    Microchip       VARCHAR(50)   NULL,
    Observacoes     TEXT          NULL,
    FotoUrl         VARCHAR(500)  NULL,
    Ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    MomentoRegistro TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AtualizadoEm    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IDAnimal),
    CONSTRAINT fk_animal_dono    FOREIGN KEY (FKDono)    REFERENCES Usuarios(IDUsuario),
    CONSTRAINT fk_animal_especie FOREIGN KEY (FKEspecie) REFERENCES Especies(IDEspecie),
    INDEX idx_animal_dono (FKDono)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TiposVacina (catálogo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS TiposVacina (
    IDTipo          VARCHAR(36)  NOT NULL,
    Nome            VARCHAR(100) NOT NULL,
    Descricao       TEXT         NULL,
    IntervaloMeses  INT          NULL COMMENT 'NULL = dose única sem reforço',
    FKEspecie       VARCHAR(36)  NULL COMMENT 'NULL = aplicável a todas as espécies',
    Ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    MomentoRegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDTipo),
    UNIQUE KEY uq_tipovacina_nome (Nome),
    CONSTRAINT fk_tv_especie FOREIGN KEY (FKEspecie) REFERENCES Especies(IDEspecie) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- RegistrosVacinas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS RegistrosVacinas (
    IDRegistro               VARCHAR(36) NOT NULL,
    FKAnimal                 VARCHAR(36) NOT NULL,
    FKTipoVacina              VARCHAR(36) NOT NULL,
    DataAplicacao             DATE        NOT NULL,
    ProximaData               DATE        NULL COMMENT 'calculada: DataAplicacao + IntervaloMeses',
    Veterinario               VARCHAR(100) NULL,
    Lote                      VARCHAR(50) NULL,
    Observacoes               TEXT        NULL,
    NotificacaoSemanaEnviada  TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'flag: 7 dias antes',
    NotificacaoDiaEnviada     TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'flag: no dia',
    MomentoRegistro           TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDRegistro),
    CONSTRAINT fk_rv_animal FOREIGN KEY (FKAnimal)     REFERENCES Animais(IDAnimal) ON DELETE CASCADE,
    CONSTRAINT fk_rv_tipo   FOREIGN KEY (FKTipoVacina) REFERENCES TiposVacina(IDTipo),
    INDEX idx_rv_animal (FKAnimal),
    INDEX idx_rv_proxima (ProximaData)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LogsWhatsApp
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS LogsWhatsApp (
    IDLog            VARCHAR(36) NOT NULL,
    FKRegistroVacina VARCHAR(36) NULL,
    Numero           VARCHAR(20) NOT NULL,
    Mensagem         TEXT        NOT NULL,
    TipoMensagem     ENUM('vacina_semana','vacina_dia','manual') NOT NULL DEFAULT 'manual',
    StatusEnvio      ENUM('pendente','enviado','erro') NOT NULL DEFAULT 'pendente',
    MomentoRegistro  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDLog),
    CONSTRAINT fk_log_regvacina FOREIGN KEY (FKRegistroVacina) REFERENCES RegistrosVacinas(IDRegistro) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
