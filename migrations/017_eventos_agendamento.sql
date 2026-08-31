-- Agro Life — Migration 017: log de eventos do agendamento (criado,
-- confirmado, remarcado, faltou, cancelado, concluído, reaberto) — vira o
-- "Histórico de movimentações" no card do animal. Sem isso, remarcar
-- sobrescreve a data antiga sem deixar rastro nenhum de quando/pra onde
-- foi trocado.

CREATE TABLE IF NOT EXISTS EventosAgendamento (
    IDEvento       VARCHAR(36) NOT NULL PRIMARY KEY,
    FKAgendamento  VARCHAR(36) NOT NULL,
    FKUsuario      VARCHAR(36) NULL,
    Tipo           ENUM('criado','confirmado','remarcado','faltou','cancelado','concluido','reaberto') NOT NULL,
    Detalhes       VARCHAR(255) NULL,
    MomentoEvento  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_agendamento FOREIGN KEY (FKAgendamento) REFERENCES Agendamentos(IDAgendamento) ON DELETE CASCADE,
    CONSTRAINT fk_evento_usuario FOREIGN KEY (FKUsuario) REFERENCES Usuarios(IDUsuario) ON DELETE SET NULL,
    INDEX idx_evento_agendamento (FKAgendamento),
    INDEX idx_evento_momento (MomentoEvento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
