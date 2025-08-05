<?php
/**
 * Gestione Interventi - Gestionale Officina Moto
 */

require_once 'includes/header.php';
requireLogin();

$pageTitle = 'Gestione Interventi';
$action = $_GET['action'] ?? 'list';
$interventoId = $_GET['id'] ?? null;
$veicoloId = $_GET['veicolo_id'] ?? null;
$clienteId = $_GET['cliente_id'] ?? null;
$error = '';
$success = '';

$db = getDB();

// Gestione azioni
switch ($action) {
    case 'add':
    case 'edit':
        // Carica lista veicoli per il form
        $veicoli = $db->select("
            SELECT v.id, v.marca, v.modello, v.targa, c.nome, c.cognome
            FROM veicoli v
            JOIN clienti c ON v.cliente_id = c.id
            ORDER BY c.cognome, c.nome, v.marca, v.modello
        ");
        
        // Gestione form di aggiunta/modifica
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $error = 'Token di sicurezza non valido!';
                break;
            }
            
            $veicolo_id = intval($_POST['veicolo_id'] ?? 0);
            $descrizione = sanitize($_POST['descrizione'] ?? '');
            $stato = sanitize($_POST['stato'] ?? 'in_attesa');
            $data_inizio = sanitize($_POST['data_inizio'] ?? '');
            $data_fine = sanitize($_POST['data_fine'] ?? '');
            $costo = floatval($_POST['costo'] ?? 0);
            $note_tecniche = sanitize($_POST['note_tecniche'] ?? '');
            $ricambi_utilizzati = sanitize($_POST['ricambi_utilizzati'] ?? '');
            
            // Validazione
            if (empty($descrizione) || $veicolo_id <= 0) {
                $error = 'Descrizione e veicolo sono obbligatori!';
            } elseif (!empty($data_inizio) && !strtotime($data_inizio)) {
                $error = 'Data inizio non valida!';
            } elseif (!empty($data_fine) && !strtotime($data_fine)) {
                $error = 'Data fine non valida!';
            } elseif (!empty($data_inizio) && !empty($data_fine) && strtotime($data_fine) < strtotime($data_inizio)) {
                $error = 'La data fine non può essere precedente alla data inizio!';
            } elseif ($costo < 0) {
                $error = 'Il costo non può essere negativo!';
            } else {
                try {
                    // Verifica che il veicolo esista
                    $veicoloExists = $db->count("SELECT COUNT(*) FROM veicoli WHERE id = ?", [$veicolo_id]);
                    if (!$veicoloExists) {
                        $error = 'Veicolo selezionato non valido!';
                    } else {
                        // Imposta data inizio se non specificata
                        if (empty($data_inizio)) {
                            $data_inizio = date('Y-m-d');
                        }
                        
                        // Se stato è completato o consegnato, imposta data fine se non specificata
                        if (($stato === 'completato' || $stato === 'consegnato') && empty($data_fine)) {
                            $data_fine = date('Y-m-d');
                        }
                        
                        if ($action === 'add') {
                            // Inserimento nuovo intervento
                            $query = "INSERT INTO interventi (veicolo_id, descrizione, stato, data_inizio, data_fine, costo, note_tecniche, ricambi_utilizzati) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $params = [
                                $veicolo_id, 
                                $descrizione, 
                                $stato, 
                                $data_inizio, 
                                $data_fine ?: null, 
                                $costo, 
                                $note_tecniche, 
                                $ricambi_utilizzati
                            ];
                            $result = $db->execute($query, $params);
                            
                            if ($result) {
                                header('Location: interventi.php?success=add');
                                exit();
                            } else {
                                $error = 'Errore durante l\'inserimento dell\'intervento!';
                            }
                        } else {
                            // Aggiornamento intervento esistente
                            $query = "UPDATE interventi SET veicolo_id = ?, descrizione = ?, stato = ?, data_inizio = ?, data_fine = ?, costo = ?, note_tecniche = ?, ricambi_utilizzati = ? WHERE id = ?";
                            $params = [
                                $veicolo_id, 
                                $descrizione, 
                                $stato, 
                                $data_inizio, 
                                $data_fine ?: null, 
                                $costo, 
                                $note_tecniche, 
                                $ricambi_utilizzati, 
                                $interventoId
                            ];
                            $result = $db->execute($query, $params);
                            
                            if ($result) {
                                header('Location: interventi.php?success=update');
                                exit();
                            } else {
                                $error = 'Errore durante l\'aggiornamento dell\'intervento!';
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore database: ' . $e->getMessage();
                }
            }
        }
        
        // Carica dati intervento per modifica
        $intervento = null;
        if ($action === 'edit' && $interventoId) {
            $intervento = $db->select("SELECT * FROM interventi WHERE id = ?", [$interventoId]);
            $intervento = $intervento ? $intervento[0] : null;
            if (!$intervento) {
                header('Location: interventi.php?error=not_found');
                exit();
            }
        }
        break;
        
    case 'delete':
        if ($interventoId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: interventi.php?error=csrf');
                exit();
            }
            
            try {
                $result = $db->execute("DELETE FROM interventi WHERE id = ?", [$interventoId]);
                
                if ($result) {
                    header('Location: interventi.php?success=delete');
                    exit();
                } else {
                    header('Location: interventi.php?error=delete_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: interventi.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'view':
        // Visualizzazione dettagli intervento
        if ($interventoId) {
            $intervento = $db->select("
                SELECT i.*, v.marca, v.modello, v.targa, v.anno, v.cilindrata, v.colore,
                       c.nome, c.cognome, c.telefono, c.email
                FROM interventi i
                JOIN veicoli v ON i.veicolo_id = v.id
                JOIN clienti c ON v.cliente_id = c.id
                WHERE i.id = ?
            ", [$interventoId]);
            $intervento = $intervento ? $intervento[0] : null;
            
            if (!$intervento) {
                header('Location: interventi.php?error=not_found');
                exit();
            }
        }
        break;
        
    case 'update_status':
        // Aggiornamento rapido dello stato
        if ($interventoId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: interventi.php?error=csrf');
                exit();
            }
            
            $nuovo_stato = sanitize($_POST['stato'] ?? '');
            $stati_validi = ['in_attesa', 'lavorazione', 'completato', 'consegnato'];
            
            if (!in_array($nuovo_stato, $stati_validi)) {
                header('Location: interventi.php?error=invalid_status');
                exit();
            }
            
            try {
                // Se stato è completato o consegnato, imposta data fine
                $data_fine = null;
                if (($nuovo_stato === 'completato' || $nuovo_stato === 'consegnato')) {
                    $data_fine = date('Y-m-d');
                }
                
                $query = "UPDATE interventi SET stato = ?";
                $params = [$nuovo_stato];
                
                if ($data_fine) {
                    $query .= ", data_fine = ?";
                    $params[] = $data_fine;
                }
                
                $query .= " WHERE id = ?";
                $params[] = $interventoId;
                
                $result = $db->execute($query, $params);
                
                if ($result) {
                    header('Location: interventi.php?success=status_updated');
                    exit();
                } else {
                    header('Location: interventi.php?error=status_update_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: interventi.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'search_vehicles':
        // Ricerca AJAX veicoli
        if (isset($_GET['q'])) {
            $query = sanitize($_GET['q']);
            $searchTerm = "%$query%";
            
            $vehicles = $db->select("
                SELECT v.id, v.marca, v.modello, v.targa, c.nome, c.cognome
                FROM veicoli v
                JOIN clienti c ON v.cliente_id = c.id
                WHERE v.marca LIKE ? OR v.modello LIKE ? OR v.targa LIKE ? 
                   OR CONCAT(c.nome, ' ', c.cognome) LIKE ?
                   OR CONCAT(c.cognome, ' ', c.nome) LIKE ?
                ORDER BY c.cognome, c.nome, v.marca, v.modello
                LIMIT 10
            ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            
            header('Content-Type: application/json');
            echo json_encode($vehicles);
            exit();
        }
        break;
}

// Carica lista interventi per la vista principale
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $stato_filter = $_GET['stato_filter'] ?? '';
    $cliente_filter = $_GET['cliente_filter'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $whereClause = '';
    $params = [];
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(i.descrizione LIKE ? OR v.marca LIKE ? OR v.modello LIKE ? OR v.targa LIKE ? OR CONCAT(c.nome, ' ', c.cognome) LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($stato_filter)) {
        $conditions[] = "i.stato = ?";
        $params[] = $stato_filter;
    }
    
    if (!empty($cliente_filter)) {
        $conditions[] = "v.cliente_id = ?";
        $params[] = $cliente_filter;
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
    
    // Conta totale interventi
    $totalInterventi = $db->count("
        SELECT COUNT(*)
        FROM interventi i
        JOIN veicoli v ON i.veicolo_id = v.id
        JOIN clienti c ON v.cliente_id = c.id
        $whereClause
    ", $params);
    $totalPages = ceil($totalInterventi / $limit);
    
    // Carica interventi con paginazione
    $interventi = $db->select("
        SELECT i.*, 
               v.marca, v.modello, v.targa,
               c.nome, c.cognome
        FROM interventi i
        JOIN veicoli v ON i.veicolo_id = v.id
        JOIN clienti c ON v.cliente_id = c.id
        $whereClause
        ORDER BY 
            CASE i.stato 
                WHEN 'lavorazione' THEN 1
                WHEN 'in_attesa' THEN 2
                WHEN 'completato' THEN 3
                WHEN 'consegnato' THEN 4
                ELSE 5
            END,
            i.data_inizio DESC
        LIMIT $limit OFFSET $offset
    ", $params);
    
    // Carica clienti per filtro
    $clientiFilter = $db->select("SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome");
}
?>

<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <!-- Lista Interventi -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-primary mb-2">🔧 Gestione Interventi</h1>
                    <p class="text-secondary">Gestisci gli ordini di lavoro e le riparazioni</p>
                </div>
                <div>
                    <a href="interventi.php?action=add" class="btn btn-primary">
                        <span>➕</span> Nuovo Intervento
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiche rapide -->
    <div class="row mb-4">
        <?php
        $stats = [
            'in_attesa' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato = 'in_attesa'"),
            'lavorazione' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato = 'lavorazione'"),
            'completato' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato = 'completato'"),
            'consegnato' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato = 'consegnato'")
        ];
        ?>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['in_attesa']; ?></div>
                    <div class="stat-label">In Attesa</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-info">
                <div class="stat-icon">🔧</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['lavorazione']; ?></div>
                    <div class="stat-label">In Lavorazione</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['completato']; ?></div>
                    <div class="stat-label">Completati</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-secondary">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['consegnato']; ?></div>
                    <div class="stat-label">Consegnati</div>
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
                        <div class="col-md-3">
                            <label for="search" class="form-label">Ricerca</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Descrizione, veicolo, cliente..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="stato_filter" class="form-label">Stato</label>
                            <select id="stato_filter" name="stato_filter" class="form-control">
                                <option value="">Tutti gli stati</option>
                                <option value="in_attesa" <?php echo $stato_filter === 'in_attesa' ? 'selected' : ''; ?>>In Attesa</option>
                                <option value="lavorazione" <?php echo $stato_filter === 'lavorazione' ? 'selected' : ''; ?>>In Lavorazione</option>
                                <option value="completato" <?php echo $stato_filter === 'completato' ? 'selected' : ''; ?>>Completato</option>
                                <option value="consegnato" <?php echo $stato_filter === 'consegnato' ? 'selected' : ''; ?>>Consegnato</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="cliente_filter" class="form-label">Cliente</label>
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
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">🔍 Cerca</button>
                            <?php if (!empty($search) || !empty($stato_filter) || !empty($cliente_filter)): ?>
                            <a href="interventi.php" class="btn btn-secondary mt-1">✖️ Reset</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-right">
                            <small class="text-muted">
                                Totale: <?php echo $totalInterventi; ?> interventi
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabella interventi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($interventi)): ?>
                    <div class="table-container">
                        <table class="data-table" id="interventiTable">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Cliente</th>
                                    <th>Veicolo</th>
                                    <th>Descrizione</th>
                                    <th>Stato</th>
                                    <th>Costo</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($interventi as $intervento): ?>
                                <tr class="intervention-row" data-status="<?php echo $intervento['stato']; ?>">
                                    <td>
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($intervento['data_inizio'])); ?></strong>
                                        </div>
                                        <?php if ($intervento['data_fine']): ?>
                                        <small class="text-muted">
                                            Fine: <?php echo date('d/m/Y', strtotime($intervento['data_fine'])); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="clienti.php?action=view&id=<?php echo $intervento['cliente_id'] ?? ''; ?>" class="text-primary">
                                            <?php echo htmlspecialchars($intervento['nome'] . ' ' . $intervento['cognome']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($intervento['marca'] . ' ' . $intervento['modello']); ?></strong>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($intervento['targa']); ?></small>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($intervento['descrizione']); ?>">
                                            <?php echo htmlspecialchars($intervento['descrizione']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-container">
                                            <span class="badge <?php echo getStatoBadge($intervento['stato']); ?>">
                                                <?php echo getStatoText($intervento['stato']); ?>
                                            </span>
                                            <?php if (Auth::hasRole(['admin', 'meccanico'])): ?>
                                            <div class="status-actions">
                                                <button type="button" class="btn btn-sm btn-outline" 
                                                        onclick="showStatusModal(<?php echo $intervento['id']; ?>, '<?php echo $intervento['stato']; ?>')" 
                                                        title="Cambia stato">
                                                    🔄
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($intervento['costo'] > 0): ?>
                                        <strong>€ <?php echo number_format($intervento['costo'], 2, ',', '.'); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="interventi.php?action=view&id=<?php echo $intervento['id']; ?>" 
                                               class="btn btn-outline" title="Visualizza">
                                                👁️
                                            </a>
                                            <?php if (Auth::hasRole(['admin', 'meccanico'])): ?>
                                            <a href="interventi.php?action=edit&id=<?php echo $intervento['id']; ?>" 
                                               class="btn btn-outline" title="Modifica">
                                                ✏️
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="deleteIntervento(<?php echo $intervento['id']; ?>)" 
                                                    title="Elimina">
                                                🗑️
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($stato_filter) ? '&stato_filter='.urlencode($stato_filter) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
                                        ← Precedente
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($stato_filter) ? '&stato_filter='.urlencode($stato_filter) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($stato_filter) ? '&stato_filter='.urlencode($stato_filter) : ''; ?><?php echo !empty($cliente_filter) ? '&cliente_filter='.urlencode($cliente_filter) : ''; ?>">
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
                        <div class="mb-3" style="font-size: 4rem; opacity: 0.3;">🔧</div>
                        <h3 class="text-muted">Nessun intervento trovato</h3>
                        <p class="text-secondary">Non ci sono interventi che corrispondono ai criteri di ricerca.</p>
                        <a href="interventi.php?action=add" class="btn btn-primary">➕ Crea il primo intervento</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Aggiunta/Modifica Intervento -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        <?php echo $action === 'add' ? '➕ Nuovo Intervento' : '✏️ Modifica Intervento'; ?>
                    </h1>
                    <p class="text-secondary">
                        <?php echo $action === 'add' ? 'Crea un nuovo ordine di lavoro' : 'Modifica i dati dell\'intervento'; ?>
                    </p>
                </div>
                <div>
                    <a href="interventi.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dati Intervento</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-error mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate id="interventoForm">
                        <?php echo csrfField(); ?>
                        
                        <div class="form-group">
                            <label for="veicolo_search" class="form-label">Veicolo *</label>
                            <div class="vehicle-search-container">
                                <input type="text" id="veicolo_search" class="form-control" 
                                       placeholder="Cerca per modello, targa o nome proprietario..." 
                                       autocomplete="off">
                                <input type="hidden" id="veicolo_id" name="veicolo_id" value="<?php echo $intervento['veicolo_id'] ?? $veicoloId ?? ''; ?>" required>
                                <div id="vehicle_results" class="vehicle-dropdown" style="display: none;"></div>
                            </div>
                            <div id="selected_vehicle" class="selected-vehicle" style="<?php echo empty($intervento['veicolo_id']) && empty($veicoloId) ? 'display: none;' : ''; ?>">
                                <?php if (!empty($intervento['veicolo_id']) || !empty($veicoloId)): 
                                    $selectedVehicleId = $intervento['veicolo_id'] ?? $veicoloId;
                                    $selectedVehicle = array_filter($veicoli, function($v) use ($selectedVehicleId) { return $v['id'] == $selectedVehicleId; });
                                    $selectedVehicle = reset($selectedVehicle);
                                    if ($selectedVehicle): ?>
                                <div class="vehicle-card">
                                    <strong><?php echo htmlspecialchars($selectedVehicle['marca'] . ' ' . $selectedVehicle['modello']); ?></strong><br>
                                    <small>Targa: <?php echo htmlspecialchars($selectedVehicle['targa']); ?> | Proprietario: <?php echo htmlspecialchars($selectedVehicle['cognome'] . ' ' . $selectedVehicle['nome']); ?></small>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="clearVehicleSelection()">Cambia</button>
                                </div>
                                <?php endif; endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="descrizione" class="form-label">Descrizione Intervento *</label>
                            <textarea id="descrizione" name="descrizione" class="form-control" rows="3" 
                                      placeholder="Descrivi il lavoro da eseguire o il problema riscontrato..." 
                                      required><?php echo htmlspecialchars($intervento['descrizione'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stato" class="form-label">Stato</label>
                                    <select id="stato" name="stato" class="form-control">
                                        <option value="in_attesa" <?php echo ($intervento['stato'] ?? 'in_attesa') === 'in_attesa' ? 'selected' : ''; ?>>In Attesa</option>
                                        <option value="lavorazione" <?php echo ($intervento['stato'] ?? '') === 'lavorazione' ? 'selected' : ''; ?>>In Lavorazione</option>
                                        <option value="completato" <?php echo ($intervento['stato'] ?? '') === 'completato' ? 'selected' : ''; ?>>Completato</option>
                                        <option value="consegnato" <?php echo ($intervento['stato'] ?? '') === 'consegnato' ? 'selected' : ''; ?>>Consegnato</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="costo" class="form-label">Costo (€)</label>
                                    <input type="number" id="costo" name="costo" class="form-control" 
                                           value="<?php echo $intervento['costo'] ?? ''; ?>" 
                                           min="0" step="0.01" 
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="data_inizio" class="form-label">Data Inizio</label>
                                    <input type="date" id="data_inizio" name="data_inizio" class="form-control" 
                                           value="<?php echo $intervento['data_inizio'] ?? date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="data_fine" class="form-label">Data Fine</label>
                                    <input type="date" id="data_fine" name="data_fine" class="form-control" 
                                           value="<?php echo $intervento['data_fine'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="ricambi_utilizzati" class="form-label">Ricambi Utilizzati</label>
                            <textarea id="ricambi_utilizzati" name="ricambi_utilizzati" class="form-control" rows="3" 
                                      placeholder="Elenca i ricambi utilizzati per questo intervento..."><?php echo htmlspecialchars($intervento['ricambi_utilizzati'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="note_tecniche" class="form-label">Note Tecniche</label>
                            <textarea id="note_tecniche" name="note_tecniche" class="form-control" rows="4" 
                                      placeholder="Note tecniche, procedure seguite, osservazioni..."><?php echo htmlspecialchars($intervento['note_tecniche'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '💾 Crea Intervento' : '💾 Aggiorna Intervento'; ?>
                            </button>
                            <a href="interventi.php" class="btn btn-secondary">❌ Annulla</a>
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
                        <li class="mb-2">🔧 Descrivi chiaramente il lavoro da eseguire</li>
                        <li class="mb-2">📅 La data inizio viene impostata automaticamente se non specificata</li>
                        <li class="mb-2">✅ Quando imposti stato "Completato" o "Consegnato", la data fine viene impostata automaticamente</li>
                        <li class="mb-2">🔩 Elenca tutti i ricambi utilizzati per la fatturazione</li>
                        <li class="mb-2">📋 Usa le note tecniche per procedure specifiche</li>
                    </ul>
                </div>
            </div>
            
            <?php if ($action === 'add' && !empty($veicoloId)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">🏍️ Veicolo Selezionato</h3>
                </div>
                <div class="card-body">
                    <?php 
                    $veicoloSelezionato = null;
                    foreach ($veicoli as $v) {
                        if ($v['id'] == $veicoloId) {
                            $veicoloSelezionato = $v;
                            break;
                        }
                    }
                    if ($veicoloSelezionato): ?>
                    <p class="mb-1">
                        <strong><?php echo htmlspecialchars($veicoloSelezionato['marca'] . ' ' . $veicoloSelezionato['modello']); ?></strong>
                    </p>
                    <p class="mb-1">Targa: <?php echo htmlspecialchars($veicoloSelezionato['targa']); ?></p>
                    <small class="text-muted">
                        Proprietario: <?php echo htmlspecialchars($veicoloSelezionato['cognome'] . ' ' . $veicoloSelezionato['nome']); ?>
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $intervento): ?>
    <!-- Visualizzazione Dettagli Intervento -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        🔧 Intervento #<?php echo $intervento['id']; ?>
                    </h1>
                    <p class="text-secondary">
                        <?php echo htmlspecialchars($intervento['marca'] . ' ' . $intervento['modello'] . ' - ' . $intervento['targa']); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <?php if (Auth::hasRole(['admin', 'meccanico'])): ?>
                    <a href="interventi.php?action=edit&id=<?php echo $intervento['id']; ?>" class="btn btn-primary">✏️ Modifica</a>
                    <?php endif; ?>
                    <a href="interventi.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Dettagli Intervento -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">📋 Dettagli Intervento</h3>
                        <span class="badge <?php echo getStatoBadge($intervento['stato']); ?> badge-lg">
                            <?php echo getStatoText($intervento['stato']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="intervention-details">
                        <div class="detail-section mb-4">
                            <h5 class="section-title">🔧 Descrizione Lavoro</h5>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($intervento['descrizione'])); ?>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>📅 Data Inizio:</strong>
                                    <span><?php echo date('d/m/Y', strtotime($intervento['data_inizio'])); ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>📅 Data Fine:</strong>
                                    <span>
                                        <?php if ($intervento['data_fine']): ?>
                                        <?php echo date('d/m/Y', strtotime($intervento['data_fine'])); ?>
                                        <?php else: ?>
                                        <span class="text-muted">In corso</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-item mb-4">
                            <strong>💰 Costo:</strong>
                            <span>
                                <?php if ($intervento['costo'] > 0): ?>
                                <strong class="text-success">€ <?php echo number_format($intervento['costo'], 2, ',', '.'); ?></strong>
                                <?php else: ?>
                                <span class="text-muted">Non specificato</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <?php if ($intervento['ricambi_utilizzati']): ?>
                        <div class="detail-section mb-4">
                            <h5 class="section-title">🔩 Ricambi Utilizzati</h5>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($intervento['ricambi_utilizzati'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($intervento['note_tecniche']): ?>
                        <div class="detail-section">
                            <h5 class="section-title">📝 Note Tecniche</h5>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($intervento['note_tecniche'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informazioni Veicolo e Cliente -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏍️ Veicolo</h3>
                </div>
                <div class="card-body">
                    <div class="vehicle-info">
                        <div class="detail-item">
                            <strong>Marca/Modello:</strong>
                            <span><?php echo htmlspecialchars($intervento['marca'] . ' ' . $intervento['modello']); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Targa:</strong>
                            <span class="vehicle-plate-small"><?php echo htmlspecialchars($intervento['targa']); ?></span>
                        </div>
                        
                        <?php if ($intervento['anno']): ?>
                        <div class="detail-item">
                            <strong>Anno:</strong>
                            <span><?php echo $intervento['anno']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($intervento['cilindrata']): ?>
                        <div class="detail-item">
                            <strong>Cilindrata:</strong>
                            <span><?php echo $intervento['cilindrata']; ?>cc</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($intervento['colore']): ?>
                        <div class="detail-item">
                            <strong>Colore:</strong>
                            <span><?php echo htmlspecialchars($intervento['colore']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="veicoli.php?action=view&id=<?php echo $intervento['veicolo_id']; ?>" class="btn btn-outline btn-sm">
                            👁️ Vedi dettagli veicolo
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">👤 Cliente</h3>
                </div>
                <div class="card-body">
                    <div class="client-info">
                        <div class="detail-item">
                            <strong>Nome:</strong>
                            <span>
                                <a href="clienti.php?action=view&id=<?php echo $intervento['cliente_id'] ?? ''; ?>" class="text-primary">
                                    <?php echo htmlspecialchars($intervento['nome'] . ' ' . $intervento['cognome']); ?>
                                </a>
                            </span>
                        </div>
                        
                        <?php if ($intervento['telefono']): ?>
                        <div class="detail-item">
                            <strong>📞 Telefono:</strong>
                            <span>
                                <a href="tel:<?php echo htmlspecialchars($intervento['telefono']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($intervento['telefono']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($intervento['email']): ?>
                        <div class="detail-item">
                            <strong>📧 Email:</strong>
                            <span>
                                <a href="mailto:<?php echo htmlspecialchars($intervento['email']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($intervento['email']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="clienti.php?action=view&id=<?php echo $intervento['cliente_id'] ?? ''; ?>" class="btn btn-outline btn-sm">
                            👁️ Vedi dettagli cliente
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Modal per cambio stato -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔄 Cambia Stato Intervento</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="closeStatusModal()">✖️</button>
        </div>
        <div class="modal-body">
            <form id="statusForm" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="modal_stato" class="form-label">Nuovo Stato:</label>
                    <select id="modal_stato" name="stato" class="form-control" required>
                        <option value="in_attesa">⏳ In Attesa</option>
                        <option value="lavorazione">🔧 In Lavorazione</option>
                        <option value="completato">✅ Completato</option>
                        <option value="consegnato">📦 Consegnato</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Aggiorna Stato</button>
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">❌ Annulla</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form nascosto per eliminazione -->
<form id="deleteForm" method="POST" style="display: none;">
    <?php echo csrfField(); ?>
</form>

<style>
.stat-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--text-primary);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.stat-warning { border-left: 4px solid #f39c12; }
.stat-info { border-left: 4px solid #3498db; }
.stat-success { border-left: 4px solid #27ae60; }
.stat-secondary { border-left: 4px solid #95a5a6; }

.intervention-row[data-status="lavorazione"] {
    background-color: rgba(52, 152, 219, 0.1);
    border-left: 3px solid #3498db;
}

.intervention-row[data-status="in_attesa"] {
    background-color: rgba(243, 156, 18, 0.1);
    border-left: 3px solid #f39c12;
}

.status-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-actions {
    opacity: 0;
    transition: var(--transition);
}

.intervention-row:hover .status-actions {
    opacity: 1;
}

.detail-section {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 1rem;
    background: var(--bg-tertiary);
}

.section-title {
    color: var(--accent-primary);
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
}

.section-content {
    color: var(--text-primary);
    line-height: 1.6;
}

.vehicle-plate-small {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 0.8rem;
    letter-spacing: 1px;
    border: 1px solid #444;
    display: inline-block;
}

.badge-lg {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    max-width: 400px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
}

.modal-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    flex: 1;
}

.modal-body {
    padding: 1rem;
}

/* Stili per ricerca veicoli */
.vehicle-search-container {
    position: relative;
}

.vehicle-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-top: none;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.vehicle-item {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: var(--transition);
}

.vehicle-item:hover {
    background: var(--bg-secondary);
}

.vehicle-item:last-child {
    border-bottom: none;
}

.vehicle-item.no-results {
    text-align: center;
    color: var(--text-secondary);
    cursor: default;
}

.vehicle-item.no-results:hover {
    background: transparent;
}

.selected-vehicle {
    margin-top: 0.5rem;
}

.vehicle-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vehicle-card strong {
    color: var(--accent-primary);
}

.vehicle-card small {
    color: var(--text-secondary);
    display: block;
    margin-top: 0.25rem;
}

@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .stat-icon {
        font-size: 1.5rem;
    }
    
    .status-actions {
        opacity: 1;
    }
    
    .vehicle-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
let currentInterventoId = null;

// Funzione per eliminare intervento
function deleteIntervento(id) {
    if (confirm('Sei sicuro di voler eliminare questo intervento?\n\nATTENZIONE: Questa azione non può essere annullata!')) {
        const form = document.getElementById('deleteForm');
        form.action = `interventi.php?action=delete&id=${id}`;
        form.submit();
    }
}

// Funzione per mostrare modal cambio stato
function showStatusModal(interventoId, currentStatus) {
    currentInterventoId = interventoId;
    const modal = document.getElementById('statusModal');
    const statusSelect = document.getElementById('modal_stato');
    const form = document.getElementById('statusForm');
    
    // Imposta il valore corrente
    statusSelect.value = currentStatus;
    
    // Imposta l'action del form
    form.action = `interventi.php?action=update_status&id=${interventoId}`;
    
    // Mostra il modal
    modal.style.display = 'flex';
}

// Funzione per chiudere modal
function closeStatusModal() {
    const modal = document.getElementById('statusModal');
    modal.style.display = 'none';
    currentInterventoId = null;
}

// Chiudi modal cliccando fuori
document.addEventListener('click', function(e) {
    const modal = document.getElementById('statusModal');
    if (e.target === modal) {
        closeStatusModal();
    }
});

// Gestione tasti
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeStatusModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sul campo ricerca se presente
    const searchField = document.getElementById('search');
    if (searchField && !searchField.value) {
        searchField.focus();
    }
    
    // Gestione automatica data fine quando si cambia stato
    const statoSelect = document.getElementById('stato');
    const dataFineField = document.getElementById('data_fine');
    
    if (statoSelect && dataFineField) {
        statoSelect.addEventListener('change', function() {
            if ((this.value === 'completato' || this.value === 'consegnato') && !dataFineField.value) {
                dataFineField.value = new Date().toISOString().split('T')[0];
            }
        });
    }
    
    // Ricerca veicoli dinamica
    const vehicleSearch = document.getElementById('veicolo_search');
    const vehicleResults = document.getElementById('vehicle_results');
    const vehicleIdInput = document.getElementById('veicolo_id');
    const selectedVehicleDiv = document.getElementById('selected_vehicle');
    
    if (vehicleSearch) {
        let searchTimeout;
        
        vehicleSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                vehicleResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchVehicles(query);
            }, 300);
        });
        
        vehicleSearch.addEventListener('focus', function() {
            if (this.value.length >= 2) {
                vehicleResults.style.display = 'block';
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!vehicleSearch.contains(e.target) && !vehicleResults.contains(e.target)) {
                vehicleResults.style.display = 'none';
            }
        });
    }
});

function searchVehicles(query) {
    fetch('interventi.php?action=search_vehicles&q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            const vehicleResults = document.getElementById('vehicle_results');
            
            if (data.length === 0) {
                vehicleResults.innerHTML = '<div class="vehicle-item no-results">Nessun veicolo trovato</div>';
            } else {
                vehicleResults.innerHTML = data.map(vehicle => 
                    `<div class="vehicle-item" onclick="selectVehicle(${vehicle.id}, '${vehicle.marca}', '${vehicle.modello}', '${vehicle.targa}', '${vehicle.nome}', '${vehicle.cognome}')">
                        <strong>${vehicle.marca} ${vehicle.modello}</strong><br>
                        <small>Targa: ${vehicle.targa} | Proprietario: ${vehicle.cognome} ${vehicle.nome}</small>
                    </div>`
                ).join('');
            }
            
            vehicleResults.style.display = 'block';
        })
        .catch(error => {
            console.error('Errore ricerca veicoli:', error);
        });
}

function selectVehicle(id, marca, modello, targa, nome, cognome) {
    document.getElementById('veicolo_id').value = id;
    document.getElementById('veicolo_search').value = '';
    document.getElementById('vehicle_results').style.display = 'none';
    
    const selectedVehicleDiv = document.getElementById('selected_vehicle');
    selectedVehicleDiv.innerHTML = `
        <div class="vehicle-card">
            <strong>${marca} ${modello}</strong><br>
            <small>Targa: ${targa} | Proprietario: ${cognome} ${nome}</small>
            <button type="button" class="btn btn-sm btn-outline" onclick="clearVehicleSelection()">Cambia</button>
        </div>
    `;
    selectedVehicleDiv.style.display = 'block';
}

function clearVehicleSelection() {
    document.getElementById('veicolo_id').value = '';
    document.getElementById('veicolo_search').value = '';
    document.getElementById('selected_vehicle').style.display = 'none';
    document.getElementById('veicolo_search').focus();
}
</script>

<?php
require_once 'includes/footer.php';
?>