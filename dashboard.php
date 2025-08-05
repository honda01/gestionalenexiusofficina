<?php
/**
 * Dashboard Principale - Gestionale Officina Moto
 */

require_once 'includes/header.php';
requireLogin();

$pageTitle = 'Dashboard';

// Recupera statistiche dal database
try {
    $db = getDB();
    
    // Statistiche generali
    $stats = [
        'clienti_totali' => $db->count("SELECT COUNT(*) FROM clienti"),
        'veicoli_totali' => $db->count("SELECT COUNT(*) FROM veicoli"),
        'interventi_aperti' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato IN ('in_attesa', 'lavorazione')"),
        'interventi_completati' => $db->count("SELECT COUNT(*) FROM interventi WHERE stato = 'completato'"),
        'ricambi_scorta_bassa' => $db->count("SELECT COUNT(*) FROM magazzino WHERE quantita <= soglia_minima")
    ];
    
    // Interventi recenti
    $interventiRecenti = $db->select("
        SELECT i.*, v.marca, v.modello, v.targa, c.nome, c.cognome
        FROM interventi i
        JOIN veicoli v ON i.veicolo_id = v.id
        JOIN clienti c ON v.cliente_id = c.id
        ORDER BY i.data_creazione DESC
        LIMIT 5
    ");
    
    // Appuntamenti oggi
    $appuntamentiOggi = $db->select("
        SELECT a.*, c.nome, c.cognome, v.marca, v.modello
        FROM appuntamenti a
        JOIN clienti c ON a.cliente_id = c.id
        LEFT JOIN veicoli v ON a.veicolo_id = v.id
        WHERE DATE(a.data_appuntamento) = CURDATE()
        ORDER BY a.ora_appuntamento
    ");
    
    // Ricambi in esaurimento
    $ricambiEsaurimento = $db->select("
        SELECT * FROM magazzino 
        WHERE quantita <= soglia_minima 
        ORDER BY quantita ASC 
        LIMIT 5
    ");
    
    // Dati per grafici (ultimi 6 mesi)
    $interventiMensili = $db->select("
        SELECT 
            DATE_FORMAT(data_inizio, '%Y-%m') as mese,
            COUNT(*) as totale,
            SUM(CASE WHEN stato = 'completato' THEN 1 ELSE 0 END) as completati
        FROM interventi 
        WHERE data_inizio >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(data_inizio, '%Y-%m')
        ORDER BY mese
    ");
    
} catch (Exception $e) {
    error_log("Errore dashboard: " . $e->getMessage());
    $stats = array_fill_keys(['clienti_totali', 'veicoli_totali', 'interventi_aperti', 'interventi_completati', 'ricambi_scorta_bassa'], 0);
    $interventiRecenti = [];
    $appuntamentiOggi = [];
    $ricambiEsaurimento = [];
    $interventiMensili = [];
}

// Funzione per ottenere il colore del badge stato
function getStatoBadge($stato) {
    switch ($stato) {
        case 'in_attesa': return 'badge-warning';
        case 'lavorazione': return 'badge-info';
        case 'completato': return 'badge-success';
        case 'consegnato': return 'badge-secondary';
        default: return 'badge-secondary';
    }
}

// Funzione per tradurre lo stato
function getStatoText($stato) {
    switch ($stato) {
        case 'in_attesa': return 'In Attesa';
        case 'lavorazione': return 'In Lavorazione';
        case 'completato': return 'Completato';
        case 'consegnato': return 'Consegnato';
        default: return ucfirst($stato);
    }
}
?>

<div class="main-content with-header">
    <!-- Hero Section BostMoto Style -->
    <div class="hero-section">
        <div class="hero-background"></div>
        <div class="hero-content">
            <div class="container">
                <div class="hero-text">
                    <h1 class="hero-title">Dashboard Gestionale</h1>
                    <p class="hero-subtitle">Centro di controllo per la tua officina Honda</p>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <span class="hero-stat-number"><?php echo $stats['interventi_aperti']; ?></span>
                            <span class="hero-stat-label">Interventi aperti</span>
                        </div>
                        <div class="hero-stat">
                            <span class="hero-stat-number"><?php echo count($appuntamentiOggi); ?></span>
                            <span class="hero-stat-label">Appuntamenti oggi</span>
                        </div>
                        <div class="hero-stat">
                            <span class="hero-stat-number"><?php echo $stats['clienti_totali']; ?></span>
                            <span class="hero-stat-label">Clienti totali</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
    <!-- Header Dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="text-primary mb-2">Dashboard</h1>
                    <p class="text-secondary">Panoramica generale dell'officina</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge badge-info">Ultimo aggiornamento: <?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiche principali -->
    <div class="row mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $stats['clienti_totali']; ?></div>
                    <div class="stat-label">Clienti Totali</div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-icon">🏍️</div>
                    <div class="stat-number"><?php echo $stats['veicoli_totali']; ?></div>
                    <div class="stat-label">Veicoli Registrati</div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-icon">🔧</div>
                    <div class="stat-number text-warning"><?php echo $stats['interventi_aperti']; ?></div>
                    <div class="stat-label">Interventi Aperti</div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number text-success"><?php echo $stats['interventi_completati']; ?></div>
                    <div class="stat-label">Interventi Completati</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert scorte basse -->
    <?php if ($stats['ricambi_scorta_bassa'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <strong>⚠️ Attenzione!</strong> 
                Ci sono <strong><?php echo $stats['ricambi_scorta_bassa']; ?></strong> ricambi con scorte basse.
                <a href="magazzino.php" class="btn btn-sm btn-warning ml-2">Visualizza Magazzino</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Grafico Interventi -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📊 Andamento Interventi (Ultimi 6 Mesi)</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <svg width="100%" height="300" viewBox="0 0 800 300" class="chart-svg">
                            <!-- Griglia -->
                            <defs>
                                <pattern id="grid" width="40" height="30" patternUnits="userSpaceOnUse">
                                    <path d="M 40 0 L 0 0 0 30" fill="none" stroke="var(--border-color)" stroke-width="1" opacity="0.3"/>
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#grid)" />
                            
                            <!-- Assi -->
                            <line x1="60" y1="250" x2="750" y2="250" stroke="var(--text-secondary)" stroke-width="2"/>
                            <line x1="60" y1="250" x2="60" y2="30" stroke="var(--text-secondary)" stroke-width="2"/>
                            
                            <!-- Dati grafico -->
                            <?php if (!empty($interventiMensili)): ?>
                                <?php 
                                $maxValue = max(array_column($interventiMensili, 'totale'));
                                $barWidth = 80;
                                $chartWidth = 690;
                                $chartHeight = 220;
                                $spacing = $chartWidth / count($interventiMensili);
                                ?>
                                
                                <?php foreach ($interventiMensili as $index => $data): ?>
                                    <?php 
                                    $x = 60 + ($index * $spacing) + ($spacing - $barWidth) / 2;
                                    $heightTotal = ($data['totale'] / max($maxValue, 1)) * $chartHeight;
                                    $heightCompleted = ($data['completati'] / max($maxValue, 1)) * $chartHeight;
                                    $yTotal = 250 - $heightTotal;
                                    $yCompleted = 250 - $heightCompleted;
                                    ?>
                                    
                                    <!-- Barra totale -->
                                    <rect x="<?php echo $x; ?>" y="<?php echo $yTotal; ?>" 
                                          width="<?php echo $barWidth; ?>" height="<?php echo $heightTotal; ?>" 
                                          fill="var(--accent-primary)" opacity="0.3" rx="4"/>
                                    
                                    <!-- Barra completati -->
                                    <rect x="<?php echo $x; ?>" y="<?php echo $yCompleted; ?>" 
                                          width="<?php echo $barWidth; ?>" height="<?php echo $heightCompleted; ?>" 
                                          fill="var(--success)" rx="4"/>
                                    
                                    <!-- Etichette -->
                                    <text x="<?php echo $x + $barWidth/2; ?>" y="270" 
                                          text-anchor="middle" fill="var(--text-secondary)" font-size="12">
                                        <?php echo date('M', strtotime($data['mese'] . '-01')); ?>
                                    </text>
                                    
                                    <!-- Valori -->
                                    <text x="<?php echo $x + $barWidth/2; ?>" y="<?php echo $yTotal - 5; ?>" 
                                          text-anchor="middle" fill="var(--text-primary)" font-size="12" font-weight="bold">
                                        <?php echo $data['totale']; ?>
                                    </text>
                                <?php endforeach; ?>
                                
                                <!-- Legenda -->
                                <rect x="600" y="40" width="15" height="15" fill="var(--accent-primary)" opacity="0.3" rx="2"/>
                                <text x="625" y="52" fill="var(--text-secondary)" font-size="12">Totali</text>
                                <rect x="600" y="65" width="15" height="15" fill="var(--success)" rx="2"/>
                                <text x="625" y="77" fill="var(--text-secondary)" font-size="12">Completati</text>
                            <?php else: ?>
                                <text x="400" y="150" text-anchor="middle" fill="var(--text-muted)" font-size="16">
                                    Nessun dato disponibile
                                </text>
                            <?php endif; ?>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Appuntamenti Oggi -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📅 Appuntamenti Oggi</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($appuntamentiOggi)): ?>
                        <div class="appointments-list">
                            <?php foreach ($appuntamentiOggi as $app): ?>
                            <div class="appointment-item mb-3 p-3 bg-secondary rounded">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['nome'] . ' ' . $app['cognome']); ?></strong>
                                        <?php if ($app['marca']): ?>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($app['marca'] . ' ' . $app['modello']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($app['descrizione']): ?>
                                        <div class="text-secondary small mt-1">
                                            <?php echo htmlspecialchars($app['descrizione']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-primary font-weight-bold">
                                            <?php echo date('H:i', strtotime($app['ora_appuntamento'])); ?>
                                        </div>
                                        <span class="badge <?php echo $app['stato'] === 'confermato' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($app['stato']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="appuntamenti.php" class="btn btn-outline btn-sm">Vedi tutti gli appuntamenti</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <div class="mb-2">📅</div>
                            <p>Nessun appuntamento per oggi</p>
                            <a href="appuntamenti.php" class="btn btn-primary btn-sm">Aggiungi Appuntamento</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Interventi Recenti -->
        <div class="col-12 col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔧 Interventi Recenti</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($interventiRecenti)): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Veicolo</th>
                                        <th>Descrizione</th>
                                        <th>Stato</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($interventiRecenti as $intervento): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($intervento['nome'] . ' ' . $intervento['cognome']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($intervento['marca'] . ' ' . $intervento['modello']); ?></div>
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
                                            <small><?php echo date('d/m/Y', strtotime($intervento['data_inizio'])); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="interventi.php" class="btn btn-outline btn-sm">Vedi tutti gli interventi</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <div class="mb-2">🔧</div>
                            <p>Nessun intervento recente</p>
                            <a href="interventi.php" class="btn btn-primary btn-sm">Crea Nuovo Intervento</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ricambi in Esaurimento -->
        <div class="col-12 col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📦 Scorte Basse</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($ricambiEsaurimento)): ?>
                        <div class="stock-alerts">
                            <?php foreach ($ricambiEsaurimento as $ricambio): ?>
                            <div class="stock-item mb-3 p-3 bg-secondary rounded <?php echo $ricambio['quantita'] == 0 ? 'border-danger' : 'border-warning'; ?>" style="border-left: 4px solid;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($ricambio['nome_ricambio']); ?></strong>
                                        <div class="text-muted small">
                                            Codice: <?php echo htmlspecialchars($ricambio['codice']); ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="<?php echo $ricambio['quantita'] == 0 ? 'text-danger' : 'text-warning'; ?> font-weight-bold">
                                            <?php echo $ricambio['quantita']; ?> pz
                                        </div>
                                        <small class="text-muted">
                                            Min: <?php echo $ricambio['soglia_minima']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="magazzino.php" class="btn btn-warning btn-sm">Gestisci Magazzino</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <div class="mb-2">✅</div>
                            <p>Tutte le scorte sono sufficienti</p>
                            <a href="magazzino.php" class="btn btn-outline btn-sm">Visualizza Magazzino</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚡ Azioni Rapide</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="clienti.php?action=add" class="quick-action-card">
                            <div class="quick-action-icon">👥</div>
                            <div class="quick-action-content">
                                <h3>Nuovo Cliente</h3>
                                <p>Aggiungi un nuovo cliente</p>
                            </div>
                        </a>
                        
                        <a href="veicoli.php?action=add" class="quick-action-card">
                            <div class="quick-action-icon">🏍️</div>
                            <div class="quick-action-content">
                                <h3>Nuovo Veicolo</h3>
                                <p>Registra un nuovo veicolo</p>
                            </div>
                        </a>
                        
                        <a href="interventi.php?action=add" class="quick-action-card">
                            <div class="quick-action-icon">🔧</div>
                            <div class="quick-action-content">
                                <h3>Nuovo Intervento</h3>
                                <p>Crea un nuovo intervento</p>
                            </div>
                        </a>
                        
                        <a href="appuntamenti.php" class="quick-action-card">
                            <div class="quick-action-icon">📅</div>
                            <div class="quick-action-content">
                                <h3>Nuovo Appuntamento</h3>
                                <p>Pianifica un appuntamento</p>
                            </div>
                        </a>
                        
                        <a href="magazzino.php?action=add" class="quick-action-card">
                            <div class="quick-action-icon">📦</div>
                            <div class="quick-action-content">
                                <h3>Nuovo Articolo</h3>
                                <p>Aggiungi articolo al magazzino</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    transition: var(--transition);
    border: 1px solid var(--border-color);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--accent-primary);
}

.stat-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--accent-primary);
    margin-bottom: 0.5rem;
    display: block;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 500;
}

.chart-svg {
    background: var(--bg-secondary);
    border-radius: var(--border-radius);
}

.appointment-item,
.stock-item {
    transition: var(--transition);
}

.appointment-item:hover,
.stock-item:hover {
    transform: translateX(5px);
    background-color: var(--bg-tertiary) !important;
}

.border-warning {
    border-left-color: var(--warning) !important;
}

.border-danger {
    border-left-color: var(--error) !important;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.quick-action-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s ease;
}

.quick-action-card:hover {
    background: var(--hover-bg);
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
}

.quick-action-icon {
    font-size: 2rem;
    margin-right: 1rem;
    opacity: 0.8;
}

.quick-action-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
    font-weight: 600;
}

.quick-action-content p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .stat-number {
        font-size: 2rem;
    }
    
    .stat-icon {
        font-size: 2.5rem;
    }
    
    .chart-svg {
        height: 250px;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
    
    .quick-action-card {
        padding: 0.75rem;
    }
    
    .quick-action-icon {
        font-size: 1.5rem;
        margin-right: 0.75rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>