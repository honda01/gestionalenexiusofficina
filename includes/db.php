<?php
/**
 * Configurazione Database - Gestionale Officina Moto
 * File per la connessione al database MySQL
 */

// Configurazione database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gestionale_officina');

// Classe per la gestione della connessione database
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Errore connessione database: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Metodo per eseguire query SELECT
    public function select($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Errore SELECT: " . $e->getMessage());
            return false;
        }
    }
    
    // Metodo per eseguire query INSERT/UPDATE/DELETE
    public function execute($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch(PDOException $e) {
            error_log("Errore EXECUTE: " . $e->getMessage());
            return false;
        }
    }
    
    // Metodo per ottenere l'ultimo ID inserito
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Metodo per contare righe
    public function count($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch(PDOException $e) {
            error_log("Errore COUNT: " . $e->getMessage());
            return 0;
        }
    }
}

// Funzione helper per ottenere la connessione
function getDB() {
    return Database::getInstance();
}

// Funzione per sanitizzare input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Funzione per validare email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Funzione per generare hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Funzione per verificare password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>