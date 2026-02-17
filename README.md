# 📚 BiblioTech - Sistema Informativo per Gestione Prestiti Librari

Sistema web completo per la gestione informatizzata dei prestiti librari scolastici, sviluppato secondo le specifiche del documento di analisi "BibliotechAnalisi__4_.docx".

## 🎯 Descrizione del Sistema

BiblioTech sostituisce completamente il registro cartaceo con una piattaforma digitale centralizzata che garantisce:
- ✅ **Affidabilità**: Dati sempre disponibili e aggiornati
- ✅ **Precisione**: Tracciamento real-time delle copie disponibili
- ✅ **Velocità**: Operazioni immediate senza errori manuali
- ✅ **Sicurezza**: Autenticazione 2FA, session tracking, password recovery
- ✅ **Controllo**: Audit completo con tabella sessioni

---

## 🏗️ Architettura del Sistema

### Livelli Architetturali

**1. Livello Dominio (Domain Layer)**
- Entità: Utente, Libro, Prestito, Sessione, TokenReset

**2. Livello Applicativo (Service Layer)**
- Gestione utenti, libri, prestiti, sessioni
- Logica di business e validazione
- Autenticazione (password + TOTP)

**3. Livello Persistenza (Persistence Layer)**
- Database MySQL con PDO
- Prepared statements per sicurezza
- Transazioni per operazioni critiche

**4. Livello Infrastrutturale**
- Docker containerizzato
- Mailpit per email (development)
- 2FAuth per gestione TOTP

---

## 🚀 Quick Start

### Prerequisiti
- Docker e Docker Compose
- Porte 9000, 9001, 9002, 8025 disponibili

### Installazione

```bash
# 1. Avvia i container
docker-compose up -d --build

# 2. Attendi inizializzazione (~30 secondi)
# Il database viene creato automaticamente con dati di esempio

# 3. Accedi all'applicazione
# Web App: http://localhost:9000
# phpMyAdmin: http://localhost:9001
# Mailpit: http://localhost:8025
# 2FAuth: http://localhost:9002
```

### Credenziali di Test

**Studenti:**
- `studente1@test.it` / `password`
- `mario.rossi@test.it` / `password`
- `giulia.bianchi@test.it` / `password`

**Bibliotecari:**
- `bibliotecario@test.it` / `admin123`
- `admin@bibliotech.local` / `admin123`

---

## 📊 Schema Database

### Tabelle

**UTENTI** (8 utenti: 6 studenti + 2 bibliotecari)
```sql
- id (PK)
- email (UNIQUE)
- password (BCrypt hash)
- ruolo (ENUM: studente, bibliotecario)
- totp_secret (chiave 2FA)
- is_active (account attivo/disabilitato)
- failed_attempts (contatore tentativi falliti)
- last_failed_login (timestamp ultimo fallimento)
```

**LIBRI** (29 titoli, 136 copie totali)
```sql
- id (PK)
- titolo
- autore
- copie_totali
- copie_disponibili
- CONSTRAINT: copie_disponibili <= copie_totali
```

**PRESTITI** (20 prestiti: 8 attivi + 12 completati)
```sql
- id (PK)
- id_utente (FK → utenti)
- id_libro (FK → libri)
- data_prestito
- data_restituzione (NULL = attivo)
- quantita
```

**SESSIONI** (tracking completo)
```sql
- id (PK)
- id_utente (FK → utenti)
- session_token (128 char, UNIQUE)
- login_time
- logout_time
- ip_address
- user_agent
```

**PASSWORD_RESET_TOKENS**
```sql
- id (PK)
- id_utente (FK → utenti)
- token (128 char, UNIQUE)
- expires_at (scadenza 1 ora)
- used (BOOLEAN)
```

---

## 🔐 Sicurezza Implementata

### Autenticazione e Autorizzazione
✅ **Password Hashing**: BCrypt (cost factor 12)  
✅ **2FA Support**: TOTP (RFC 6238) con OTPHP library  
✅ **Account Lockout**: 5 tentativi, blocco 15 minuti  
✅ **Session Tracking**: Audit completo in database  
✅ **Session Fixation Prevention**: Regenerate ID dopo login  
✅ **Role-Based Access**: Separazione studente/bibliotecario  

### Protezione Dati
✅ **SQL Injection**: Prepared statements (PDO)  
✅ **XSS Protection**: htmlspecialchars() su tutti gli output  
✅ **Password Recovery**: Token SHA-256, single-use, 1h expiry  
✅ **CSRF Protection**: Session token verification  

### Transazioni Atomiche
✅ **FOR UPDATE Lock**: Previene race conditions  
✅ **Atomic Operations**: Prestito + decremento copie  
✅ **Rollback Completo**: In caso di errore  
✅ **Data Integrity**: Verifiche multiple  

