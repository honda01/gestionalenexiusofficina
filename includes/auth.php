<?php
/**
 * Sistema di Autenticazione - Gestionale Officina Moto
 * Gestione login, logout e controllo sessioni
 */

require_once 'db.php';

// Avvia la sessione se non è già attiva
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    
    /**
     * Effettua il login dell'utente
     */
    public static function login($email, $password) {
        $db = getDB();
        
        $query = "SELECT id, nome, email, password_hash, ruolo FROM utenti WHERE email = ?";
        $result = $db->select($query, [$email]);
        
        if ($result && count($result) > 0) {
            $user = $result[0];
            
            if (verifyPassword($password, $user['password_hash'])) {
                // Login riuscito - salva dati in sessione
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['ruolo'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Effettua il logout dell'utente
     */
    public static function logout() {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    /**
     * Verifica se l'utente è loggato
     */
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Ottiene i dati dell'utente corrente
     */
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }
    
    /**
     * Verifica se l'utente ha un determinato ruolo
     */
    public static function hasRole($role) {
        if (self::isLoggedIn()) {
            return $_SESSION['user_role'] === $role;
        }
        return false;
    }
    
    /**
     * Verifica se l'utente ha uno dei ruoli specificati
     */
    public static function hasAnyRole($roles) {
        if (self::isLoggedIn()) {
            return in_array($_SESSION['user_role'], $roles);
        }
        return false;
    }
    
    /**
     * Reindirizza alla pagina di login se non autenticato
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
    
    /**
     * Reindirizza se non si ha il ruolo richiesto
     */
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            header('Location: dashboard.php?error=access_denied');
            exit();
        }
    }
    
    /**
     * Reindirizza se non si ha almeno uno dei ruoli richiesti
     */
    public static function requireAnyRole($roles) {
        self::requireLogin();
        if (!self::hasAnyRole($roles)) {
            header('Location: dashboard.php?error=access_denied');
            exit();
        }
    }
    
    /**
     * Genera token CSRF
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verifica token CSRF
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Ottiene il nome del ruolo in italiano
     */
    public static function getRoleName($role) {
        $roles = [
            'admin' => 'Amministratore',
            'meccanico' => 'Meccanico',
            'reception' => 'Reception'
        ];
        
        return isset($roles[$role]) ? $roles[$role] : $role;
    }
}

/**
 * Funzioni helper per l'autenticazione
 */

// Verifica se l'utente è loggato
function isLoggedIn() {
    return Auth::isLoggedIn();
}

// Ottiene l'utente corrente
function getCurrentUser() {
    return Auth::getCurrentUser();
}

// Verifica ruolo
function hasRole($role) {
    return Auth::hasRole($role);
}

// Richiede login
function requireLogin() {
    Auth::requireLogin();
}

// Richiede ruolo specifico
function requireRole($role) {
    Auth::requireRole($role);
}

// Genera token CSRF
function csrfToken() {
    return Auth::generateCSRFToken();
}

// Campo hidden per CSRF
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}
?>