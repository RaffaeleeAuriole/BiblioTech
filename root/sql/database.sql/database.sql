-- ============================================================================
-- BiblioTech - Database Schema e Dati di Esempio
-- Sistema informativo per la gestione informatizzata dei prestiti librari
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================================================
-- CREAZIONE TABELLE
-- ============================================================================

-- Tabella UTENTI
-- Gestisce gli utenti del sistema (studenti e bibliotecari)
-- Include supporto per autenticazione a due fattori (TOTP)
CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL COMMENT 'Hash BCrypt della password',
    ruolo ENUM('studente','bibliotecario') NOT NULL,
    totp_secret VARCHAR(32) NOT NULL COMMENT 'Chiave segreta TOTP per 2FA',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Account attivo/disabilitato',
    failed_attempts INT NOT NULL DEFAULT 0 COMMENT 'Tentativi di login falliti',
    last_failed_login DATETIME NULL COMMENT 'Timestamp ultimo tentativo fallito',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ruolo (ruolo),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Utenti del sistema con supporto 2FA';

-- Tabella LIBRI
-- Catalogo dei libri disponibili nella biblioteca
CREATE TABLE libri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(255) NOT NULL,
    autore VARCHAR(255) NOT NULL,
    copie_totali INT NOT NULL CHECK (copie_totali >= 0),
    copie_disponibili INT NOT NULL CHECK (copie_disponibili >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_copie_coerenza CHECK (copie_disponibili <= copie_totali),
    INDEX idx_titolo (titolo),
    INDEX idx_autore (autore),
    INDEX idx_disponibilita (copie_disponibili)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catalogo libri con tracciamento copie';

-- Tabella PRESTITI
-- Tracciamento prestiti libri (attivi e storici)
CREATE TABLE prestiti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    id_libro INT NOT NULL,
    data_prestito DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_restituzione DATETIME NULL COMMENT 'NULL = prestito attivo',
    quantita INT NOT NULL DEFAULT 1 CHECK (quantita > 0),
    CONSTRAINT fk_prestito_utente FOREIGN KEY (id_utente) 
        REFERENCES utenti(id) ON DELETE CASCADE,
    CONSTRAINT fk_prestito_libro FOREIGN KEY (id_libro) 
        REFERENCES libri(id) ON DELETE CASCADE,
    INDEX idx_utente (id_utente),
    INDEX idx_libro (id_libro),
    INDEX idx_data_prestito (data_prestito),
    INDEX idx_attivo (data_restituzione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Prestiti libri con storico completo';

-- Tabella SESSIONI
-- Tracciamento sessioni utente per audit e sicurezza
CREATE TABLE sessioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    session_token VARCHAR(128) NOT NULL UNIQUE COMMENT 'Token univoco sessione',
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    ip_address VARCHAR(45) COMMENT 'IPv4 o IPv6',
    user_agent TEXT COMMENT 'Browser/client info',
    CONSTRAINT fk_sessione_utente FOREIGN KEY (id_utente) 
        REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_utente_sessione (id_utente),
    INDEX idx_session_token (session_token),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sessioni utente per audit e sicurezza';

-- Tabella PASSWORD_RESET_TOKENS
-- Token per recupero password via email
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE COMMENT 'Token univoco per reset',
    expires_at DATETIME NOT NULL COMMENT 'Scadenza token (tipicamente 1 ora)',
    used BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Token già utilizzato',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_utente FOREIGN KEY (id_utente) 
        REFERENCES utenti(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_used (used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Token per recupero password con scadenza';

-- ============================================================================
-- DATI DI ESEMPIO
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UTENTI
-- ----------------------------------------------------------------------------
-- Password hash generati con BCrypt (cost factor 12)
-- Studenti: password = "password"
-- Bibliotecario: password = "admin123"

INSERT INTO utenti (email, password, ruolo, totp_secret, is_active, failed_attempts) VALUES
-- Studenti
('studente1@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'JBSWY3DPEHPK3PXP', TRUE, 0),
('studente2@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'KRSXG5DSNFXGOIDM', TRUE, 0),
('studente3@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'MZXW6YTBOIYTEMZT', TRUE, 0),
('mario.rossi@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'GEZDGNBVGY3TQOJQ', TRUE, 0),
('giulia.bianchi@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'MNQXGLTDN5QWY3DP', TRUE, 0),
('luca.verdi@test.it', '$2a$12$umpKwHrD1O8kTVoXFbeVZe2TCzxBVN4LfoZ16TeXL7.7AIx6Wc68i', 'studente', 'OBQXG43XN5ZGC3TU', TRUE, 0),

-- Bibliotecari
('bibliotecario@test.it', '$2a$12$vEKOOqOYxwsSFYiWJD9pdOJHmNmW3TA1HxDdlB2dFoS8rx4..0Urm', 'bibliotecario', 'NB2W45DFOIZA4YTF', TRUE, 0),
('admin@bibliotech.local', '$2a$12$vEKOOqOYxwsSFYiWJD9pdOJHmNmW3TA1HxDdlB2dFoS8rx4..0Urm', 'bibliotecario', 'MJQXGZJSGMZTGMRY', TRUE, 0);

-- ----------------------------------------------------------------------------
-- LIBRI
-- ----------------------------------------------------------------------------
-- Catalogo diversificato per biblioteca scolastica
-- Include letteratura italiana, straniera, classici e libri di testo

INSERT INTO libri (titolo, autore, copie_totali, copie_disponibili) VALUES
-- Letteratura Italiana Classica
('I Promessi Sposi', 'Alessandro Manzoni', 8, 8),
('La Divina Commedia', 'Dante Alighieri', 10, 9),
('Il Nome della Rosa', 'Umberto Eco', 5, 4),
('Se questo è un uomo', 'Primo Levi', 6, 6),
('Il Gattopardo', 'Giuseppe Tomasi di Lampedusa', 4, 4),

-- Letteratura Italiana Moderna
('Sostiene Pereira', 'Antonio Tabucchi', 3, 3),
('La solitudine dei numeri primi', 'Paolo Giordano', 4, 3),
('Io non ho paura', 'Niccolò Ammaniti', 3, 3),

-- Letteratura Straniera Classica
('1984', 'George Orwell', 7, 5),
('Il Signore degli Anelli', 'J.R.R. Tolkien', 6, 4),
('Orgoglio e Pregiudizio', 'Jane Austen', 4, 4),
('Cime Tempestose', 'Emily Brontë', 3, 3),
('Il Grande Gatsby', 'F. Scott Fitzgerald', 5, 5),

-- Letteratura Straniera Moderna
('Harry Potter e la Pietra Filosofale', 'J.K. Rowling', 8, 6),
('Il Piccolo Principe', 'Antoine de Saint-Exupéry', 6, 6),
('Il Cacciatore di Aquiloni', 'Khaled Hosseini', 4, 4),
('Cronache del Ghiaccio e del Fuoco', 'George R.R. Martin', 5, 4),

-- Filosofia e Saggistica
('La Repubblica', 'Platone', 4, 4),
('Etica Nicomachea', 'Aristotele', 3, 3),
('Pensieri', 'Blaise Pascal', 3, 3),

-- Scienze e Divulgazione
('Breve storia del tempo', 'Stephen Hawking', 4, 4),
('L\'origine delle specie', 'Charles Darwin', 3, 3),
('Il gene egoista', 'Richard Dawkins', 3, 3),

-- Storia
('La storia', 'Elsa Morante', 4, 4),
('Storia d\'Italia', 'Indro Montanelli', 3, 3),

-- Teatro
('Amleto', 'William Shakespeare', 5, 5),
('Sei personaggi in cerca d\'autore', 'Luigi Pirandello', 4, 4),

-- Poesia
('Canzoniere', 'Francesco Petrarca', 3, 3),
('Myricae', 'Giovanni Pascoli', 3, 3);

-- ----------------------------------------------------------------------------
-- PRESTITI
-- ----------------------------------------------------------------------------
-- Prestiti di esempio: alcuni attivi, alcuni già restituiti

-- Prestiti ATTIVI (data_restituzione = NULL)
INSERT INTO prestiti (id_utente, id_libro, data_prestito, data_restituzione, quantita) VALUES
-- Prestiti attivi recenti
(1, 9, DATE_SUB(NOW(), INTERVAL 3 DAY), NULL, 1),    -- studente1: 1984
(2, 10, DATE_SUB(NOW(), INTERVAL 5 DAY), NULL, 1),   -- studente2: Il Signore degli Anelli
(3, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, 1),    -- studente3: Il Nome della Rosa
(4, 14, DATE_SUB(NOW(), INTERVAL 7 DAY), NULL, 1),   -- mario.rossi: Harry Potter
(5, 7, DATE_SUB(NOW(), INTERVAL 10 DAY), NULL, 1),   -- giulia.bianchi: La solitudine dei numeri primi
(6, 17, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, 1),   -- luca.verdi: Cronache del Ghiaccio e del Fuoco

-- Altri prestiti attivi (più vecchi)
(1, 2, DATE_SUB(NOW(), INTERVAL 15 DAY), NULL, 1),   -- studente1: La Divina Commedia
(4, 14, DATE_SUB(NOW(), INTERVAL 20 DAY), NULL, 1);  -- mario.rossi: altro Harry Potter

-- Prestiti COMPLETATI (data_restituzione NOT NULL)
INSERT INTO prestiti (id_utente, id_libro, data_prestito, data_restituzione, quantita) VALUES
-- Prestiti restituiti nell'ultimo mese
(1, 1, DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY), 1),
(2, 2, DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 25 DAY), 1),
(3, 4, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY), 1),
(4, 9, DATE_SUB(NOW(), INTERVAL 50 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY), 1),
(5, 10, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY), 1),
(6, 13, DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY), 1),

-- Prestiti restituiti nei mesi precedenti
(1, 14, DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 75 DAY), 1),
(2, 15, DATE_SUB(NOW(), INTERVAL 95 DAY), DATE_SUB(NOW(), INTERVAL 80 DAY), 1),
(3, 11, DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 85 DAY), 1),
(4, 16, DATE_SUB(NOW(), INTERVAL 110 DAY), DATE_SUB(NOW(), INTERVAL 95 DAY), 1),
(5, 9, DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 105 DAY), 1),
(6, 10, DATE_SUB(NOW(), INTERVAL 130 DAY), DATE_SUB(NOW(), INTERVAL 115 DAY), 1);

-- ============================================================================
-- INFORMAZIONI SULLE CREDENZIALI
-- ============================================================================

-- STUDENTI (password: "password")
-- Email: studente1@test.it, studente2@test.it, studente3@test.it
--        mario.rossi@test.it, giulia.bianchi@test.it, luca.verdi@test.it
-- Password: password

-- BIBLIOTECARI (password: "admin123")
-- Email: bibliotecario@test.it, admin@bibliotech.local
-- Password: admin123

-- Tutti gli utenti hanno una chiave TOTP configurata per 2FA opzionale
-- Le chiavi TOTP possono essere scansionate con app come Google Authenticator

-- ============================================================================
-- VERIFICA INTEGRITÀ DATI
-- ============================================================================

-- Verifica vincoli copie (devono essere tutti TRUE)
SELECT 
    'Verifica Copie Disponibili' as Test,
    COUNT(*) as TotaleLibri,
    SUM(CASE WHEN copie_disponibili <= copie_totali THEN 1 ELSE 0 END) as LibriValidi,
    SUM(CASE WHEN copie_disponibili > copie_totali THEN 1 ELSE 0 END) as LibriInvalidi
FROM libri;

-- Verifica prestiti attivi vs copie disponibili
SELECT 
    'Verifica Coerenza Prestiti' as Test,
    l.id,
    l.titolo,
    l.copie_totali,
    l.copie_disponibili,
    COUNT(p.id) as prestiti_attivi,
    (l.copie_totali - COUNT(p.id)) as calcolato_disponibile
FROM libri l
LEFT JOIN prestiti p ON l.id = p.id_libro AND p.data_restituzione IS NULL
GROUP BY l.id
HAVING l.copie_disponibili != (l.copie_totali - COUNT(p.id));

-- Se la query sopra non restituisce righe, i dati sono coerenti

-- ============================================================================
-- STATISTICHE INIZIALI
-- ============================================================================

SELECT 'STATISTICHE DATABASE' as Categoria, '' as Valore
UNION ALL
SELECT 'Totale Utenti', COUNT(*) FROM utenti
UNION ALL
SELECT '- Studenti', COUNT(*) FROM utenti WHERE ruolo = 'studente'
UNION ALL
SELECT '- Bibliotecari', COUNT(*) FROM utenti WHERE ruolo = 'bibliotecario'
UNION ALL
SELECT 'Totale Libri (titoli)', COUNT(*) FROM libri
UNION ALL
SELECT 'Totale Copie Possedute', SUM(copie_totali) FROM libri
UNION ALL
SELECT 'Copie Disponibili', SUM(copie_disponibili) FROM libri
UNION ALL
SELECT 'Copie in Prestito', SUM(copie_totali) - SUM(copie_disponibili) FROM libri
UNION ALL
SELECT 'Prestiti Attivi', COUNT(*) FROM prestiti WHERE data_restituzione IS NULL
UNION ALL
SELECT 'Prestiti Completati', COUNT(*) FROM prestiti WHERE data_restituzione IS NOT NULL
UNION ALL
SELECT 'Totale Prestiti Storici', COUNT(*) FROM prestiti;