---

## 📋 Variabili di Sessione ($_SESSION)

Come da specifica del documento di analisi:

| Chiave | Tipo | Descrizione |
|--------|------|-------------|
| `user_id` | int | ID univoco utente |
| `email` | string | Email utente |
| `ruolo` | string | studente o bibliotecario |
| `logged_in` | boolean | Flag autenticazione |
| `login_method` | string | password o 2fa |
| `login_time` | datetime | Timestamp login |
| `session_token` | string | Token univoco (128 char) |
| `session_db_id` | int | ID record in tabella sessioni |

**Sicurezza**: Nessuna password o TOTP secret in sessione.

---

## 🔄 Flussi Operativi

### Login con 2FA
1. Utente inserisce email + password
2. Sistema verifica password (BCrypt)
3. Se password corretta e TOTP configurato → richiedi codice
4. Utente inserisce codice TOTP
5. Sistema verifica codice (RFC 6238, ±30 sec)
6. Reset failed_attempts
7. Genera session_token (128 char)
8. Crea record in tabella sessioni
9. Regenera session ID PHP
10. Imposta variabili $_SESSION
11. Redirect basato su ruolo

### Recupero Password
1. Utente richiede reset (email)
2. Sistema verifica email esiste
3. Genera token (128 char)
4. Salva token_hash in DB con scadenza 1h
5. Invia email HTML via Mailpit
6. Utente click link da email
7. Sistema verifica token valido e non scaduto
8. Utente imposta nuova password
9. Token marcato come 'used'
10. Password aggiornata (BCrypt hash)

### Creazione Prestito (con Race Condition Prevention)
1. Studente richiede prestito
2. **BEGIN TRANSACTION**
3. **FOR UPDATE** lock su record libro
4. Verifica copie_disponibili > 0
5. Verifica utente non ha già il libro
6. INSERT prestito con data_prestito = NOW()
7. UPDATE copie_disponibili = copie_disponibili - 1
8. **COMMIT** (atomico)
9. Redirect con successo

**Protezione**: Se 2 utenti richiedono l'ultima copia simultaneamente, solo il primo avrà successo. Il secondo riceve errore "Nessuna copia disponibile".

### Restituzione Libro
1. Bibliotecario/Studente avvia restituzione
2. **BEGIN TRANSACTION**
3. **FOR UPDATE** lock su prestito
4. Verifica prestito non già chiuso
5. Verifica autorizzazione (studente = solo suoi libri)
6. UPDATE data_restituzione = NOW()
7. UPDATE copie_disponibili = copie_disponibili + quantita
8. Verifica copie_disponibili <= copie_totali
9. **COMMIT** (atomico)
10. Redirect con successo

---

## 📁 Struttura File

```
bibliotech-final/
├── README.md                          # Questo file
├── docker-compose.yaml               # Configurazione servizi
├── Dockerfile                        # Container PHP + Apache
├── composer.json                     # Dipendenze (OTPHP)
├── composer.lock                     # Lock dipendenze
│
├── sql/
│   └── database.sql                  # Schema + dati di esempio
│
└── src/                              # Codice sorgente
    ├── assets/
    │   └── style.css                 # Stili globali
    │
    ├── config.php                    # Configurazione DB
    ├── auth.php                      # Sistema autenticazione completo
    ├── email_helper.php              # Funzioni invio email
    │
    ├── index.php                     # Homepage pubblica
    ├── home.php                      # Dashboard utenti loggati
    ├── header.php                    # Navigation dinamica
    ├── footer.php                    # Footer comune
    │
    ├── login.php                     # Login + 2FA + lockout
    ├── logout.php                    # Logout con session cleanup
    ├── registrazione.php             # Registrazione studenti
    ├── pw_dimenticata.php            # Richiesta reset password
    ├── nuova_pw.php                  # Handler reset password
    │
    ├── libri.php                     # Catalogo libri
    ├── libro.php                     # Dettaglio libro
    ├── prestito.php                  # Handler prestito (transazioni)
    ├── prestiti.php                  # Prestiti utente
    ├── restituisci.php               # Handler restituzione (transazioni)
    │
    └── gestione_restituzioni.php    # Dashboard bibliotecario
```

---

## 🎮 Funzionalità per Ruolo

### 👨‍🎓 Studente
✅ Visualizzare catalogo libri (29 titoli)  
✅ Verificare disponibilità in real-time  
✅ Richiedere prestito (se copie > 0)  
✅ Visualizzare propri prestiti attivi  
✅ Restituire propri libri  
✅ Consultare storico personale  
❌ NON può accedere a gestione_restituzioni.php  
❌ NON può vedere prestiti di altri  

