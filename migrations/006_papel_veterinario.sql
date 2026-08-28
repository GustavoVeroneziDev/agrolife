-- Agro Life — Migration 006: papel de veterinário

ALTER TABLE Usuarios
    MODIFY COLUMN NivelAcesso ENUM('cliente','veterinario','admin') NOT NULL DEFAULT 'cliente';

ALTER TABLE RegistrosVacinas
    ADD COLUMN FKVeterinario VARCHAR(36) NULL AFTER ProximaData;

ALTER TABLE RegistrosVacinas
    ADD CONSTRAINT fk_rv_veterinario FOREIGN KEY (FKVeterinario) REFERENCES Usuarios(IDUsuario) ON DELETE SET NULL;

ALTER TABLE RegistrosVacinas
    DROP COLUMN Veterinario;
