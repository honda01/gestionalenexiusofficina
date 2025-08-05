<?php
/**
 * Gestione Magazzino - Gestionale Officina Moto
 */

require_once 'includes/header.php';
requireLogin();

$pageTitle = 'Gestione Magazzino';
$action = $_GET['action'] ?? 'list';
$ricambioId = $_GET['id'] ?? null;
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
            
            $nome_ricambio = sanitize($_POST['nome_ricambio'] ?? '');
            $codice = sanitize($_POST['codice'] ?? '');
            $descrizione = sanitize($_POST['descrizione'] ?? '');
            $categoria = sanitize($_POST['categoria'] ?? '');
            $fornitore = sanitize($_POST['fornitore'] ?? '');
            $quantita = intval($_POST['quantita'] ?? 0);
            $quantita_minima = intval($_POST['quantita_minima'] ?? 0);
            $prezzo_acquisto = floatval($_POST['prezzo_acquisto'] ?? 0);
            $prezzo_vendita = floatval($_POST['prezzo_vendita'] ?? 0);
            $posizione = sanitize($_POST['posizione'] ?? '');
            
            // Validazione
            if (empty($nome_ricambio) || empty($codice)) {
                $error = 'Nome ricambio e codice sono obbligatori!';
            } elseif ($quantita < 0) {
                $error = 'La quantità non può essere negativa!';
            } elseif ($quantita_minima < 0) {
                $error = 'La quantità minima non può essere negativa!';
            } elseif ($prezzo_acquisto < 0) {
                $error = 'Il prezzo di acquisto non può essere negativo!';
            } elseif ($prezzo_vendita < 0) {
                $error = 'Il prezzo di vendita non può essere negativo!';
            } else {
                try {
                    // Verifica che il codice non sia già utilizzato (per inserimento o per modifica di altro record)
                    $existingCode = $db->select("SELECT id FROM magazzino WHERE codice = ?" . ($action === 'edit' ? " AND id != ?" : ""), 
                                               $action === 'edit' ? [$codice, $ricambioId] : [$codice]);
                    
                    if ($existingCode) {
                        $error = 'Codice ricambio già esistente!';
                    } else {
                        if ($action === 'add') {
                            // Inserimento nuovo ricambio
                            $query = "INSERT INTO magazzino (nome_ricambio, codice, descrizione, categoria, fornitore, quantita, quantita_minima, prezzo_acquisto, prezzo_vendita, posizione) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $params = [$nome_ricambio, $codice, $descrizione, $categoria, $fornitore, $quantita, $quantita_minima, $prezzo_acquisto, $prezzo_vendita, $posizione];
                            $result = $db->execute($query, $params);
                            
                            if ($result) {
                                header('Location: magazzino.php?success=add');
                                exit();
                            } else {
                                $error = 'Errore durante l\'inserimento del ricambio!';
                            }
                        } else {
                            // Aggiornamento ricambio esistente
                            $query = "UPDATE magazzino SET nome_ricambio = ?, codice = ?, descrizione = ?, categoria = ?, fornitore = ?, quantita = ?, quantita_minima = ?, prezzo_acquisto = ?, prezzo_vendita = ?, posizione = ? WHERE id = ?";
                            $params = [$nome_ricambio, $codice, $descrizione, $categoria, $fornitore, $quantita, $quantita_minima, $prezzo_acquisto, $prezzo_vendita, $posizione, $ricambioId];
                            $result = $db->execute($query, $params);
                            
                            if ($result) {
                                header('Location: magazzino.php?success=update');
                                exit();
                            } else {
                                $error = 'Errore durante l\'aggiornamento del ricambio!';
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Errore database: ' . $e->getMessage();
                }
            }
        }
        
        // Carica dati ricambio per modifica
        $ricambio = null;
        if ($action === 'edit' && $ricambioId) {
            $ricambio = $db->select("SELECT * FROM magazzino WHERE id = ?", [$ricambioId]);
            $ricambio = $ricambio ? $ricambio[0] : null;
            if (!$ricambio) {
                header('Location: magazzino.php?error=not_found');
                exit();
            }
        }
        break;
        
    case 'delete':
        if ($ricambioId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: magazzino.php?error=csrf');
                exit();
            }
            
            try {
                $result = $db->execute("DELETE FROM magazzino WHERE id = ?", [$ricambioId]);
                
                if ($result) {
                    header('Location: magazzino.php?success=delete');
                    exit();
                } else {
                    header('Location: magazzino.php?error=delete_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: magazzino.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'update_quantity':
        // Aggiornamento rapido della quantità
        if ($ricambioId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validazione CSRF
            if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                header('Location: magazzino.php?error=csrf');
                exit();
            }
            
            $nuova_quantita = intval($_POST['quantita'] ?? 0);
            $operazione = sanitize($_POST['operazione'] ?? 'set'); // set, add, subtract
            
            if ($nuova_quantita < 0) {
                header('Location: magazzino.php?error=invalid_quantity');
                exit();
            }
            
            try {
                if ($operazione === 'set') {
                    $query = "UPDATE magazzino SET quantita = ? WHERE id = ?";
                    $params = [$nuova_quantita, $ricambioId];
                } elseif ($operazione === 'add') {
                    $query = "UPDATE magazzino SET quantita = quantita + ? WHERE id = ?";
                    $params = [$nuova_quantita, $ricambioId];
                } elseif ($operazione === 'subtract') {
                    $query = "UPDATE magazzino SET quantita = GREATEST(0, quantita - ?) WHERE id = ?";
                    $params = [$nuova_quantita, $ricambioId];
                } else {
                    header('Location: magazzino.php?error=invalid_operation');
                    exit();
                }
                
                $result = $db->execute($query, $params);
                
                if ($result) {
                    header('Location: magazzino.php?success=quantity_updated');
                    exit();
                } else {
                    header('Location: magazzino.php?error=quantity_update_failed');
                    exit();
                }
            } catch (Exception $e) {
                header('Location: magazzino.php?error=database_error');
                exit();
            }
        }
        break;
        
    case 'view':
        // Visualizzazione dettagli ricambio
        if ($ricambioId) {
            $ricambio = $db->select("SELECT * FROM magazzino WHERE id = ?", [$ricambioId]);
            $ricambio = $ricambio ? $ricambio[0] : null;
            
            if (!$ricambio) {
                header('Location: magazzino.php?error=not_found');
                exit();
            }
        }
        break;
}

// Carica lista ricambi per la vista principale
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $categoria_filter = $_GET['categoria_filter'] ?? '';
    $stock_filter = $_GET['stock_filter'] ?? ''; // all, low_stock, out_of_stock
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $whereClause = '';
    $params = [];
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(nome_ricambio LIKE ? OR codice LIKE ? OR descrizione LIKE ? OR fornitore LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if (!empty($categoria_filter)) {
        $conditions[] = "categoria = ?";
        $params[] = $categoria_filter;
    }
    
    if ($stock_filter === 'low_stock') {
        $conditions[] = "quantita <= quantita_minima AND quantita > 0";
    } elseif ($stock_filter === 'out_of_stock') {
        $conditions[] = "quantita = 0";
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(' AND ', $conditions);
    }
    
    // Conta totale ricambi
    $totalRicambi = $db->count("SELECT COUNT(*) FROM magazzino $whereClause", $params);
    $totalPages = ceil($totalRicambi / $limit);
    
    // Carica ricambi con paginazione
    $ricambi = $db->select("
        SELECT *
        FROM magazzino
        $whereClause
        ORDER BY 
            CASE 
                WHEN quantita = 0 THEN 1
                WHEN quantita <= quantita_minima THEN 2
                ELSE 3
            END,
            nome_ricambio ASC
        LIMIT $limit OFFSET $offset
    ", $params);
    
    // Carica categorie per filtro
    $categorie = $db->select("SELECT DISTINCT categoria FROM magazzino WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");
}
?>

<div class="container-fluid">
    <?php if ($action === 'list'): ?>
    <!-- Lista Magazzino -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-primary mb-2">📦 Gestione Magazzino</h1>
                    <p class="text-secondary">Gestisci l'inventario dei ricambi</p>
                </div>
                <div>
                    <a href="magazzino.php?action=add" class="btn btn-primary">
                        <span>➕</span> Nuovo Ricambio
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiche rapide -->
    <div class="row mb-4">
        <?php
        $stats = [
            'totale' => $db->count("SELECT COUNT(*) FROM magazzino"),
            'scorte_basse' => $db->count("SELECT COUNT(*) FROM magazzino WHERE quantita <= quantita_minima AND quantita > 0"),
            'esauriti' => $db->count("SELECT COUNT(*) FROM magazzino WHERE quantita = 0"),
            'valore_totale' => $db->select("SELECT SUM(quantita * prezzo_acquisto) as valore FROM magazzino")[0]['valore'] ?? 0
        ];
        ?>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-info">
                <div class="stat-icon">📦</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['totale']; ?></div>
                    <div class="stat-label">Totale Ricambi</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-warning <?php echo $stats['scorte_basse'] > 0 ? 'blinking' : ''; ?>">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['scorte_basse']; ?></div>
                    <div class="stat-label">Scorte Basse</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-danger <?php echo $stats['esauriti'] > 0 ? 'blinking' : ''; ?>">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['esauriti']; ?></div>
                    <div class="stat-label">Esauriti</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="stat-card stat-success">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <div class="stat-number">€ <?php echo number_format($stats['valore_totale'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Valore Magazzino</div>
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
                                   placeholder="Nome, codice, descrizione..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="categoria_filter" class="form-label">Categoria</label>
                            <select id="categoria_filter" name="categoria_filter" class="form-control">
                                <option value="">Tutte le categorie</option>
                                <?php foreach ($categorie as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['categoria']); ?>" 
                                        <?php echo $categoria_filter === $cat['categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['categoria']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="stock_filter" class="form-label">Stato Scorte</label>
                            <select id="stock_filter" name="stock_filter" class="form-control">
                                <option value="">Tutti</option>
                                <option value="low_stock" <?php echo $stock_filter === 'low_stock' ? 'selected' : ''; ?>>Scorte Basse</option>
                                <option value="out_of_stock" <?php echo $stock_filter === 'out_of_stock' ? 'selected' : ''; ?>>Esauriti</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">🔍 Cerca</button>
                            <?php if (!empty($search) || !empty($categoria_filter) || !empty($stock_filter)): ?>
                            <a href="magazzino.php" class="btn btn-secondary mt-1">✖️ Reset</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-right">
                            <small class="text-muted">
                                Totale: <?php echo $totalRicambi; ?> ricambi
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabella ricambi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($ricambi)): ?>
                    <div class="table-container">
                        <table class="data-table" id="ricambiTable">
                            <thead>
                                <tr>
                                    <th>Codice</th>
                                    <th>Nome Ricambio</th>
                                    <th>Categoria</th>
                                    <th>Quantità</th>
                                    <th>Prezzo</th>
                                    <th>Fornitore</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ricambi as $ricambio): ?>
                                <tr class="ricambio-row" data-stock-status="<?php echo getStockStatus($ricambio); ?>">
                                    <td>
                                        <div class="part-code">
                                            <?php echo htmlspecialchars($ricambio['codice']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($ricambio['nome_ricambio']); ?></strong>
                                        </div>
                                        <?php if ($ricambio['descrizione']): ?>
                                        <small class="text-muted text-truncate" style="max-width: 200px; display: block;" 
                                               title="<?php echo htmlspecialchars($ricambio['descrizione']); ?>">
                                            <?php echo htmlspecialchars($ricambio['descrizione']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ricambio['categoria']): ?>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($ricambio['categoria']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="quantity-container">
                                            <div class="quantity-display <?php echo getQuantityClass($ricambio); ?>">
                                                <strong><?php echo $ricambio['quantita']; ?></strong>
                                                <?php if ($ricambio['quantita_minima'] > 0): ?>
                                                <small class="text-muted">/ min: <?php echo $ricambio['quantita_minima']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (Auth::hasRole(['admin', 'reception'])): ?>
                                            <div class="quantity-actions">
                                                <button type="button" class="btn btn-sm btn-outline" 
                                                        onclick="showQuantityModal(<?php echo $ricambio['id']; ?>, <?php echo $ricambio['quantita']; ?>, '<?php echo htmlspecialchars($ricambio['nome_ricambio']); ?>')" 
                                                        title="Aggiorna quantità">
                                                    📝
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if ($ricambio['prezzo_vendita'] > 0): ?>
                                            <strong>€ <?php echo number_format($ricambio['prezzo_vendita'], 2, ',', '.'); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($ricambio['prezzo_acquisto'] > 0): ?>
                                        <small class="text-muted">
                                            Acq: € <?php echo number_format($ricambio['prezzo_acquisto'], 2, ',', '.'); ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ricambio['fornitore']): ?>
                                        <span><?php echo htmlspecialchars($ricambio['fornitore']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="magazzino.php?action=view&id=<?php echo $ricambio['id']; ?>" 
                                               class="btn btn-outline" title="Visualizza">
                                                👁️
                                            </a>
                                            <?php if (Auth::hasRole(['admin', 'reception'])): ?>
                                            <a href="magazzino.php?action=edit&id=<?php echo $ricambio['id']; ?>" 
                                               class="btn btn-outline" title="Modifica">
                                                ✏️
                                            </a>
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="deleteRicambio(<?php echo $ricambio['id']; ?>)" 
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($categoria_filter) ? '&categoria_filter='.urlencode($categoria_filter) : ''; ?><?php echo !empty($stock_filter) ? '&stock_filter='.urlencode($stock_filter) : ''; ?>">
                                        ← Precedente
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($categoria_filter) ? '&categoria_filter='.urlencode($categoria_filter) : ''; ?><?php echo !empty($stock_filter) ? '&stock_filter='.urlencode($stock_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($categoria_filter) ? '&categoria_filter='.urlencode($categoria_filter) : ''; ?><?php echo !empty($stock_filter) ? '&stock_filter='.urlencode($stock_filter) : ''; ?>">
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
                        <div class="mb-3" style="font-size: 4rem; opacity: 0.3;">📦</div>
                        <h3 class="text-muted">Nessun ricambio trovato</h3>
                        <p class="text-secondary">Non ci sono ricambi che corrispondono ai criteri di ricerca.</p>
                        <a href="magazzino.php?action=add" class="btn btn-primary">➕ Aggiungi il primo ricambio</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Form Aggiunta/Modifica Ricambio -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        <?php echo $action === 'add' ? '➕ Nuovo Ricambio' : '✏️ Modifica Ricambio'; ?>
                    </h1>
                    <p class="text-secondary">
                        <?php echo $action === 'add' ? 'Aggiungi un nuovo ricambio al magazzino' : 'Modifica i dati del ricambio'; ?>
                    </p>
                </div>
                <div>
                    <a href="magazzino.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dati Ricambio</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-error mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" data-validate id="ricambioForm">
                        <?php echo csrfField(); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nome_ricambio" class="form-label">Nome Ricambio *</label>
                                    <input type="text" id="nome_ricambio" name="nome_ricambio" class="form-control" 
                                           value="<?php echo htmlspecialchars($ricambio['nome_ricambio'] ?? ''); ?>" 
                                           placeholder="Es: Filtro olio, Candela, Pastiglie freno..." 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="codice" class="form-label">Codice Ricambio *</label>
                                    <input type="text" id="codice" name="codice" class="form-control" 
                                           value="<?php echo htmlspecialchars($ricambio['codice'] ?? ''); ?>" 
                                           placeholder="Es: FO001, CAN123, PF456..." 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="descrizione" class="form-label">Descrizione</label>
                            <textarea id="descrizione" name="descrizione" class="form-control" rows="2" 
                                      placeholder="Descrizione dettagliata del ricambio..."><?php echo htmlspecialchars($ricambio['descrizione'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="categoria" class="form-label">Categoria</label>
                                    <input type="text" id="categoria" name="categoria" class="form-control" 
                                           value="<?php echo htmlspecialchars($ricambio['categoria'] ?? ''); ?>" 
                                           placeholder="Es: Motore, Freni, Elettrica..." 
                                           list="categorieList">
                                    <datalist id="categorieList">
                                        <option value="Motore">
                                        <option value="Freni">
                                        <option value="Elettrica">
                                        <option value="Trasmissione">
                                        <option value="Sospensioni">
                                        <option value="Carrozzeria">
                                        <option value="Pneumatici">
                                        <option value="Accessori">
                                    </datalist>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="fornitore" class="form-label">Fornitore</label>
                                    <input type="text" id="fornitore" name="fornitore" class="form-control" 
                                           value="<?php echo htmlspecialchars($ricambio['fornitore'] ?? ''); ?>" 
                                           placeholder="Nome del fornitore...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="quantita" class="form-label">Quantità Disponibile</label>
                                    <input type="number" id="quantita" name="quantita" class="form-control" 
                                           value="<?php echo $ricambio['quantita'] ?? '0'; ?>" 
                                           min="0" 
                                           placeholder="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="quantita_minima" class="form-label">Quantità Minima</label>
                                    <input type="number" id="quantita_minima" name="quantita_minima" class="form-control" 
                                           value="<?php echo $ricambio['quantita_minima'] ?? '0'; ?>" 
                                           min="0" 
                                           placeholder="0">
                                    <small class="form-text text-muted">Soglia per avviso scorte basse</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prezzo_acquisto" class="form-label">Prezzo Acquisto (€)</label>
                                    <input type="number" id="prezzo_acquisto" name="prezzo_acquisto" class="form-control" 
                                           value="<?php echo $ricambio['prezzo_acquisto'] ?? ''; ?>" 
                                           min="0" step="0.01" 
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="prezzo_vendita" class="form-label">Prezzo Vendita (€)</label>
                                    <input type="number" id="prezzo_vendita" name="prezzo_vendita" class="form-control" 
                                           value="<?php echo $ricambio['prezzo_vendita'] ?? ''; ?>" 
                                           min="0" step="0.01" 
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="posizione" class="form-label">Posizione in Magazzino</label>
                            <input type="text" id="posizione" name="posizione" class="form-control" 
                                   value="<?php echo htmlspecialchars($ricambio['posizione'] ?? ''); ?>" 
                                   placeholder="Es: Scaffale A-3, Cassetto B-12...">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'add' ? '💾 Aggiungi Ricambio' : '💾 Aggiorna Ricambio'; ?>
                            </button>
                            <a href="magazzino.php" class="btn btn-secondary">❌ Annulla</a>
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
                        <li class="mb-2">🔢 Il codice ricambio deve essere univoco</li>
                        <li class="mb-2">📊 Imposta una quantità minima per ricevere avvisi</li>
                        <li class="mb-2">💰 I prezzi aiutano a calcolare il valore del magazzino</li>
                        <li class="mb-2">📍 La posizione facilita il ritrovamento</li>
                        <li class="mb-2">🏷️ Usa categorie coerenti per organizzare meglio</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'view' && $ricambio): ?>
    <!-- Visualizzazione Dettagli Ricambio -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary mb-2">
                        📦 <?php echo htmlspecialchars($ricambio['nome_ricambio']); ?>
                    </h1>
                    <p class="text-secondary">
                        Codice: <?php echo htmlspecialchars($ricambio['codice']); ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <?php if (Auth::hasRole(['admin', 'reception'])): ?>
                    <a href="magazzino.php?action=edit&id=<?php echo $ricambio['id']; ?>" class="btn btn-primary">✏️ Modifica</a>
                    <?php endif; ?>
                    <a href="magazzino.php" class="btn btn-secondary">← Torna alla lista</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Dettagli Ricambio -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">📋 Dettagli Ricambio</h3>
                        <div class="stock-status-badge">
                            <?php echo getStockStatusBadge($ricambio); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="part-details">
                        <?php if ($ricambio['descrizione']): ?>
                        <div class="detail-section mb-4">
                            <h5 class="section-title">📝 Descrizione</h5>
                            <div class="section-content">
                                <?php echo nl2br(htmlspecialchars($ricambio['descrizione'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>🏷️ Categoria:</strong>
                                    <span>
                                        <?php if ($ricambio['categoria']): ?>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($ricambio['categoria']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">Non specificata</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>🏢 Fornitore:</strong>
                                    <span>
                                        <?php if ($ricambio['fornitore']): ?>
                                        <?php echo htmlspecialchars($ricambio['fornitore']); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Non specificato</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>📦 Quantità Disponibile:</strong>
                                    <span class="<?php echo getQuantityClass($ricambio); ?>">
                                        <strong><?php echo $ricambio['quantita']; ?></strong>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>⚠️ Quantità Minima:</strong>
                                    <span><?php echo $ricambio['quantita_minima']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>💰 Prezzo Acquisto:</strong>
                                    <span>
                                        <?php if ($ricambio['prezzo_acquisto'] > 0): ?>
                                        € <?php echo number_format($ricambio['prezzo_acquisto'], 2, ',', '.'); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Non specificato</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-item">
                                    <strong>💵 Prezzo Vendita:</strong>
                                    <span>
                                        <?php if ($ricambio['prezzo_vendita'] > 0): ?>
                                        <strong class="text-success">€ <?php echo number_format($ricambio['prezzo_vendita'], 2, ',', '.'); ?></strong>
                                        <?php else: ?>
                                        <span class="text-muted">Non specificato</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($ricambio['posizione']): ?>
                        <div class="detail-item mb-4">
                            <strong>📍 Posizione:</strong>
                            <span class="position-badge"><?php echo htmlspecialchars($ricambio['posizione']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($ricambio['prezzo_acquisto'] > 0 && $ricambio['quantita'] > 0): ?>
                        <div class="detail-item">
                            <strong>💎 Valore Totale:</strong>
                            <span class="text-success">
                                <strong>€ <?php echo number_format($ricambio['prezzo_acquisto'] * $ricambio['quantita'], 2, ',', '.'); ?></strong>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Azioni Rapide -->
        <div class="col-12 col-lg-4 mb-4">
            <?php if (Auth::hasRole(['admin', 'reception'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚡ Azioni Rapide</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <button type="button" class="btn btn-primary btn-block mb-2" 
                                onclick="showQuantityModal(<?php echo $ricambio['id']; ?>, <?php echo $ricambio['quantita']; ?>, '<?php echo htmlspecialchars($ricambio['nome_ricambio']); ?>')">
                            📝 Aggiorna Quantità
                        </button>
                        
                        <a href="magazzino.php?action=edit&id=<?php echo $ricambio['id']; ?>" class="btn btn-outline btn-block mb-2">
                            ✏️ Modifica Dettagli
                        </a>
                        
                        <button type="button" class="btn btn-danger btn-block" 
                                onclick="deleteRicambio(<?php echo $ricambio['id']; ?>)">
                            🗑️ Elimina Ricambio
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">📊 Statistiche</h3>
                </div>
                <div class="card-body">
                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label">Stato Scorte:</span>
                            <span class="stat-value"><?php echo getStockStatusText($ricambio); ?></span>
                        </div>
                        
                        <?php if ($ricambio['prezzo_acquisto'] > 0 && $ricambio['prezzo_vendita'] > 0): ?>
                        <div class="stat-item">
                            <span class="stat-label">Margine:</span>
                            <span class="stat-value text-success">
                                <?php 
                                $margine = (($ricambio['prezzo_vendita'] - $ricambio['prezzo_acquisto']) / $ricambio['prezzo_acquisto']) * 100;
                                echo number_format($margine, 1) . '%';
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="stat-item">
                            <span class="stat-label">Giorni di scorta:</span>
                            <span class="stat-value">
                                <?php 
                                // Calcolo approssimativo basato su consumo medio (esempio)
                                if ($ricambio['quantita'] > 0) {
                                    echo $ricambio['quantita'] > 30 ? '30+' : $ricambio['quantita'];
                                } else {
                                    echo '0';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Modal per aggiornamento quantità -->
<div id="quantityModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📝 Aggiorna Quantità</h3>
            <button type="button" class="btn btn-sm btn-secondary" onclick="closeQuantityModal()">✖️</button>
        </div>
        <div class="modal-body">
            <div id="modalPartName" class="mb-3 text-center">
                <strong></strong>
            </div>
            <form id="quantityForm" method="POST">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label for="modal_quantita" class="form-label">Nuova Quantità:</label>
                    <input type="number" id="modal_quantita" name="quantita" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label for="modal_operazione" class="form-label">Operazione:</label>
                    <select id="modal_operazione" name="operazione" class="form-control" required>
                        <option value="set">🔄 Imposta quantità</option>
                        <option value="add">➕ Aggiungi alla quantità attuale</option>
                        <option value="subtract">➖ Sottrai dalla quantità attuale</option>
                    </select>
                </div>
                <div class="current-quantity mb-3">
                    <small class="text-muted">Quantità attuale: <span id="currentQuantity"></span></small>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Aggiorna</button>
                    <button type="button" class="btn btn-secondary" onclick="closeQuantityModal()">❌ Annulla</button>
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
.blinking {
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.5; }
}

.stat-danger { border-left: 4px solid #e74c3c; }

.ricambio-row[data-stock-status="out_of_stock"] {
    background-color: rgba(231, 76, 60, 0.1);
    border-left: 3px solid #e74c3c;
}

.ricambio-row[data-stock-status="low_stock"] {
    background-color: rgba(243, 156, 18, 0.1);
    border-left: 3px solid #f39c12;
}

.part-code {
    background: var(--bg-tertiary);
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    font-size: 0.9rem;
    border: 1px solid var(--border-color);
}

.quantity-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.quantity-display.text-danger {
    color: #e74c3c !important;
    font-weight: bold;
}

.quantity-display.text-warning {
    color: #f39c12 !important;
    font-weight: bold;
}

.quantity-display.text-success {
    color: #27ae60 !important;
}

.quantity-actions {
    opacity: 0;
    transition: var(--transition);
}

.ricambio-row:hover .quantity-actions {
    opacity: 1;
}

.position-badge {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: bold;
}

.stock-status-badge .badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
}

.quick-actions .btn {
    text-align: left;
}

.stats-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--border-color);
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stat-value {
    font-weight: bold;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .quantity-actions {
        opacity: 1;
    }
    
    .quick-actions .btn {
        text-align: center;
    }
}
</style>

<script>
let currentRicambioId = null;
let currentQuantity = 0;

// Funzione per eliminare ricambio
function deleteRicambio(id) {
    if (confirm('Sei sicuro di voler eliminare questo ricambio?\n\nATTENZIONE: Questa azione non può essere annullata!')) {
        const form = document.getElementById('deleteForm');
        form.action = `magazzino.php?action=delete&id=${id}`;
        form.submit();
    }
}

// Funzione per mostrare modal aggiornamento quantità
function showQuantityModal(ricambioId, quantita, nomeRicambio) {
    currentRicambioId = ricambioId;
    currentQuantity = quantita;
    
    const modal = document.getElementById('quantityModal');
    const partNameEl = document.getElementById('modalPartName').querySelector('strong');
    const quantityInput = document.getElementById('modal_quantita');
    const currentQuantityEl = document.getElementById('currentQuantity');
    const form = document.getElementById('quantityForm');
    
    // Imposta i valori
    partNameEl.textContent = nomeRicambio;
    quantityInput.value = '';
    currentQuantityEl.textContent = quantita;
    
    // Imposta l'action del form
    form.action = `magazzino.php?action=update_quantity&id=${ricambioId}`;
    
    // Mostra il modal
    modal.style.display = 'flex';
    quantityInput.focus();
}

// Funzione per chiudere modal
function closeQuantityModal() {
    const modal = document.getElementById('quantityModal');
    modal.style.display = 'none';
    currentRicambioId = null;
    currentQuantity = 0;
}

// Chiudi modal cliccando fuori
document.addEventListener('click', function(e) {
    const modal = document.getElementById('quantityModal');
    if (e.target === modal) {
        closeQuantityModal();
    }
});

// Gestione tasti
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQuantityModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus sul campo ricerca se presente
    const searchField = document.getElementById('search');
    if (searchField && !searchField.value) {
        searchField.focus();
    }
    
    // Gestione cambio operazione nel modal
    const operazioneSelect = document.getElementById('modal_operazione');
    const quantitaInput = document.getElementById('modal_quantita');
    
    if (operazioneSelect && quantitaInput) {
        operazioneSelect.addEventListener('change', function() {
            if (this.value === 'set') {
                quantitaInput.placeholder = 'Nuova quantità totale';
            } else if (this.value === 'add') {
                quantitaInput.placeholder = 'Quantità da aggiungere';
            } else if (this.value === 'subtract') {
                quantitaInput.placeholder = 'Quantità da sottrarre';
            }
        });
    }
});
</script>

<?php
// Funzioni helper per lo stato delle scorte
function getStockStatus($ricambio) {
    if ($ricambio['quantita'] == 0) {
        return 'out_of_stock';
    } elseif ($ricambio['quantita'] <= $ricambio['quantita_minima']) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
}

function getStockStatusText($ricambio) {
    switch (getStockStatus($ricambio)) {
        case 'out_of_stock':
            return 'Esaurito';
        case 'low_stock':
            return 'Scorte basse';
        case 'in_stock':
            return 'Disponibile';
        default:
            return 'Sconosciuto';
    }
}

function getStockStatusBadge($ricambio) {
    switch (getStockStatus($ricambio)) {
        case 'out_of_stock':
            return '<span class="badge badge-danger">❌ Esaurito</span>';
        case 'low_stock':
            return '<span class="badge badge-warning">⚠️ Scorte basse</span>';
        case 'in_stock':
            return '<span class="badge badge-success">✅ Disponibile</span>';
        default:
            return '<span class="badge badge-secondary">❓ Sconosciuto</span>';
    }
}

function getQuantityClass($ricambio) {
    switch (getStockStatus($ricambio)) {
        case 'out_of_stock':
            return 'text-danger';
        case 'low_stock':
            return 'text-warning';
        case 'in_stock':
            return 'text-success';
        default:
            return '';
    }
}

require_once 'includes/footer.php';
?>