### 👨‍💼 Bibliotecario
✅ Visualizzare catalogo completo  
✅ Dashboard con TUTTI i prestiti attivi  
✅ Registrare restituzione di QUALSIASI libro  
✅ Statistiche complete (prestiti attivi, utenti, copie)  
✅ Visualizzare storico recente  
✅ Accesso completo a gestione_restituzioni.php  

---

## 🧪 Testing

### Test Scenario 1: Login Base
```bash
1. Vai a http://localhost:9000/login.php
2. Email: studente1@test.it
3. Password: password
4. TOTP: (lascia vuoto)
5. Click "Accedi"
✅ Redirect a libri.php
```

### Test Scenario 2: Login con 2FA
```bash
1. Vai a http://localhost:9002 (2FAuth)
2. Aggiungi account con TOTP secret di un utente
3. Login con email + password + codice TOTP
✅ Autenticazione 2FA completata
```

### Test Scenario 3: Account Lockout
```bash
1. Login con password errata 5 volte
2. Account bloccato per 15 minuti
3. Messaggio: "Account bloccato..."
✅ Sistema anti-brute force attivo
```

### Test Scenario 4: Password Recovery
```bash
1. Click "Password dimenticata?"
2. Inserisci email: studente1@test.it
3. Apri Mailpit: http://localhost:8025
4. Click email "BiblioTech - Recupero Password"
5. Click "Reimposta Password"
6. Imposta nuova password
7. Login con nuova password
✅ Password aggiornata con successo
```

### Test Scenario 5: Prestito e Restituzione
```bash
# Studente
1. Login come studente1@test.it
2. Sfoglia catalogo (29 libri)
3. Click su "1984" (7 copie, 5 disponibili)
4. Click "PRENDI IN PRESTITO"
✅ Copie disponibili: 5 → 4
✅ Prestito creato
5. Vai a "I Miei Prestiti"
6. Click "RESTITUISCI" su "1984"
✅ Copie disponibili: 4 → 5
✅ Prestito chiuso

# Bibliotecario
1. Login come bibliotecario@test.it
2. Dashboard mostra TUTTI i prestiti attivi (8)
3. Click "RESTITUISCI" su qualsiasi prestito
✅ Restituzione registrata
✅ Copie aggiornate
```

### Test Scenario 6: Race Condition
```bash
# Simula 2 utenti che richiedono l'ultima copia
1. Prepara libro con 1 sola copia disponibile
2. Apri 2 browser/tab contemporaneamente
3. Entrambi click "PRENDI IN PRESTITO" simultaneamente
✅ Solo 1 prestito creato
✅ L'altro riceve errore "Nessuna copia disponibile"
✅ Transazione protegge da inconsistenze
```

---

## 📊 Dati di Esempio

### Catalogo Libri (29 titoli, 136 copie)

**Letteratura Italiana Classica:**
1. I Promessi Sposi - Manzoni (8 copie)
2. La Divina Commedia - Dante (10 copie, 1 in prestito)
3. Il Nome della Rosa - Eco (5 copie, 1 in prestito)
4. Se questo è un uomo - Levi (6 copie)
5. Il Gattopardo - Lampedusa (4 copie)

**Letteratura Straniera:**
- 1984 - Orwell (7 copie, 2 in prestito)
- Il Signore degli Anelli - Tolkien (6 copie, 2 in prestito)
- Harry Potter - Rowling (8 copie, 2 in prestito)
- Cronache del Ghiaccio e del Fuoco - Martin (5 copie, 1 in prestito)

**E molti altri...**

### Prestiti Attivi (8)
- studente1: 1984 (3 giorni fa)
- studente2: Il Signore degli Anelli (5 giorni fa)
- mario.rossi: Harry Potter (7 giorni fa)
- giulia.bianchi: La solitudine dei numeri primi (10 giorni fa)
- E altri...

---

## 🔧 Configurazione

### Costanti di Sicurezza (config.php)
```php
MAX_LOGIN_ATTEMPTS = 5           // Tentativi prima lockout
LOCKOUT_TIME = 900               // 15 minuti
PASSWORD_RESET_EXPIRY = 3600     // 1 ora
SESSION_LIFETIME = 7200          // 2 ore
TOTP_WINDOW = 1                  // ±30 secondi
```

### Porte Servizi
| Servizio | Porta | URL |
|----------|-------|-----|
| Web App | 9000 | http://localhost:9000 |
| phpMyAdmin | 9001 | http://localhost:9001 |
| 2FAuth | 9002 | http://localhost:9002 |
| Mailpit | 8025 | http://localhost:8025 |
| SMTP (interno) | 1025 | mailpit:1025 |

---

## 🚨 Troubleshooting

