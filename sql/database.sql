-- ============================================================================
-- BiblioTech - Database Initialization Script
-- Sistema di Gestione Prestiti Librari Scolastici
-- ============================================================================

-- Elimina database se esiste (solo per sviluppo)
DROP DATABASE IF EXISTS biblioTech;

-- Crea database
CREATE DATABASE biblioTech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblioTech;

-- ============================================================================
-- TABELLA: utenti
-- Gestisce gli utenti del sistema (studenti e bibliotecari)
-- ============================================================================
CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt della password',
    ruolo ENUM('studente', 'bibliotecario') NOT NULL DEFAULT 'studente',
    totp_secret VARCHAR(255) DEFAULT NULL COMMENT 'Chiave segreta TOTP per 2FA',
    totp_attivo BOOLEAN DEFAULT FALSE COMMENT 'Indica se 2FA è attivo',
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_ruolo (ruolo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLA: libri
-- Catalogo dei libri disponibili nella biblioteca
-- ============================================================================
CREATE TABLE libri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(500) NOT NULL,
    autore VARCHAR(255) NOT NULL,
    isbn VARCHAR(20) DEFAULT NULL,
    copie_totali INT NOT NULL DEFAULT 1 CHECK (copie_totali >= 0),
    copie_disponibili INT NOT NULL DEFAULT 1 CHECK (copie_disponibili >= 0),
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_titolo (titolo),
    INDEX idx_autore (autore),
    INDEX idx_disponibilita (copie_disponibili),
    CONSTRAINT chk_copie CHECK (copie_disponibili <= copie_totali)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLA: prestiti
-- Registro di tutti i prestiti (attivi e storici)
-- ============================================================================
CREATE TABLE prestiti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    libro_id INT NOT NULL,
    data_prestito TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_restituzione TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE RESTRICT,
    FOREIGN KEY (libro_id) REFERENCES libri(id) ON DELETE RESTRICT,
    INDEX idx_utente (id_utente),
    INDEX idx_libro (libro_id),
    INDEX idx_attivi (data_restituzione),
    INDEX idx_data_prestito (data_prestito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLA: password_reset_tokens
-- Token per recupero password (monouso con scadenza)
-- ============================================================================
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE COMMENT 'Indica se il token è già stato utilizzato',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_utente (id_utente),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLA: sessioni (opzionale - per tracking avanzato)
-- Registro delle sessioni utente
-- ============================================================================
CREATE TABLE sessioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    data_login TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_logout TIMESTAMP NULL DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    autenticazione_metodo ENUM('password', '2fa') DEFAULT 'password',
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_utente (id_utente),
    INDEX idx_token (session_token),
    INDEX idx_attive (data_logout)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATI DI ESEMPIO
-- ============================================================================

-- Inserimento utenti di esempio
-- Password per tutti: "password123"
INSERT INTO utenti (email, password, ruolo, totp_secret, totp_attivo) VALUES
('bibliotecario@scuola.it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bibliotecario', NULL, FALSE),
('studente1@scuola.it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'studente', NULL, FALSE),
('studente2@scuola.it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'studente', NULL, FALSE),
('mario.rossi@scuola.it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'studente', NULL, FALSE);

-- Inserimento libri di esempio
INSERT INTO libri (titolo, autore, isbn, copie_totali, copie_disponibili) VALUES
('Il Signore degli Anelli', 'J.R.R. Tolkien', '978-0261102385', 5, 5),
('1984', 'George Orwell', '978-0451524935', 3, 3),
('Il Piccolo Principe', 'Antoine de Saint-Exupéry', '978-0156012195', 4, 4),
('Harry Potter e la Pietra Filosofale', 'J.K. Rowling', '978-0439708180', 6, 6),
('Orgoglio e Pregiudizio', 'Jane Austen', '978-0141439518', 2, 2),
('Il Nome della Rosa', 'Umberto Eco', '978-0156001311', 3, 3),
('La Divina Commedia', 'Dante Alighieri', '978-0142437223', 5, 5),
('Il Codice da Vinci', 'Dan Brown', '978-0307474278', 4, 4),
('Le Cronache di Narnia', 'C.S. Lewis', '978-0066238500', 3, 3),
('I Promessi Sposi', 'Alessandro Manzoni', '978-8817126717', 10, 10);

-- Inserimento prestiti di esempio (alcuni attivi, alcuni conclusi)
INSERT INTO prestiti (id_utente, libro_id, data_prestito, data_restituzione) VALUES
(2, 1, '2025-02-01 10:00:00', NULL),  -- Prestito attivo
(2, 3, '2025-01-15 14:30:00', '2025-02-10 09:15:00'),  -- Prestito concluso
(3, 2, '2025-02-05 11:20:00', NULL),  -- Prestito attivo
(4, 4, '2025-01-20 16:45:00', '2025-02-08 10:30:00'),  -- Prestito concluso
(3, 5, '2025-02-10 08:00:00', NULL);  -- Prestito attivo

-- Aggiorna copie_disponibili in base ai prestiti attivi
UPDATE libri l SET copie_disponibili = copie_totali - (
    SELECT COUNT(*) FROM prestiti p 
    WHERE p.libro_id = l.id AND p.data_restituzione IS NULL
);

-- ============================================================================
-- TRIGGER: Verifica vincoli copie prima dell'inserimento prestito
-- ============================================================================
DELIMITER $$

CREATE TRIGGER before_prestito_insert
BEFORE INSERT ON prestiti
FOR EACH ROW
BEGIN
    DECLARE disponibili INT;
    
    -- Verifica copie disponibili
    SELECT copie_disponibili INTO disponibili
    FROM libri
    WHERE id = NEW.libro_id;
    
    IF disponibili <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Nessuna copia disponibile per questo libro';
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURE: Pulizia token scaduti
-- ============================================================================
DELIMITER $$

CREATE PROCEDURE cleanup_expired_tokens()
BEGIN
    DELETE FROM password_reset_tokens 
    WHERE expires_at < NOW() OR used = TRUE;
END$$

DELIMITER ;

-- ============================================================================
-- VIEW: Prestiti attivi con dettagli
-- ============================================================================
CREATE VIEW v_prestiti_attivi AS
SELECT 
    p.id AS prestito_id,
    p.data_prestito,
    u.id AS id_utente,
    u.email AS studente_email,
    u.ruolo,
    l.id AS libro_id,
    l.titolo,
    l.autore,
    l.isbn,
    DATEDIFF(NOW(), p.data_prestito) AS giorni_prestito
FROM prestiti p
JOIN utenti u ON p.id_utente = u.id
JOIN libri l ON p.libro_id = l.id
WHERE p.data_restituzione IS NULL
ORDER BY p.data_prestito DESC;

-- ============================================================================
-- VIEW: Statistiche biblioteca
-- ============================================================================
CREATE VIEW v_statistiche_biblioteca AS
SELECT 
    (SELECT COUNT(*) FROM libri) AS totale_titoli,
    (SELECT SUM(copie_totali) FROM libri) AS totale_copie,
    (SELECT SUM(copie_disponibili) FROM libri) AS copie_disponibili,
    (SELECT COUNT(*) FROM prestiti WHERE data_restituzione IS NULL) AS prestiti_attivi,
    (SELECT COUNT(*) FROM prestiti) AS prestiti_totali,
    (SELECT COUNT(*) FROM utenti WHERE ruolo = 'studente') AS totale_studenti,
    (SELECT COUNT(*) FROM utenti WHERE ruolo = 'bibliotecario') AS totale_bibliotecari;

-- ============================================================================
-- UTENTE DATABASE (opzionale - per produzione)
-- ============================================================================
-- CREATE USER 'bibliotech_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON biblioTech.* TO 'bibliotech_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================================================
-- VERIFICA INIZIALIZZAZIONE
-- ============================================================================
SELECT 'Database biblioTech inizializzato con successo!' AS Status;
SELECT * FROM v_statistiche_biblioteca;