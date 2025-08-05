<?php
/**
 * Pagina di Login - Gestionale Officina Moto
 */

require_once 'includes/auth.php';

// Se l'utente è già loggato, reindirizza alla dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Gestione del form di login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validazione CSRF
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido!';
    } elseif (empty($email) || empty($password)) {
        $error = 'Inserisci email e password!';
    } elseif (!validateEmail($email)) {
        $error = 'Formato email non valido!';
    } else {
        // Tentativo di login
        if (Auth::login($email, $password)) {
            // Login riuscito
            if ($remember) {
                // Imposta cookie per "ricordami" (30 giorni)
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            header('Location: dashboard.php?success=login');
            exit();
        } else {
            $error = 'Credenziali non valide!';
        }
    }
}

// Recupera email salvata se presente
$rememberedEmail = $_COOKIE['remember_email'] ?? '';

$pageTitle = 'Login';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Gestionale Officina Moto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            padding: 2rem;
        }
        
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-primary);
        }
        
        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .login-form {
            margin-bottom: 2rem;
        }
        
        .login-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .login-form .form-control {
            padding: 1rem;
            font-size: 1rem;
            border-radius: var(--border-radius);
            background-color: var(--bg-secondary);
            border: 2px solid var(--border-color);
            transition: var(--transition);
        }
        
        .login-form .form-control:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            background-color: var(--bg-tertiary);
        }
        
        .login-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            border: none;
            border-radius: var(--border-radius);
            color: white;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--accent-dark), var(--accent-primary));
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
        }
        
        .forgot-password {
            color: var(--accent-primary);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .forgot-password:hover {
            color: var(--accent-secondary);
            text-decoration: underline;
        }
        
        .demo-credentials {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1.5rem;
        }
        
        .demo-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .demo-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .demo-email {
            color: var(--accent-light);
        }
        
        .login-footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }
        
        @media (max-width: 575px) {
            .login-container {
                padding: 1rem;
            }
            
            .login-card {
                padding: 2rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .login-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🏍️</div>
                <h1 class="login-title">Gestionale Officina</h1>
                <p class="login-subtitle">Accedi al sistema di gestione</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form" id="loginForm">
                <?php echo csrfField(); ?>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="Inserisci la tua email"
                        value="<?php echo htmlspecialchars($rememberedEmail); ?>"
                        required
                        autocomplete="email"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Inserisci la tua password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?php echo $rememberedEmail ? 'checked' : ''; ?>>
                        Ricordami
                    </label>
                    <a href="#" class="forgot-password">Password dimenticata?</a>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">
                    Accedi
                </button>
            </form>
            
            <!-- Credenziali demo -->
            <div class="demo-credentials">
                <div class="demo-title">🔑 Credenziali Demo</div>
                <div class="demo-item">
                    <span>Admin:</span>
                    <span class="demo-email">admin@officina.com</span>
                </div>
                <div class="demo-item">
                    <span>Meccanico:</span>
                    <span class="demo-email">marco@officina.com</span>
                </div>
                <div class="demo-item">
                    <span>Reception:</span>
                    <span class="demo-email">laura@officina.com</span>
                </div>
                <div class="demo-item">
                    <span>Password:</span>
                    <span class="demo-email">password</span>
                </div>
            </div>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Gestionale Officina Moto</p>
                <p>Sistema di gestione completo per officine</p>
            </div>
        </div>
    </div>
    
    <script>
        // Gestione form login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Validazione client-side
            if (!email || !password) {
                e.preventDefault();
                alert('Inserisci email e password!');
                return;
            }
            
            if (!email.includes('@')) {
                e.preventDefault();
                alert('Inserisci un indirizzo email valido!');
                return;
            }
            
            // Mostra loading
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner"></span> Accesso in corso...';
        });
        
        // Auto-fill credenziali demo al click
        document.querySelectorAll('.demo-email').forEach(function(element, index) {
            if (index < 3) { // Solo per le email, non per la password
                element.style.cursor = 'pointer';
                element.addEventListener('click', function() {
                    document.getElementById('email').value = this.textContent;
                    document.getElementById('password').value = 'password';
                    document.getElementById('password').focus();
                });
            }
        });
        
        // Focus automatico sul primo campo vuoto
        window.addEventListener('load', function() {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (!emailField.value) {
                emailField.focus();
            } else {
                passwordField.focus();
            }
        });
        
        // Gestione Enter per passare al campo successivo
        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>