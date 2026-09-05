-- Agro Life — Migration 022: WhatsApp passa a ser o dado essencial de
-- contato (cliente/equipe), e-mail vira opcional — muitos clientes reais
-- de clínica não checam e-mail, mas sempre têm WhatsApp. UNIQUE em Email
-- continua funcionando normalmente com múltiplos NULLs (não conflitam
-- entre si no MySQL/InnoDB).

ALTER TABLE Usuarios
    MODIFY COLUMN Email VARCHAR(150) NULL;
