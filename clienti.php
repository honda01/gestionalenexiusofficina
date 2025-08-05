<?php
/**
 * Gestione Clienti - Gestionale Officina Moto
 */

require_once 'includes/header.php';
requireLogin();

$pageTitle = 'Gestione Clienti';
$action = $_GET['action'] ?? 'list';
$clienteId = $_GET['id'] ?? null;
$error = '';
$success = '';

$db = getDB();

// Gestione azioni
switch ($action) {
    case 'add':
    case 'edit':
        // Gestione form di aggiunta/modifica
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $error = 'Token di sicurezza non valido!';
                break;
            }
            
            $nome = sanitize($_POST['nome'] ?? '');
            $cognome = sanitize($_POST['cognome'] ?? '');
            $telefono = sanitize($_POST['telefono'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $indirizzo = sanitize($_POST['indirizzo'] ?? '');
            $note = sanitize($_POST['note'] ?? '');
            
            // Validazione
            if (empty($nome) || empty($cognome)) {
                $error = 'Nome e cognome sono obbligatori!';
            } elseif (!empty($email) && !validateEmail($email)) {
                $error = 'Formato email non valido!';
            } else {
                try {
                    if ($action === 'add') {
                        // Inserimento nuovo cliente
                        $query = "INSERT INTO clienti (nome, cognome, telefono, email, indirizzo, note) VALUES (?, ?, ?, ?, ?, ?)";
                        $result = $db->execute($query, [$nome, $cognome, $telefono, $email, $indirizzo, $note]);
                        
                        if ($result) {
                            header('Location: clienti.php?success=add');
                            exit();
                        } else {
                            $error = 'Errore durante l\'inserimento del cliente!';
                        }
                    } else {
                        // Aggiornamento cliente esistente
                        $query = "UPDATE clienti SET nome = ?, cognome = ?, telefono = ?, email = ?, indirizzo = ?, note = ? WHERE id = ?";
                        $result = $db->execute($query, [$nome, $cognome, $telefono, $email, $indirizzo, $note, $clienteId]);
                        
                        if ($result) {
                            header('Location: clienti.php?success=update');
                            exit();
                        } else {
                            $error = 'Errore durante l\'aggiornamento del cliente!';
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore database: ' . $e->getMessage();
                }
            }
        }
        
        // Carica dati cliente per modifica
        $cliente = null;
        if ($action === 'edit' && $clienteId) {
            $cliente = $db->select("SELECT * FROM clienti WHERE id = ?", [$clienteId]);
            $cliente = $cliente ? $cliente[0] : null;
            if (!$cliente) {
                header('Location: clienti.php?error=not_found');
                exit();
            }
        }
        break;
        
    case 'delete':
        if ($clienteId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: clienti.php?error=csrf');
                exit();
            }
            
            try {
                // Verifica se il cliente ha veicoli associati
                $veicoliCount = $db->count("SELECT COUNT(*) FROM veicoli WHERE cliente_id = ?", [$clienteId]);
                
                if ($veicoliCount > 0) {
                    header('Location: clienti.php?error=has_vehicles');
                    exit();
                }
                
                $result = $db->execute("DELETE FROM clienti WHERE id = ?", [$clienteId]);
                
                if ($result) {
                    header('Location: clienti.php?success=delete');
                    exit();
                } else {
                    header('Location: clienti.php?error=delete_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: clienti.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'view':
        // Visualizzazione dettagli cliente
        if ($clienteId) {
            $cliente = $db->select("SELECT * FROM clienti WHERE id = ?", [$clienteId]);
            $cliente = $cliente ? $cliente[0] : null;
            
            if ($cliente) {
                // Carica veicoli del cliente
                $veicoli = $db->select("SELECT * FROM veicoli WHERE cliente_id = ? ORDER BY marca, modello", [$clienteId]);
                
                // Carica interventi del cliente
                $interventi = $db->select("
                    SELECT i.*, v.marca, v.modello, v.targa
                    FROM interventi i
                    JOIN veicoli v ON i.veicolo_id = v.id
                    WHERE v.cliente_id = ?
                    ORDER BY i.data_inizio DESC
                    LIMIT 10
                ", [$clienteId]);
            } else {
                header('Location: clienti.php?error=not_found');
                exit();
            }
        }
        break;
}

// Carica lista clienti per la vista principale
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE nome LIKE ? OR cognome LIKE ? OR email LIKE ? OR telefono LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    // Conta totale clienti
    $totalClienti = $db->count("SELECT COUNT(*) FROM clienti $whereClause", $params);
    $totalPages = ceil($totalClienti / $limit);
    
    // Carica clienti con paginazione
    $clienti = $db->select("
        SELECT c.*, 
               COUNT(v.id) as veicoli_count,
               COUNT(DISTINCT i.id) as interventi_count
        FROM clienti c
        LEFT JOIN veicoli v ON c.id = v.cliente_id
        LEFT JOIN interventi i ON v.id = i.veicolo_id
        $whereClause
        GROUP BY c.id
        ORDER BY c.cognome, c.nome
        LIMIT $limit OFFSET $offset
    ", $params);
}
?>

<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <!-- Lista Clienti -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-primary mb-2">👥 Gestione Clienti</h1>
                    <p class="text-secondary">Gestisci i clienti dell'officina</p>
                </div>
                <div>
                    <a href="clienti.php?action=add" class="btn btn-primary">
                        <span>➕</span> Nuovo Cliente
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtri e ricerca -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Ricerca Cliente</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Nome, cognome, email o telefono..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">🔍 Cerca</button>
                            <?php if (!empty($search)): ?>
                            <a href="clienti.php" class="btn btn-secondary">✖️ Reset</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 text-right">
                            <small class="text-muted">
                                Totale: <?php echo $totalClienti; ?> clienti
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabella clienti -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($clienti)): ?>
                    <div class="table-container">
                        <table class="data-table" id="clientiTable">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Contatti</th>
                                    <th>Veicoli</th>
                                    <th>Interventi</th>
                                    <th>Registrato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clienti as $cliente): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($cliente['nome'] . ' ' . $cliente['cognome']); ?></strong>
                                        </div>
                                        <?php if (!empty($cliente['note'])): ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars(substr($cliente['note'], 0, 50)) . (strlen($cliente['note']) > 50 ? '...' : ''); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($cliente['telefono'])): ?>
                                        <div><span>📞</span> <?php echo htmlspecialchars($cliente['telefono']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($cliente['email'])): ?>
                                        <div><span>📧</span> <?php echo htmlspecialchars($cliente['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $cliente['veicoli_count'] > 0 ? 'badge-info' : 'badge-secondary'; ?>">
                                            <?php echo $cliente['veicoli_count']; ?> veicoli
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $cliente['interventi_count'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $cliente['interventi_count']; ?> interventi
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($cliente['data_creazione'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="clienti.php?action=view&id=<?php echo $cliente['id']; ?>" 
                                               class="btn btn-outline" title="Visualizza">
                                                👁️
                                            </a>
                                            <a href="clienti.php?action=edit&id=<?php echo $cliente['id']; ?>" 
                                               class="btn btn-outline" title="Modifica">
                                                ✏️
                                            </a>
                                            <?php if ($cliente['veicoli_count'] == 0): ?>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="deleteCliente(<?php echo $cliente['id']; ?>)" 
                                                    title="Elimina">
                                                🗑️
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-secondary" 
                                                    title="Non eliminabile (ha veicoli)" disabled>
                                                🔒
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($totalPages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        ← Precedente
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">
                                        Successiva →
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-5">
                        <div class="mb-3" style="font-size: 4rem; opacity: 0.3;">👥</div>
                        <h3 class="text-muted">Nessun cliente trovato</h3>
                        <p class="text-secondary">Non ci sono clienti che corrispondono ai criteri di ricerca.</p>
                        <a href="clienti.php?action=add" class="btn btn-primary">➕ Aggiungi il primo cliente</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Aggiunta/Modifica Cliente -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        <?php echo $action === 'add' ? '➕ Nuovo Cliente' : '✏️ Modifica Cliente'; ?>
                    </h1>
                    <p class="text-secondary">
                        <?php echo $action === 'add' ? 'Aggiungi un nuovo cliente' : 'Modifica i dati del cliente'; ?>
                    </p>
                </div>
                <div>
                    <a href="clienti.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dati Cliente</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-error mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate id="clienteForm">
                        <?php echo csrfField(); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nome" class="form-label">Nome *</label>
                                    <input type="text" id="nome" name="nome" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['nome'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cognome" class="form-label">Cognome *</label>
                                    <input type="text" id="cognome" name="cognome" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['cognome'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telefono" class="form-label">Telefono</label>
                                    <input type="tel" id="telefono" name="telefono" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" 
                                           placeholder="+39 123 456 7890">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>" 
                                           placeholder="cliente@email.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="indirizzo" class="form-label">Indirizzo</label>
                            <textarea id="indirizzo" name="indirizzo" class="form-control" rows="2" 
                                      placeholder="Via, numero civico, città, CAP"><?php echo htmlspecialchars($cliente['indirizzo'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="note" class="form-label">Note</label>
                            <textarea id="note" name="note" class="form-control" rows="3" 
                                      placeholder="Note aggiuntive sul cliente..."><?php echo htmlspecialchars($cliente['note'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '💾 Salva Cliente' : '💾 Aggiorna Cliente'; ?>
                            </button>
                            <a href="clienti.php" class="btn btn-secondary">❌ Annulla</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💡 Suggerimenti</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">📝 I campi contrassegnati con * sono obbligatori</li>
                        <li class="mb-2">📞 Il telefono aiuta per le comunicazioni rapide</li>
                        <li class="mb-2">📧 L'email è utile per invio preventivi e comunicazioni</li>
                        <li class="mb-2">📍 L'indirizzo può essere utile per consegne a domicilio</li>
                        <li class="mb-2">📋 Usa le note per informazioni importanti sul cliente</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $cliente): ?>
    <!-- Visualizzazione Dettagli Cliente -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        👤 <?php echo htmlspecialchars($cliente['nome'] . ' ' . $cliente['cognome']); ?>
                    </h1>
                    <p class="text-secondary">Dettagli cliente e storico</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="clienti.php?action=edit&id=<?php echo $cliente['id']; ?>" class="btn btn-primary">✏️ Modifica</a>
                    <a href="clienti.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Informazioni Cliente -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Informazioni</h3>
                </div>
                <div class="card-body">
                    <div class="client-info">
                        <div class="info-item mb-3">
                            <strong>Nome Completo:</strong><br>
                            <span><?php echo htmlspecialchars($cliente['nome'] . ' ' . $cliente['cognome']); ?></span>
                        </div>
                        
                        <?php if (!empty($cliente['telefono'])): ?>
                        <div class="info-item mb-3">
                            <strong>📞 Telefono:</strong><br>
                            <a href="tel:<?php echo htmlspecialchars($cliente['telefono']); ?>" class="text-primary">
                                <?php echo htmlspecialchars($cliente['telefono']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($cliente['email'])): ?>
                        <div class="info-item mb-3">
                            <strong>📧 Email:</strong><br>
                            <a href="mailto:<?php echo htmlspecialchars($cliente['email']); ?>" class="text-primary">
                                <?php echo htmlspecialchars($cliente['email']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($cliente['indirizzo'])): ?>
                        <div class="info-item mb-3">
                            <strong>📍 Indirizzo:</strong><br>
                            <span><?php echo nl2br(htmlspecialchars($cliente['indirizzo'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item mb-3">
                            <strong>📅 Cliente dal:</strong><br>
                            <span><?php echo date('d/m/Y', strtotime($cliente['data_creazione'])); ?></span>
                        </div>
                        
                        <?php if (!empty($cliente['note'])): ?>
                        <div class="info-item">
                            <strong>📝 Note:</strong><br>
                            <span><?php echo nl2br(htmlspecialchars($cliente['note'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Veicoli del Cliente -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">🏍️ Veicoli (<?php echo count($veicoli); ?>)</h3>
                        <a href="veicoli.php?action=add&cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-primary btn-sm">
                            ➕ Aggiungi Veicolo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($veicoli)): ?>
                    <div class="row">
                        <?php foreach ($veicoli as $veicolo): ?>
                        <div class="col-12 col-md-6 mb-3">
                            <div class="card bg-secondary">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($veicolo['marca'] . ' ' . $veicolo['modello']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <strong>Targa:</strong> <?php echo htmlspecialchars($veicolo['targa']); ?><br>
                                        <?php if ($veicolo['anno']): ?>
                                        <strong>Anno:</strong> <?php echo $veicolo['anno']; ?><br>
                                        <?php endif; ?>
                                        <?php if ($veicolo['cilindrata']): ?>
                                        <strong>Cilindrata:</strong> <?php echo $veicolo['cilindrata']; ?>cc<br>
                                        <?php endif; ?>
                                        <?php if ($veicolo['colore']): ?>
                                        <strong>Colore:</strong> <?php echo htmlspecialchars($veicolo['colore']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex gap-2">
                                        <a href="veicoli.php?action=view&id=<?php echo $veicolo['id']; ?>" class="btn btn-outline btn-sm">
                                            👁️ Dettagli
                                        </a>
                                        <a href="interventi.php?action=add&veicolo_id=<?php echo $veicolo['id']; ?>" class="btn btn-primary btn-sm">
                                            🔧 Nuovo Intervento
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <div class="mb-3" style="font-size: 3rem; opacity: 0.3;">🏍️</div>
                        <p class="text-muted">Nessun veicolo registrato per questo cliente</p>
                        <a href="veicoli.php?action=add&cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-primary">
                            ➕ Aggiungi primo veicolo
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Storico Interventi -->
    <?php if (!empty($interventi)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔧 Storico Interventi (ultimi 10)</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Veicolo</th>
                                    <th>Descrizione</th>
                                    <th>Stato</th>
                                    <th>Costo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interventi as $intervento): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($intervento['data_inizio'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($intervento['marca'] . ' ' . $intervento['modello']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($intervento['targa']); ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($intervento['descrizione']); ?>">
                                            <?php echo htmlspecialchars($intervento['descrizione']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getStatoBadge($intervento['stato']); ?>">
                                            <?php echo getStatoText($intervento['stato']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($intervento['costo'] > 0): ?>
                                        <strong>€ <?php echo number_format($intervento['costo'], 2, ',', '.'); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="interventi.php?action=view&id=<?php echo $intervento['id']; ?>" 
                                           class="btn btn-outline btn-sm">
                                            👁️ Dettagli
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="interventi.php?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-outline">
                            Vedi tutti gli interventi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Form nascosto per eliminazione -->
<form id="deleteForm" method="POST" style="display: none;">
    <?php echo csrfField(); ?>
</form>

<style>
.pagination {
    display: flex;
    list-style: none;
    gap: 0.5rem;
    margin: 0;
    padding: 0;
}

.page-item {
    display: block;
}

.page-link {
    display: block;
    padding: 0.5rem 1rem;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    text-decoration: none;
    border-radius: var(--border-radius);
    transition: var(--transition);
}

.page-link:hover {
    background-color: var(--bg-tertiary);
    border-color: var(--accent-primary);
    color: var(--accent-primary);
}

.page-item.active .page-link {
    background-color: var(--accent-primary);
    border-color: var(--accent-primary);
    color: white;
}

.info-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.btn-group {
    display: flex;
    gap: 0.25rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}
</style>

<script>
// Funzione per eliminare cliente
function deleteCliente(id) {
    if (confirm('Sei sicuro di voler eliminare questo cliente?\n\nATTENZIONE: Questa azione non può essere annullata!')) {
        const form = document.getElementById('deleteForm');
        form.action = `clienti.php?action=delete&id=${id}`;
        form.submit();
    }
}

// Inizializza ricerca tabella
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sul campo ricerca se presente
    const searchField = document.getElementById('search');
    if (searchField && !searchField.value) {
        searchField.focus();
    }
});
</script>

<?php
// Funzioni helper per badge stato (se non già definite)
if (!function_exists('getStatoBadge')) {
    function getStatoBadge($stato) {
        switch ($stato) {
            case 'in_attesa': return 'badge-warning';
            case 'lavorazione': return 'badge-info';
            case 'completato': return 'badge-success';
            case 'consegnato': return 'badge-secondary';
            default: return 'badge-secondary';
        }
    }
}

if (!function_exists('getStatoText')) {
    function getStatoText($stato) {
        switch ($stato) {
            case 'in_attesa': return 'In Attesa';
            case 'lavorazione': return 'In Lavorazione';
            case 'completato': return 'Completato';
            case 'consegnato': return 'Consegnato';
            default: return ucfirst($stato);
        }
    }
}

require_once 'includes/footer.php';
?>