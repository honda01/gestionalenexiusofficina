-- Schema Database Gestionale Officina Moto
-- Creazione database
CREATE DATABASE IF NOT EXISTS gestionale_officina;
USE gestionale_officina;

-- Tabella utenti
CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin', 'meccanico', 'reception') NOT NULL,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella clienti
CREATE TABLE clienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(100),
    indirizzo TEXT,
    note TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella veicoli
CREATE TABLE veicoli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    marca VARCHAR(50) NOT NULL,
    modello VARCHAR(50) NOT NULL,
    targa VARCHAR(20) UNIQUE NOT NULL,
    anno INT,
    cilindrata INT,
    colore VARCHAR(30),
    foto VARCHAR(255),
    note TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE
);

-- Tabella interventi
CREATE TABLE interventi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veicolo_id INT NOT NULL,
    descrizione TEXT NOT NULL,
    stato ENUM('in_attesa', 'lavorazione', 'completato', 'consegnato') DEFAULT 'in_attesa',
    data_inizio DATE NOT NULL,
    data_fine DATE,
    costo DECIMAL(10,2) DEFAULT 0.00,
    note TEXT,
    meccanico_id INT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (veicolo_id) REFERENCES veicoli(id) ON DELETE CASCADE,
    FOREIGN KEY (meccanico_id) REFERENCES utenti(id)
);

-- Tabella magazzino
CREATE TABLE magazzino (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_ricambio VARCHAR(100) NOT NULL,
    codice VARCHAR(50) UNIQUE NOT NULL,
    quantita INT NOT NULL DEFAULT 0,
    prezzo DECIMAL(10,2) DEFAULT 0.00,
    soglia_minima INT DEFAULT 5,
    fornitore VARCHAR(100),
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabella appuntamenti
CREATE TABLE appuntamenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    veicolo_id INT,
    data_appuntamento DATE NOT NULL,
    ora_appuntamento TIME NOT NULL,
    descrizione TEXT,
    stato ENUM('programmato', 'confermato', 'completato', 'cancellato') DEFAULT 'programmato',
    note TEXT,
    data_creazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clienti(id) ON DELETE CASCADE,
    FOREIGN KEY (veicolo_id) REFERENCES veicoli(id) ON DELETE SET NULL
);

-- Inserimento dati di esempio

-- Utenti di esempio
INSERT INTO utenti (nome, email, password_hash, ruolo) VALUES
('Admin Sistema', 'admin@officina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Marco Rossi', 'marco@officina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'meccanico'),
('Laura Bianchi', 'laura@officina.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reception');

-- Clienti di esempio
INSERT INTO clienti (nome, cognome, telefono, email, indirizzo, note) VALUES
('Giuseppe', 'Verdi', '333-1234567', 'giuseppe.verdi@email.com', 'Via Roma 123, Milano', 'Cliente affezionato'),
('Maria', 'Rossi', '334-7654321', 'maria.rossi@email.com', 'Via Garibaldi 45, Roma', 'Preferisce appuntamenti mattutini'),
('Antonio', 'Bianchi', '335-9876543', 'antonio.bianchi@email.com', 'Corso Italia 78, Napoli', 'Moto da corsa'),
('Francesca', 'Neri', '336-5432109', 'francesca.neri@email.com', 'Via Dante 12, Torino', 'Cliente nuovo');

-- Veicoli di esempio
INSERT INTO veicoli (cliente_id, marca, modello, targa, anno, cilindrata, colore, note) VALUES
(1, 'Ducati', 'Monster 821', 'AB123CD', 2019, 821, 'Rosso', 'Moto sportiva'),
(1, 'Honda', 'CBR 600RR', 'EF456GH', 2020, 600, 'Nero', 'Moto da pista'),
(2, 'Yamaha', 'MT-07', 'IJ789KL', 2021, 689, 'Blu', 'Naked bike'),
(3, 'Kawasaki', 'Ninja ZX-10R', 'MN012OP', 2022, 998, 'Verde', 'Superbike'),
(4, 'BMW', 'R 1250 GS', 'QR345ST', 2023, 1254, 'Bianco', 'Adventure bike');

-- Interventi di esempio
INSERT INTO interventi (veicolo_id, descrizione, stato, data_inizio, data_fine, costo, meccanico_id) VALUES
(1, 'Tagliando completo e sostituzione olio', 'completato', '2024-01-15', '2024-01-16', 150.00, 2),
(2, 'Sostituzione pastiglie freno anteriori', 'lavorazione', '2024-01-20', NULL, 80.00, 2),
(3, 'Controllo generale e regolazione catena', 'in_attesa', '2024-01-25', NULL, 50.00, NULL),
(4, 'Riparazione sistema di scarico', 'lavorazione', '2024-01-22', NULL, 200.00, 2),
(5, 'Installazione borse laterali', 'in_attesa', '2024-01-28', NULL, 120.00, NULL);

-- Magazzino di esempio
INSERT INTO magazzino (nome_ricambio, codice, quantita, prezzo, soglia_minima, fornitore) VALUES
('Olio motore 10W-40', 'OIL001', 25, 12.50, 5, 'Castrol'),
('Filtro olio', 'FIL001', 15, 8.00, 3, 'Mann Filter'),
('Pastiglie freno anteriori', 'BRK001', 8, 45.00, 2, 'Brembo'),
('Catena trasmissione', 'CHN001', 5, 85.00, 2, 'DID'),
('Candele', 'SPK001', 20, 15.00, 5, 'NGK'),
('Filtro aria', 'AIR001', 12, 25.00, 3, 'K&N'),
('Pneumatico anteriore 120/70-17', 'TYR001', 3, 120.00, 2, 'Michelin'),
('Pneumatico posteriore 180/55-17', 'TYR002', 2, 150.00, 2, 'Michelin');

-- Appuntamenti di esempio
INSERT INTO appuntamenti (cliente_id, veicolo_id, data_appuntamento, ora_appuntamento, descrizione, stato) VALUES
(1, 1, '2024-02-01', '09:00:00', 'Controllo generale', 'programmato'),
(2, 3, '2024-02-02', '14:30:00', 'Tagliando 10.000 km', 'confermato'),
(3, 4, '2024-02-05', '10:15:00', 'Installazione accessori', 'programmato'),
(4, 5, '2024-02-08', '16:00:00', 'Primo tagliando', 'programmato');