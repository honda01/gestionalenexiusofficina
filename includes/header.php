<?php
require_once 'auth.php';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Gestionale Officina Moto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Header con navigazione -->
    <header class="main-header">
        <div class="header-container">
            <div class="logo">
                <h1><span class="logo-icon">🏍️</span> Officina Moto</h1>
            </div>
            
            <!-- Menu di navigazione -->
            <nav class="main-nav">
                <ul class="nav-list">
                    <li class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <a href="dashboard.php" class="nav-link">
                            <span class="nav-icon">📊</span>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage === 'clienti' ? 'active' : ''; ?>">
                        <a href="clienti.php" class="nav-link">
                            <span class="nav-icon">👥</span>
                            <span class="nav-text">Clienti</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage === 'veicoli' ? 'active' : ''; ?>">
                        <a href="veicoli.php" class="nav-link">
                            <span class="nav-icon">🏍️</span>
                            <span class="nav-text">Veicoli</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage === 'interventi' ? 'active' : ''; ?>">
                        <a href="interventi.php" class="nav-link">
                            <span class="nav-icon">🔧</span>
                            <span class="nav-text">Interventi</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage === 'magazzino' ? 'active' : ''; ?>">
                        <a href="magazzino.php" class="nav-link">
                            <span class="nav-icon">📦</span>
                            <span class="nav-text">Magazzino</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage === 'appuntamenti' ? 'active' : ''; ?>">
                        <a href="appuntamenti.php" class="nav-link">
                            <span class="nav-icon">📅</span>
                            <span class="nav-text">Appuntamenti</span>
                        </a>
                    </li>
                    
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item <?php echo $currentPage === 'utenti' ? 'active' : ''; ?>">
                        <a href="utenti.php" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span class="nav-text">Utenti</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <!-- Menu utente -->
            <div class="user-menu">
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
                    <span class="user-role"><?php echo Auth::getRoleName($currentUser['role']); ?></span>
                </div>
                <div class="user-actions">
                    <a href="profilo.php" class="btn btn-sm btn-outline">Profilo</a>
                    <a href="scripts/logout.php" class="btn btn-sm btn-danger">Logout</a>
                </div>
            </div>
            
            <!-- Menu mobile toggle -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
    
    <!-- Menu mobile -->
    <nav class="mobile-nav" id="mobileNav">
        <ul class="mobile-nav-list">
            <li><a href="dashboard.php" class="mobile-nav-link">📊 Dashboard</a></li>
            <li><a href="clienti.php" class="mobile-nav-link">👥 Clienti</a></li>
            <li><a href="veicoli.php" class="mobile-nav-link">🏍️ Veicoli</a></li>
            <li><a href="interventi.php" class="mobile-nav-link">🔧 Interventi</a></li>
            <li><a href="magazzino.php" class="mobile-nav-link">📦 Magazzino</a></li>
            <li><a href="appuntamenti.php" class="mobile-nav-link">📅 Appuntamenti</a></li>
            <?php if (hasRole('admin')): ?>
            <li><a href="utenti.php" class="mobile-nav-link">⚙️ Utenti</a></li>
            <?php endif; ?>
            <li class="mobile-nav-divider"></li>
            <li><a href="profilo.php" class="mobile-nav-link">👤 Profilo</a></li>
            <li><a href="scripts/logout.php" class="mobile-nav-link logout">🚪 Logout</a></li>
        </ul>
    </nav>
    <?php endif; ?>
    
    <!-- Contenuto principale -->
    <main class="main-content <?php echo isLoggedIn() ? 'with-header' : 'login-page'; ?>">
        
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php 
            $successMessages = [
                'login' => 'Login effettuato con successo!',
                'logout' => 'Logout effettuato con successo!',
                'save' => 'Dati salvati con successo!',
                'delete' => 'Elemento eliminato con successo!',
                'update' => 'Aggiornamento completato con successo!'
            ];
            echo isset($successMessages[$_GET['success']]) ? $successMessages[$_GET['success']] : 'Operazione completata con successo!';
            ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php 
            $errorMessages = [
                'login' => 'Credenziali non valide!',
                'access_denied' => 'Accesso negato! Non hai i permessi necessari.',
                'invalid_data' => 'Dati non validi!',
                'database_error' => 'Errore del database!',
                'csrf' => 'Token di sicurezza non valido!'
            ];
            echo isset($errorMessages[$_GET['error']]) ? $errorMessages[$_GET['error']] : 'Si è verificato un errore!';
            ?>
        </div>
        <?php endif; ?>
        
    <script>
    // Toggle menu mobile
    function toggleMobileMenu() {
        const mobileNav = document.getElementById('mobileNav');
        const toggle = document.querySelector('.mobile-menu-toggle');
        
        mobileNav.classList.toggle('active');
        toggle.classList.toggle('active');
    }
    
    // Chiudi menu mobile quando si clicca su un link
    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('mobileNav').classList.remove('active');
            document.querySelector('.mobile-menu-toggle').classList.remove('active');
        });
    });
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
    </script>