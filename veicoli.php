<?php
/**
 * Gestione Veicoli - Gestionale Officina Moto
 */

require_once 'includes/header.php';
requireLogin();

$pageTitle = 'Gestione Veicoli';
$action = $_GET['action'] ?? 'list';
$veicoloId = $_GET['id'] ?? null;
$clienteId = $_GET['cliente_id'] ?? null;
$error = '';
$success = '';

$db = getDB();

// Gestione azioni
switch ($action) {
    case 'add':
    case 'edit':
        // Carica lista clienti per il form
        $clienti = $db->select("SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome");
        
        // Gestione form di aggiunta/modifica
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $error = 'Token di sicurezza non valido!';
                break;
            }
            
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $marca = sanitize($_POST['marca'] ?? '');
            $modello = sanitize($_POST['modello'] ?? '');
            $targa = sanitize($_POST['targa'] ?? '');
            $anno = intval($_POST['anno'] ?? 0);
            $cilindrata = intval($_POST['cilindrata'] ?? 0);
            $colore = sanitize($_POST['colore'] ?? '');
            $numero_telaio = sanitize($_POST['numero_telaio'] ?? '');
            $note = sanitize($_POST['note'] ?? '');
            
            // Validazione
            if (empty($marca) || empty($modello) || empty($targa) || $cliente_id <= 0) {
                $error = 'Marca, modello, targa e cliente sono obbligatori!';
            } elseif ($anno > 0 && ($anno < 1900 || $anno > date('Y') + 1)) {
                $error = 'Anno non valido!';
            } else {
                try {
                    // Verifica che il cliente esista
                    $clienteExists = $db->count("SELECT COUNT(*) FROM clienti WHERE id = ?", [$cliente_id]);
                    if (!$clienteExists) {
                        $error = 'Cliente selezionato non valido!';
                    } else {
                        // Verifica unicità targa (escludendo il veicolo corrente se in modifica)
                        $targaQuery = "SELECT COUNT(*) FROM veicoli WHERE targa = ?";
                        $targaParams = [$targa];
                        
                        if ($action === 'edit' && $veicoloId) {
                            $targaQuery .= " AND id != ?";
                            $targaParams[] = $veicoloId;
                        }
                        
                        $targaExists = $db->count($targaQuery, $targaParams);
                        if ($targaExists) {
                            $error = 'Esiste già un veicolo con questa targa!';
                        } else {
                            if ($action === 'add') {
                                // Inserimento nuovo veicolo
                                $query = "INSERT INTO veicoli (cliente_id, marca, modello, targa, anno, cilindrata, colore, numero_telaio, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $params = [$cliente_id, $marca, $modello, $targa, $anno ?: null, $cilindrata ?: null, $colore, $numero_telaio, $note];
                                $result = $db->execute($query, $params);
                                
                                if ($result) {
                                    header('Location: veicoli.php?success=add');
                                    exit();
                                } else {
                                    $error = 'Errore durante l\'inserimento del veicolo!';
                                }
                            } else {
                                // Aggiornamento veicolo esistente
                                $query = "UPDATE veicoli SET cliente_id = ?, marca = ?, modello = ?, targa = ?, anno = ?, cilindrata = ?, colore = ?, numero_telaio = ?, note = ? WHERE id = ?";
                                $params = [$cliente_id, $marca, $modello, $targa, $anno ?: null, $cilindrata ?: null, $colore, $numero_telaio, $note, $veicoloId];
                                $result = $db->execute($query, $params);
                                
                                if ($result) {
                                    header('Location: veicoli.php?success=update');
                                    exit();
                                } else {
                                    $error = 'Errore durante l\'aggiornamento del veicolo!';
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore database: ' . $e->getMessage();
                }
            }
        }
        
        // Carica dati veicolo per modifica
        $veicolo = null;
        if ($action === 'edit' && $veicoloId) {
            $veicolo = $db->select("SELECT * FROM veicoli WHERE id = ?", [$veicoloId]);
            $veicolo = $veicolo ? $veicolo[0] : null;
            if (!$veicolo) {
                header('Location: veicoli.php?error=not_found');
                exit();
            }
        }
        break;
        
    case 'delete':
        if ($veicoloId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: veicoli.php?error=csrf');
                exit();
            }
            
            try {
                // Verifica se il veicolo ha interventi associati
                $interventiCount = $db->count("SELECT COUNT(*) FROM interventi WHERE veicolo_id = ?", [$veicoloId]);
                
                if ($interventiCount > 0) {
                    header('Location: veicoli.php?error=has_interventions');
                    exit();
                }
                
                $result = $db->execute("DELETE FROM veicoli WHERE id = ?", [$veicoloId]);
                
                if ($result) {
                    header('Location: veicoli.php?success=delete');
                    exit();
                } else {
                    header('Location: veicoli.php?error=delete_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: veicoli.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'view':
        // Visualizzazione dettagli veicolo
        if ($veicoloId) {
            $veicolo = $db->select("
                SELECT v.*, c.nome, c.cognome, c.telefono, c.email
                FROM veicoli v
                JOIN clienti c ON v.cliente_id = c.id
                WHERE v.id = ?
            ", [$veicoloId]);
            $veicolo = $veicolo ? $veicolo[0] : null;
            
            if ($veicolo) {
                // Carica interventi del veicolo
                $interventi = $db->select("
                    SELECT *
                    FROM interventi
                    WHERE veicolo_id = ?
                    ORDER BY data_inizio DESC
                ", [$veicoloId]);
            } else {
                header('Location: veicoli.php?error=not_found');
                exit();
            }
        }
        break;
}

// Carica lista veicoli per la vista principale
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $cliente_filter = $_GET['cliente_filter'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $whereClause = '';
    $params = [];
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(v.marca LIKE ? OR v.modello LIKE ? OR v.targa LIKE ? OR CONCAT(c.nome, ' ', c.cognome) LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($cliente_filter)) {
        $conditions[] = "v.cliente_id = ?";
        $params[] = $cliente_filter;
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
    
    // Conta totale veicoli
    $totalVeicoli = $db->count("
        SELECT COUNT(*)
        FROM veicoli v
        JOIN clienti c ON v.cliente_id = c.id
        $whereClause
    ", $params);
    $totalPages = ceil($totalVeicoli / $limit);
    
    // Carica veicoli con paginazione
    $veicoli = $db->select("
        SELECT v.*, 
               c.nome, c.cognome,
               COUNT(i.id) as interventi_count,
               MAX(i.data_inizio) as ultimo_intervento
        FROM veicoli v
        JOIN clienti c ON v.cliente_id = c.id
        LEFT JOIN interventi i ON v.id = i.veicolo_id
        $whereClause
        GROUP BY v.id
        ORDER BY v.marca, v.modello
        LIMIT $limit OFFSET $offset
    ", $params);
    
    // Carica clienti per filtro
    $clientiFilter = $db->select("SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome");
}
?>

<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <!-- Lista Veicoli -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-primary mb-2">🏍️ Gestione Veicoli</h1>
                    <p class="text-secondary">Gestisci i veicoli dei clienti</p>
                </div>
                <div>
                    <a href="veicoli.php?action=add" class="btn btn-primary">
                        <span>➕</span> Nuovo Veicolo
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
                        <div class="col-md-4">
                            <label for="search" class="form-label">Ricerca Veicolo</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Marca, modello, targa o cliente..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="cliente_filter" class="form-label">Filtra per Cliente</label>
                            <select id="cliente_filter" name="cliente_filter" class="form-control">
                                <option value="">Tutti i clienti</option>
                                <?php foreach ($clientiFilter as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" 
                                        <?php echo $cliente_filter == $cliente['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['cognome'] . ' ' . $cliente['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">🔍 Cerca</button>
                            <?php if (!empty($search) || !empty($cliente_filter)): ?>
                            <a href="veicoli.php" class="btn btn-secondary">✖️ Reset</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-right">
                            <small class="text-muted">
                                Totale: <?php echo $totalVeicoli; ?> veicoli
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Griglia veicoli -->
    <div class="row">
        <div class="col-12">
            <?php if (!empty($veicoli)): ?>
            <div class="table-container">
                <table class="data-table" id="veicoliTable">
                    <thead>
                        <tr>
                            <th>Targa</th>
                            <th>Veicolo</th>
                            <th>Proprietario</th>
                            <th>Anno</th>
                            <th>Cilindrata</th>
                            <th>Colore</th>
                            <th>Interventi</th>
                            <th>Ultimo Intervento</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($veicoli as $veicolo): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($veicolo['targa']); ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($veicolo['marca'] . ' ' . $veicolo['modello']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <a href="clienti.php?action=view&id=<?php echo $veicolo['cliente_id']; ?>" class="text-primary">
                                    <?php echo htmlspecialchars($veicolo['nome'] . ' ' . $veicolo['cognome']); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo $veicolo['anno'] ?: '-'; ?>
                            </td>
                            <td>
                                <?php echo $veicolo['cilindrata'] ? $veicolo['cilindrata'] . 'cc' : '-'; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($veicolo['colore']) ?: '-'; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $veicolo['interventi_count'] > 0 ? 'badge-info' : 'badge-secondary'; ?>">
                                    <?php echo $veicolo['interventi_count']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($veicolo['ultimo_intervento']): ?>
                                    <small><?php echo date('d/m/Y', strtotime($veicolo['ultimo_intervento'])); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Nessuno</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <div class="dropdown">
                                        <button class="btn btn-outline btn-sm" type="button" data-toggle="dropdown">
                                            ⋮
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="veicoli.php?action=view&id=<?php echo $veicolo['id']; ?>">
                                                👁️ Visualizza
                                            </a>
                                            <a class="dropdown-item" href="veicoli.php?action=edit&id=<?php echo $veicolo['id']; ?>">
                                                ✏️ Modifica
                                            </a>
                                            <a class="dropdown-item" href="interventi.php?action=add&veicolo_id=<?php echo $veicolo['id']; ?>">
                                                🔧 Nuovo Intervento
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($veicolo['interventi_count'] == 0): ?>
                                            <a class="dropdown-item text-danger" href="#" 
                                               onclick="deleteVeicolo(<?php echo $veicolo['id']; ?>)">
                                                🗑️ Elimina
                                            </a>
                                            <?php else: ?>
                                            <span class="dropdown-item text-muted">
                                                🔒 Non eliminabile (ha interventi)
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
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
                            <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
                                ← Precedente
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
                                Successiva →
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-3" style="font-size: 4rem; opacity: 0.3;">🏍️</div>
                    <h3 class="text-muted">Nessun veicolo trovato</h3>
                    <p class="text-secondary">Non ci sono veicoli che corrispondono ai criteri di ricerca.</p>
                    <a href="veicoli.php?action=add" class="btn btn-primary">➕ Aggiungi il primo veicolo</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Aggiunta/Modifica Veicolo -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        <?php echo $action === 'add' ? '➕ Nuovo Veicolo' : '✏️ Modifica Veicolo'; ?>
                    </h1>
                    <p class="text-secondary">
                        <?php echo $action === 'add' ? 'Aggiungi un nuovo veicolo' : 'Modifica i dati del veicolo'; ?>
                    </p>
                </div>
                <div>
                    <a href="veicoli.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dati Veicolo</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-error mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate id="veicoloForm">
                        <?php echo csrfField(); ?>
                        
                        <div class="form-group">
                            <label for="cliente_id" class="form-label">Proprietario *</label>
                            <select id="cliente_id" name="cliente_id" class="form-control" required>
                                <option value="">Seleziona cliente...</option>
                                <?php foreach ($clienti as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" 
                                        <?php echo (($veicolo['cliente_id'] ?? $clienteId) == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['cognome'] . ' ' . $cliente['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="marca" class="form-label">Marca *</label>
                                    <input type="text" id="marca" name="marca" class="form-control" 
                                           value="<?php echo htmlspecialchars($veicolo['marca'] ?? ''); ?>" 
                                           placeholder="es. Honda, Yamaha, Ducati..." required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modello" class="form-label">Modello *</label>
                                    <input type="text" id="modello" name="modello" class="form-control" 
                                           value="<?php echo htmlspecialchars($veicolo['modello'] ?? ''); ?>" 
                                           placeholder="es. CBR600RR, MT-07, Panigale..." required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="targa" class="form-label">Targa *</label>
                                    <input type="text" id="targa" name="targa" class="form-control" 
                                           value="<?php echo htmlspecialchars($veicolo['targa'] ?? ''); ?>" 
                                           placeholder="es. AB123CD" required style="text-transform: uppercase;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="anno" class="form-label">Anno</label>
                                    <input type="number" id="anno" name="anno" class="form-control" 
                                           value="<?php echo $veicolo['anno'] ?? ''; ?>" 
                                           min="1900" max="<?php echo date('Y') + 1; ?>" 
                                           placeholder="es. 2020">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cilindrata" class="form-label">Cilindrata (cc)</label>
                                    <input type="number" id="cilindrata" name="cilindrata" class="form-control" 
                                           value="<?php echo $veicolo['cilindrata'] ?? ''; ?>" 
                                           min="50" max="2000" 
                                           placeholder="es. 600">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="colore" class="form-label">Colore</label>
                                    <input type="text" id="colore" name="colore" class="form-control" 
                                           value="<?php echo htmlspecialchars($veicolo['colore'] ?? ''); ?>" 
                                           placeholder="es. Rosso, Nero, Bianco...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_telaio" class="form-label">Numero Telaio</label>
                            <input type="text" id="numero_telaio" name="numero_telaio" class="form-control" 
                                   value="<?php echo htmlspecialchars($veicolo['numero_telaio'] ?? ''); ?>" 
                                   placeholder="Numero di telaio del veicolo">
                        </div>
                        
                        <div class="form-group">
                            <label for="note" class="form-label">Note</label>
                            <textarea id="note" name="note" class="form-control" rows="3" 
                                      placeholder="Note aggiuntive sul veicolo, modifiche, accessori..."><?php echo htmlspecialchars($veicolo['note'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '💾 Salva Veicolo' : '💾 Aggiorna Veicolo'; ?>
                            </button>
                            <a href="veicoli.php" class="btn btn-secondary">❌ Annulla</a>
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
                        <li class="mb-2">🏷️ La targa deve essere univoca nel sistema</li>
                        <li class="mb-2">📅 L'anno aiuta per identificare la versione del modello</li>
                        <li class="mb-2">⚙️ La cilindrata è utile per ordinare ricambi</li>
                        <li class="mb-2">🔧 Usa le note per modifiche, accessori o particolarità</li>
                        <li class="mb-2">🔍 Il numero telaio è utile per identificazione univoca</li>
                    </ul>
                </div>
            </div>
            
            <?php if ($action === 'add' && !empty($clienteId)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">👤 Cliente Selezionato</h3>
                </div>
                <div class="card-body">
                    <?php 
                    $clienteSelezionato = null;
                    foreach ($clienti as $c) {
                        if ($c['id'] == $clienteId) {
                            $clienteSelezionato = $c;
                            break;
                        }
                    }
                    if ($clienteSelezionato): ?>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($clienteSelezionato['cognome'] . ' ' . $clienteSelezionato['nome']); ?></strong>
                    </p>
                    <small class="text-muted">Cliente preselezionato</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $veicolo): ?>
    <!-- Visualizzazione Dettagli Veicolo -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        🏍️ <?php echo htmlspecialchars($veicolo['marca'] . ' ' . $veicolo['modello']); ?>
                    </h1>
                    <p class="text-secondary">
                        Targa: <strong><?php echo htmlspecialchars($veicolo['targa']); ?></strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="interventi.php?action=add&veicolo_id=<?php echo $veicolo['id']; ?>" class="btn btn-primary">🔧 Nuovo Intervento</a>
                    <a href="veicoli.php?action=edit&id=<?php echo $veicolo['id']; ?>" class="btn btn-outline">✏️ Modifica</a>
                    <a href="veicoli.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Informazioni Veicolo -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏍️ Dati Tecnici</h3>
                </div>
                <div class="card-body">
                    <div class="vehicle-details">
                        <div class="detail-item">
                            <strong>Marca:</strong>
                            <span><?php echo htmlspecialchars($veicolo['marca']); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Modello:</strong>
                            <span><?php echo htmlspecialchars($veicolo['modello']); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Targa:</strong>
                            <span class="vehicle-plate-display"><?php echo htmlspecialchars($veicolo['targa']); ?></span>
                        </div>
                        
                        <?php if ($veicolo['anno']): ?>
                        <div class="detail-item">
                            <strong>Anno:</strong>
                            <span><?php echo $veicolo['anno']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($veicolo['cilindrata']): ?>
                        <div class="detail-item">
                            <strong>Cilindrata:</strong>
                            <span><?php echo $veicolo['cilindrata']; ?>cc</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($veicolo['colore']): ?>
                        <div class="detail-item">
                            <strong>Colore:</strong>
                            <span><?php echo htmlspecialchars($veicolo['colore']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($veicolo['numero_telaio']): ?>
                        <div class="detail-item">
                            <strong>Numero Telaio:</strong>
                            <span><?php echo htmlspecialchars($veicolo['numero_telaio']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <strong>Registrato il:</strong>
                            <span><?php echo date('d/m/Y', strtotime($veicolo['data_creazione'])); ?></span>
                        </div>
                        
                        <?php if ($veicolo['note']): ?>
                        <div class="detail-item">
                            <strong>Note:</strong>
                            <span><?php echo nl2br(htmlspecialchars($veicolo['note'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informazioni Proprietario -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">👤 Proprietario</h3>
                </div>
                <div class="card-body">
                    <div class="owner-info">
                        <div class="detail-item">
                            <strong>Nome:</strong>
                            <span>
                                <a href="clienti.php?action=view&id=<?php echo $veicolo['cliente_id']; ?>" class="text-primary">
                                    <?php echo htmlspecialchars($veicolo['nome'] . ' ' . $veicolo['cognome']); ?>
                                </a>
                            </span>
                        </div>
                        
                        <?php if ($veicolo['telefono']): ?>
                        <div class="detail-item">
                            <strong>📞 Telefono:</strong>
                            <span>
                                <a href="tel:<?php echo htmlspecialchars($veicolo['telefono']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($veicolo['telefono']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($veicolo['email']): ?>
                        <div class="detail-item">
                            <strong>📧 Email:</strong>
                            <span>
                                <a href="mailto:<?php echo htmlspecialchars($veicolo['email']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($veicolo['email']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="clienti.php?action=view&id=<?php echo $veicolo['cliente_id']; ?>" class="btn btn-outline btn-sm">
                            👁️ Vedi dettagli cliente
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Storico Interventi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">🔧 Storico Interventi (<?php echo count($interventi); ?>)</h3>
                        <a href="interventi.php?action=add&veicolo_id=<?php echo $veicolo['id']; ?>" class="btn btn-primary">
                            ➕ Nuovo Intervento
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($interventi)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Data Inizio</th>
                                    <th>Descrizione</th>
                                    <th>Stato</th>
                                    <th>Costo</th>
                                    <th>Data Fine</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interventi as $intervento): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($intervento['data_inizio'])); ?></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($intervento['descrizione']); ?>">
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
                                        <?php if ($intervento['data_fine']): ?>
                                        <?php echo date('d/m/Y', strtotime($intervento['data_fine'])); ?>
                                        <?php else: ?>
                                        <span class="text-muted">In corso</span>
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
                    <?php else: ?>
                    <div class="text-center py-4">
                        <div class="mb-3" style="font-size: 3rem; opacity: 0.3;">🔧</div>
                        <p class="text-muted">Nessun intervento registrato per questo veicolo</p>
                        <a href="interventi.php?action=add&veicolo_id=<?php echo $veicolo['id']; ?>" class="btn btn-primary">
                            ➕ Crea primo intervento
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Form nascosto per eliminazione -->
<form id="deleteForm" method="POST" style="display: none;">
    <?php echo csrfField(); ?>
</form>

<style>
.vehicle-card {
    transition: var(--transition);
    border: 1px solid var(--border-color);
}

.vehicle-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: var(--accent-primary);
}

.vehicle-plate {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 0.9rem;
    letter-spacing: 1px;
    border: 2px solid #444;
    display: inline-block;
    margin-top: 0.25rem;
}

.vehicle-plate-display {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 1.1rem;
    letter-spacing: 2px;
    border: 3px solid #444;
    display: inline-block;
}

.vehicle-info .info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.vehicle-info .info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 500;
    color: var(--text-secondary);
    min-width: 100px;
}

.info-value {
    color: var(--text-primary);
    text-align: right;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item strong {
    color: var(--text-secondary);
    min-width: 120px;
    flex-shrink: 0;
}

.detail-item span {
    color: var(--text-primary);
    text-align: right;
    flex: 1;
    margin-left: 1rem;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    min-width: 180px;
    margin-top: 0.25rem;
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 0.5rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    transition: var(--transition);
}

.dropdown-item:hover {
    background: var(--bg-tertiary);
    color: var(--accent-primary);
}

.dropdown-item.text-danger:hover {
    background: var(--accent-primary);
    color: white;
}

.dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0.25rem 0;
}

@media (max-width: 768px) {
    .vehicle-info .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .info-value {
        text-align: left;
    }
    
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .detail-item span {
        text-align: left;
        margin-left: 0;
    }
}
</style>

<script>
// Funzione per eliminare veicolo
function deleteVeicolo(id) {
    if (confirm('Sei sicuro di voler eliminare questo veicolo?\n\nATTENZIONE: Questa azione non può essere annullata!')) {
        const form = document.getElementById('deleteForm');
        form.action = `veicoli.php?action=delete&id=${id}`;
        form.submit();
    }
}

// Gestione dropdown
document.addEventListener('DOMContentLoaded', function() {
    // Gestione dropdown menu
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-toggle="dropdown"]')) {
            e.preventDefault();
            const dropdown = e.target.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            
            // Chiudi tutti gli altri dropdown
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            
            // Toggle del dropdown corrente
            menu.classList.toggle('show');
        } else {
            // Chiudi tutti i dropdown se si clicca fuori
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
            });
        }
    });
    
    // Auto-uppercase per targa
    const targaField = document.getElementById('targa');
    if (targaField) {
        targaField.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Auto-focus sul campo ricerca se presente
    const searchField = document.getElementById('search');
    if (searchField && !searchField.value) {
        searchField.focus();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>