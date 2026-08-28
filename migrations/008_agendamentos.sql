-- Agro Life — Migration 008: agendamento de procedimentos/consultas

CREATE TABLE IF NOT EXISTS Agendamentos (
    IDAgendamento     VARCHAR(36)  NOT NULL,
    FKAnimal          VARCHAR(36)  NOT NULL,
    FKVeterinario     VARCHAR(36)  NULL,
    FKRegistroClinico VARCHAR(36)  NULL,
    Tipo              ENUM('cirurgia','consulta','exame','procedimento','observacao','outro') NOT NULL DEFAULT 'consulta',
    Titulo            VARCHAR(150) NOT NULL,
    DataHoraInicio    DATETIME     NOT NULL,
    DataHoraFim       DATETIME     NOT NULL,
    Status            ENUM('pendente','confirmado','concluido','cancelado','faltou') NOT NULL DEFAULT 'pendente',
    Observacoes       TEXT         NULL,
    ObservacoesPos    TEXT         NULL,
    MomentoRegistro   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AtualizadoEm      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (IDAgendamento),
    CONSTRAINT fk_ag_animal      FOREIGN KEY (FKAnimal)          REFERENCES Animais(IDAnimal)              ON DELETE CASCADE,
    CONSTRAINT fk_ag_veterinario FOREIGN KEY (FKVeterinario)     REFERENCES Usuarios(IDUsuario)             ON DELETE SET NULL,
    CONSTRAINT fk_ag_clinico     FOREIGN KEY (FKRegistroClinico) REFERENCES RegistrosClinicos(IDRegistro)   ON DELETE SET NULL,
    INDEX idx_ag_animal    (FKAnimal),
    INDEX idx_ag_vet_data  (FKVeterinario, DataHoraInicio),
    INDEX idx_ag_data      (DataHoraInicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
