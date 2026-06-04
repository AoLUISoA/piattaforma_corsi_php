-- Gianpaolo Rotolo
-- Luigi Piacquadio
-- 2025/2026
-- database gestione corsi

CREATE DATABASE piattaforma_corsi;
USE piattaforma_corsi;

CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    ruolo ENUM('studente', 'docente', 'admin') NOT NULL,
    email VARCHAR(100) NOT NULL,
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE corsi (
    id_corso INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(100) NOT NULL,
    descrizione TEXT,
    docente_id INT NOT NULL,
    difficolta ENUM('base', 'intermedio', 'avanzato') DEFAULT 'base',
    contenuto TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES utenti(id) ON DELETE CASCADE
);

CREATE TABLE iscrizioni (
    id_iscrizione INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    id_corso INT NOT NULL,
    avanzamento FLOAT DEFAULT 0,
    data_iscrizione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (id_corso) REFERENCES corsi(id_corso) ON DELETE CASCADE,
    UNIQUE KEY unique_iscrizione (id_utente, id_corso)
);

CREATE TABLE feedback (
    id_feedback INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    id_corso INT NOT NULL,
    voto INT CHECK (voto BETWEEN 1 AND 5),
    commento TEXT,
    data_feedback TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (id_corso) REFERENCES corsi(id_corso) ON DELETE CASCADE
);

INSERT INTO utenti (username, password, ruolo, email) VALUES
('mario_studente', 'pass123', 'studente', 'mario@example.com'),
('prof_rossi', 'pass123', 'docente', 'rossi@example.com');


INSERT INTO corsi (titolo, descrizione, docente_id, difficolta, contenuto) VALUES
('Introduzione al C', 'Impara le basi della programmazione in C', 2, 'base', 'Capitolo 1: Variabili\nCapitolo 2: Cicli\nCapitolo 3: Funzioni'),
('Database SQL', 'Gestione database relazionali', 2, 'intermedio', 'Capitolo 1: SELECT\nCapitolo 2: JOIN\nCapitolo 3: Subquery');