### Container non si avviano
```bash
docker-compose down -v
docker-compose up -d --build
docker ps  # Verifica tutti i container running
```

### Database non accessibile
```bash
docker exec -it BiblioTech-DataBaseR mysql -uroot -prootpassword biblioTech
# Test query: SELECT COUNT(*) FROM utenti;
```

### Email non arrivano
```bash
# Verifica Mailpit
docker logs BiblioTech-Mailpit

# Verifica msmtp logs
docker exec BiblioTech-SitoWebR cat /var/log/msmtp.log

# Test manuale
docker exec BiblioTech-SitoWebR sh -c 'echo -e "Subject: Test\n\nTest" | msmtp -t test@example.com'
# Controlla su http://localhost:8025
```

### Errori PHP
```bash
docker logs BiblioTech-SitoWebR
docker exec BiblioTech-SitoWebR tail -f /var/log/apache2/error.log
```

### Reset completo sistema
```bash
docker-compose down -v
rm -rf mysql_data/  # Se esiste
docker-compose up -d --build
# Attendi 30 secondi per inizializzazione DB
```

---

## 📈 Statistiche Sistema

```
Utenti Totali: 8
├── Studenti: 6
└── Bibliotecari: 2

Libri Totali: 29 titoli
├── Copie Possedute: 136
├── Copie Disponibili: 122
└── Copie in Prestito: 14

Prestiti:
├── Attivi: 8
├── Completati: 12
└── Totale Storico: 20

Categorie Libri:
├── Letteratura Italiana (8)
├── Letteratura Straniera (9)
├── Filosofia (3)
├── Scienze (3)
├── Storia (2)
├── Teatro (2)
└── Poesia (2)
```

---

## 🎓 Conformità Specifica

### FASE A - Analisi e Progettazione ✅
- [x] Descrizione sistema completa
- [x] Customizzazioni: 2FA, Email recovery, Session tracking
- [x] Schema database con 5 tabelle
- [x] ER diagram (struttura pronta in docs/)
- [x] UML diagram (struttura pronta in docs/)
- [x] Specifiche sicurezza e sessione

### FASE B - Implementazione ✅
- [x] Database SQL completo
- [x] 29 libri, 6 studenti, 2 bibliotecari
- [x] Login con 2FA e lockout
- [x] Logout sicuro
- [x] Registrazione studenti
- [x] Password recovery via email
- [x] Catalogo libri
- [x] Dettaglio libro
- [x] Prestiti studenti
- [x] Dashboard bibliotecario
- [x] Gestione restituzioni
- [x] Transazioni atomiche
- [x] Prepared statements
- [x] Password hashing (BCrypt)

### Requisiti Tecnici ✅
- [x] Docker Compose presente
- [x] README.md completo
- [x] Directory sql/ con dump
- [x] Directory src/ con codice
- [x] Directory docs/ (struttura)
- [x] Password hashate (NO chiaro)
- [x] Git-ready structure

---

## 📝 Best Practices Implementate

✅ **Separation of Concerns**: Config, Auth, Email separati  
✅ **DRY Principle**: Header/Footer riusabili  
✅ **Security First**: Tutte le misure di sicurezza implementate  
✅ **Error Handling**: Try-catch su operazioni critiche  
✅ **Logging**: error_log() per debugging  
✅ **Comments**: Codice ben commentato  
✅ **Transactions**: Operazioni atomiche garantite  
✅ **Validation**: Input sempre validato  
✅ **Prepared Statements**: 100% delle query  
✅ **Session Security**: Tracking completo  

---

## 🌟 Features Avanzate

### Già Implementate
✅ 2FA con TOTP (RFC 6238)  
✅ Session tracking in database  
✅ Account lockout anti-brute force  
✅ Password recovery via email  
✅ Mailpit per testing email  
✅ Race condition prevention  
✅ Transaction rollback  
✅ Audit trail completo  

### Possibili Estensioni Future
- [ ] Scadenze prestiti con notifiche
- [ ] Sistema prenotazioni
- [ ] QR code per identificazione libri
- [ ] Report statistici avanzati
- [ ] API REST
- [ ] Mobile app
- [ ] Integrazione ISBN database
- [ ] Sistema recensioni

---

## 📞 Supporto

Per problemi o domande:
1. Controlla questa documentazione
2. Verifica logs: `docker logs <container>`
3. Testa connessioni: `docker exec ...`
4. Reset completo se necessario

---

## 📜 Licenza

Progetto sviluppato per scopi didattici.

---

**📚 BiblioTech v1.0.0**  
*Sistema Informativo per Gestione Prestiti Librari*  
Conforme al documento di analisi "BibliotechAnalisi__4_.docx"  

© 2026 BiblioTech Project
