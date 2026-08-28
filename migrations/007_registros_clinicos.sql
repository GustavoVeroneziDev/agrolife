-- Agro Life — Migration 007: registros clínicos (cirurgias, exames, observações) e anexos

CREATE TABLE IF NOT EXISTS RegistrosClinicos (
    IDRegistro      VARCHAR(36)  NOT NULL,
    FKAnimal        VARCHAR(36)  NOT NULL,
    FKVeterinario   VARCHAR(36)  NULL,
    Tipo            ENUM('cirurgia','consulta','exame','procedimento','observacao','outro') NOT NULL DEFAULT 'observacao',
    Titulo          VARCHAR(150) NOT NULL,
    Anotacoes       TEXT         NULL,
    DataRegistro    DATE         NOT NULL,
    MomentoRegistro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDRegistro),
    CONSTRAINT fk_rc_animal      FOREIGN KEY (FKAnimal)      REFERENCES Animais(IDAnimal) ON DELETE CASCADE,
    CONSTRAINT fk_rc_veterinario FOREIGN KEY (FKVeterinario) REFERENCES Usuarios(IDUsuario) ON DELETE SET NULL,
    INDEX idx_rc_animal (FKAnimal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS AnexosClinicos (
    IDAnexo        VARCHAR(36)  NOT NULL,
    FKRegistro     VARCHAR(36)  NOT NULL,
    CaminhoArquivo VARCHAR(255) NOT NULL,
    NomeOriginal   VARCHAR(255) NULL,
    MomentoUpload  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IDAnexo),
    CONSTRAINT fk_ac_registro FOREIGN KEY (FKRegistro) REFERENCES RegistrosClinicos(IDRegistro) ON DELETE CASCADE,
    INDEX idx_ac_registro (FKRegistro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
