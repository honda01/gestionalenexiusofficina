<?php
/**
 * Script Logout - Gestionale Officina Moto
 * Gestisce la disconnessione dell'utente
 */

require_once '../includes/auth.php';

// Verifica che l'utente sia loggato
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Effettua il logout
Auth::logout();

// Il metodo logout() già reindirizza, ma per sicurezza:
header('Location: ../login.php?success=logout');
exit();
?>