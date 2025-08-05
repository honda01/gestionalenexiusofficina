<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token di sicurezza non valido.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $client_id = $_POST['client_id'] ?? null;
        $vehicle_id = $_POST['vehicle_id'] ?? null;
        $appointment_date = $_POST['appointment_date'] ?? '';
        $appointment_time = $_POST['appointment_time'] ?? '';
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'programmato';
        $notes = $_POST['notes'] ?? '';
            
            // Validation
            if (empty($client_id) || empty($appointment_date) || empty($appointment_time) || empty($description)) {
            $error = 'Tutti i campi obbligatori devono essere compilati.';
        } else {
            $datetime = $appointment_date . ' ' . $appointment_time;
                
                if ($action === 'add') {
                    $stmt = $conn->prepare("INSERT INTO appuntamenti (cliente_id, veicolo_id, data_appuntamento, descrizione, stato, note) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$client_id, $vehicle_id, $datetime, $description, $status, $notes]);
                    $success = 'Appuntamento aggiunto con successo.';
                } else {
                    $stmt = $conn->prepare("UPDATE appuntamenti SET cliente_id = ?, veicolo_id = ?, data_appuntamento = ?, descrizione = ?, stato = ?, note = ? WHERE id = ?");
                    $stmt->execute([$client_id, $vehicle_id, $datetime, $description, $status, $notes, $id]);
                    $success = 'Appuntamento aggiornato con successo.';
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                $stmt = $conn->prepare("DELETE FROM appuntamenti WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Appuntamento eliminato con successo.';
            }
        } elseif ($action === 'update_status') {
            $id = $_POST['id'] ?? null;
            $status = $_POST['status'] ?? '';
            if ($id && $status) {
                $stmt = $conn->prepare("UPDATE appuntamenti SET stato = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                $success = 'Stato aggiornato con successo.';
            }
        }
    }
}

// Get filter parameters
$view = $_GET['view'] ?? 'calendar';
$date = $_GET['date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$client_filter = $_GET['client'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query for appointments
$where_conditions = [];
$params = [];

if ($view === 'list') {
    if ($status_filter) {
        $where_conditions[] = "a.stato = ?";
        $params[] = $status_filter;
    }
    
    if ($client_filter) {
        $where_conditions[] = "a.cliente_id = ?";
        $params[] = $client_filter;
    }
    
    if ($search) {
        $where_conditions[] = "(c.nome LIKE ? OR c.cognome LIKE ? OR a.descrizione LIKE ? OR v.targa LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
} else {
    // Calendar view - show appointments for selected month
    $month_start = date('Y-m-01', strtotime($date));
    $month_end = date('Y-m-t', strtotime($date));
    $where_conditions[] = "DATE(a.data_appuntamento) BETWEEN ? AND ?";
    $params[] = $month_start;
    $params[] = $month_end;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get appointments
if ($view === 'list') {
    $count_query = "SELECT COUNT(*) FROM appuntamenti a 
                    LEFT JOIN clienti c ON a.cliente_id = c.id 
                    LEFT JOIN veicoli v ON a.veicolo_id = v.id 
                    $where_clause";
    $total_records = $conn->prepare($count_query);
    $total_records->execute($params);
    $total_records = $total_records->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    $query = "SELECT a.*, c.nome, c.cognome, c.telefono, v.marca, v.modello, v.targa
              FROM appuntamenti a 
              LEFT JOIN clienti c ON a.cliente_id = c.id 
              LEFT JOIN veicoli v ON a.veicolo_id = v.id 
              $where_clause 
              ORDER BY a.data_appuntamento ASC 
              LIMIT $limit OFFSET $offset";
} else {
    $query = "SELECT a.*, c.nome, c.cognome, c.telefono, v.marca, v.modello, v.targa
              FROM appuntamenti a 
              LEFT JOIN clienti c ON a.cliente_id = c.id 
              LEFT JOIN veicoli v ON a.veicolo_id = v.id 
              $where_clause 
              ORDER BY a.data_appuntamento ASC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get clients for dropdown
$clients_stmt = $conn->query("SELECT id, nome, cognome FROM clienti ORDER BY cognome, nome");
$clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vehicles for selected client (for AJAX)
if (isset($_GET['get_vehicles']) && isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
    $vehicles_stmt = $conn->prepare("SELECT id, marca, modello, targa FROM veicoli WHERE cliente_id = ? ORDER BY marca, modello");
    $vehicles_stmt->execute([$client_id]);
    $vehicles = $vehicles_stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($vehicles);
    exit;
}

// Get appointment for editing
$edit_appointment = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM appuntamenti WHERE id = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_appointment = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}

include 'includes/header.php';
?>

<style>
.calendar-container {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.calendar-nav button {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.calendar-nav button:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border-color);
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background: var(--secondary-bg);
    padding: 1rem;
    text-align: center;
    font-weight: 600;
    color: var(--text-secondary);
}

.calendar-day {
    background: var(--bg-color);
    min-height: 120px;
    padding: 0.5rem;
    position: relative;
    transition: background-color 0.3s ease;
}

.calendar-day:hover {
    background: var(--hover-bg);
}

.calendar-day.other-month {
    background: var(--secondary-bg);
    color: var(--text-muted);
}

.calendar-day.today {
    background: rgba(220, 38, 38, 0.1);
    border: 2px solid var(--primary-color);
}

.day-number {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.appointment-item {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.appointment-item:hover {
    background: var(--primary-hover);
    transform: scale(1.02);
}

.appointment-item.completato {
    background: #10b981;
}

.appointment-item.annullato {
    background: #6b7280;
}

.appointment-item.in_corso {
    background: #f59e0b;
}

.appointment-item.programmato {
    background: #3b82f6;
}

.appointment-item.confermato {
    background: #059669;
}

.view-toggle {
    display: flex;
    gap: 0.5rem;
}

.view-toggle button {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    color: var(--text-color);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-toggle button.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.appointment-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.appointment-modal.show {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    color: var(--text-color);
    font-size: 1.25rem;
    font-weight: 600;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.btn-close:hover {
    background: var(--hover-bg);
    color: var(--text-color);
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-color);
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
    color: var(--text-color);
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--secondary-bg);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--hover-bg);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-programmato {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.status-confermato {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-in_corso {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-completato {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.status-annullato {
    background: rgba(107, 114, 128, 0.2);
    color: #6b7280;
}

.quick-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.quick-actions button {
    padding: 0.25rem 0.5rem;
    border: none;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-confirm {
    background: #10b981;
    color: white;
}

.btn-progress {
    background: #f59e0b;
    color: white;
}

.btn-complete {
    background: #22c55e;
    color: white;
}

.btn-cancel {
    background: #6b7280;
    color: white;
}

/* Stili per vista lista */
.filters-section {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.filters-form {
    display: flex;
    gap: 1rem;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
    color: var(--text-color);
    font-size: 0.875rem;
}

.table-container {
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.data-table th {
    background: var(--secondary-bg);
    color: var(--text-color);
    font-weight: 600;
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}

.data-table tbody tr:hover {
    background: var(--hover-bg);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-info {
    background: #3b82f6;
    color: white;
}

.btn-info:hover {
    background: #2563eb;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .calendar-grid {
        font-size: 0.875rem;
    }
    
    .calendar-day {
        min-height: 80px;
        padding: 0.25rem;
    }
    
    .appointment-item {
        font-size: 0.625rem;
        padding: 0.125rem 0.25rem;
    }
    
    .calendar-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .view-toggle {
        justify-content: center;
    }
    
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: auto;
    }
    
    .data-table {
        font-size: 0.75rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="page-header">
    <div class="header-content">
        <div class="header-left">
            <h1><i class="icon-calendar"></i> Gestione Appuntamenti</h1>
            <p>Pianifica e gestisci gli appuntamenti dell'officina</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="openAppointmentModal()">
                <i class="icon-plus"></i> Nuovo Appuntamento
            </button>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="calendar-container">
    <div class="calendar-header">
        <div class="view-toggle">
            <button type="button" class="<?= $view === 'calendar' ? 'active' : '' ?>" onclick="switchView('calendar')">
                <i class="icon-calendar"></i> Calendario
            </button>
            <button type="button" class="<?= $view === 'list' ? 'active' : '' ?>" onclick="switchView('list')">
                <i class="icon-list"></i> Lista
            </button>
        </div>
        
        <?php if ($view === 'calendar'): ?>
            <div class="calendar-nav">
                <button type="button" onclick="changeMonth(-1)">
                    <i class="icon-chevron-left"></i>
                </button>
                <h3 id="current-month"><?= date('F Y', strtotime($date)) ?></h3>
                <button type="button" onclick="changeMonth(1)">
                    <i class="icon-chevron-right"></i>
                </button>
            </div>
            <button type="button" class="btn btn-secondary" onclick="goToToday()">
                Oggi
            </button>
        <?php endif; ?>
    </div>
    
    <?php if ($view === 'calendar'): ?>
        <!-- Calendar View -->
        <div class="calendar-grid">
            <div class="calendar-day-header">Dom</div>
            <div class="calendar-day-header">Lun</div>
            <div class="calendar-day-header">Mar</div>
            <div class="calendar-day-header">Mer</div>
            <div class="calendar-day-header">Gio</div>
            <div class="calendar-day-header">Ven</div>
            <div class="calendar-day-header">Sab</div>
            
            <?php
            $current_date = new DateTime($date);
            $current_date->modify('first day of this month');
            $start_date = clone $current_date;
            $start_date->modify('last sunday');
            
            $end_date = new DateTime($date);
            $end_date->modify('last day of this month');
            $end_date->modify('next saturday');
            
            $appointments_by_date = [];
            foreach ($appointments as $appointment) {
                $app_date = date('Y-m-d', strtotime($appointment['data_appuntamento']));
                if (!isset($appointments_by_date[$app_date])) {
                    $appointments_by_date[$app_date] = [];
                }
                $appointments_by_date[$app_date][] = $appointment;
            }
            
            $current = clone $start_date;
            while ($current <= $end_date) {
                $day_str = $current->format('Y-m-d');
                $is_current_month = $current->format('m') === $current_date->format('m');
                $is_today = $day_str === date('Y-m-d');
                
                $classes = ['calendar-day'];
                if (!$is_current_month) $classes[] = 'other-month';
                if ($is_today) $classes[] = 'today';
                
                echo '<div class="' . implode(' ', $classes) . '" onclick="selectDate(\'' . $day_str . '\')">';
                echo '<div class="day-number">' . $current->format('j') . '</div>';
                
                if (isset($appointments_by_date[$day_str])) {
                    foreach ($appointments_by_date[$day_str] as $appointment) {
                        $time = date('H:i', strtotime($appointment['data_appuntamento']));
                        echo '<div class="appointment-item ' . $appointment['stato'] . '" onclick="event.stopPropagation(); viewAppointment(' . $appointment['id'] . ')" title="' . htmlspecialchars($appointment['descrizione']) . '">';
                        echo $time . ' - ' . htmlspecialchars(substr($appointment['descrizione'], 0, 20));
                        if (strlen($appointment['descrizione']) > 20) echo '...';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                $current->modify('+1 day');
            }
            ?>
        </div>
    <?php else: ?>
        <!-- List View -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="hidden" name="view" value="list">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Cerca appuntamenti..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-group">
                    <select name="status">
                        <option value="">Tutti gli stati</option>
                        <option value="programmato" <?= $status_filter === 'programmato' ? 'selected' : '' ?>>Programmato</option>
                        <option value="confermato" <?= $status_filter === 'confermato' ? 'selected' : '' ?>>Confermato</option>
                        <option value="in_corso" <?= $status_filter === 'in_corso' ? 'selected' : '' ?>>In corso</option>
                        <option value="completato" <?= $status_filter === 'completato' ? 'selected' : '' ?>>Completato</option>
                        <option value="annullato" <?= $status_filter === 'annullato' ? 'selected' : '' ?>>Annullato</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select name="client">
                        <option value="">Tutti i clienti</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['cognome'] . ' ' . $client['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtra</button>
                <a href="?view=list" class="btn btn-secondary">Reset</a>
            </form>
        </div>
        
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Cliente</th>
                        <th>Veicolo</th>
                        <th>Descrizione</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Nessun appuntamento trovato</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($appointment['data_appuntamento'])) ?></strong><br>
                                    <small><?= date('H:i', strtotime($appointment['data_appuntamento'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($appointment['cognome'] . ' ' . $appointment['nome']) ?></strong><br>
                                    <small><?= htmlspecialchars($appointment['telefono']) ?></small>
                                </td>
                                <td>
                                    <?php if ($appointment['marca']): ?>
                                        <strong><?= htmlspecialchars($appointment['marca'] . ' ' . $appointment['modello']) ?></strong><br>
                                        <small><?= htmlspecialchars($appointment['targa']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Nessun veicolo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($appointment['descrizione']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $appointment['stato'] ?>">
                                        <?= ucfirst(str_replace('-', ' ', $appointment['stato'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editAppointment(<?= $appointment['id'] ?>)" title="Modifica">
                                            <i class="icon-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" onclick="viewAppointment(<?= $appointment['id'] ?>)" title="Visualizza">
                                            <i class="icon-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteAppointment(<?= $appointment['id'] ?>)" title="Elimina">
                                            <i class="icon-trash"></i>
                                        </button>
                                    </div>
                                    <div class="quick-actions">
                                        <?php if ($appointment['stato'] === 'programmato'): ?>
                                            <button type="button" class="btn-confirm" onclick="updateStatus(<?= $appointment['id'] ?>, 'confermato')" title="Conferma">
                                                <i class="icon-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (in_array($appointment['stato'], ['programmato', 'confermato'])): ?>
                                            <button type="button" class="btn-progress" onclick="updateStatus(<?= $appointment['id'] ?>, 'in_corso')" title="In corso">
                                                <i class="icon-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($appointment['stato'] === 'in_corso'): ?>
                                            <button type="button" class="btn-complete" onclick="updateStatus(<?= $appointment['id'] ?>, 'completato')" title="Completa">
                                                <i class="icon-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!in_array($appointment['stato'], ['completato', 'annullato'])): ?>
                                            <button type="button" class="btn-cancel" onclick="updateStatus(<?= $appointment['id'] ?>, 'annullato')" title="Annulla">
                                                <i class="icon-x"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($view === 'list' && $total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?view=list&page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&client=<?= urlencode($client_filter) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Appointment Modal -->
<div id="appointmentModal" class="appointment-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Nuovo Appuntamento</h3>
            <button type="button" class="btn-close" onclick="closeAppointmentModal()">&times;</button>
        </div>
        <form id="appointmentForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCSRFToken() ?>">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="appointmentId">
            
            <div class="form-group">
                <label for="client_id">Cliente *</label>
                <select name="client_id" id="client_id" class="form-control" required onchange="loadVehicles(this.value)">
                    <option value="">Seleziona cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= htmlspecialchars($client['id']) ?>" <?= $edit_appointment && $edit_appointment['cliente_id'] == $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['cognome'] . ' ' . $client['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="vehicle_id">Veicolo</label>
                <select name="vehicle_id" id="vehicle_id" class="form-control">
                    <option value="">Seleziona veicolo</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_date">Data *</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="form-control"
                           value="<?= $edit_appointment ? date('Y-m-d', strtotime($edit_appointment['data_appuntamento'])) : '' ?>" required>
                </div>
                <div class="form-group">
                    <label for="appointment_time">Ora *</label>
                    <input type="time" name="appointment_time" id="appointment_time" class="form-control"
                           value="<?= $edit_appointment ? date('H:i', strtotime($edit_appointment['data_appuntamento'])) : '' ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Descrizione *</label>
                <textarea name="description" id="description" class="form-control" rows="3" required placeholder="Descrivi il tipo di intervento o servizio richiesto"><?= $edit_appointment ? htmlspecialchars($edit_appointment['descrizione']) : '' ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="status">Stato</label>
                <select name="status" id="status" class="form-control">
                    <option value="programmato" <?= $edit_appointment && $edit_appointment['stato'] == 'programmato' ? 'selected' : '' ?>>Programmato</option>
                    <option value="confermato" <?= $edit_appointment && $edit_appointment['stato'] == 'confermato' ? 'selected' : '' ?>>Confermato</option>
                    <option value="in_corso" <?= $edit_appointment && $edit_appointment['stato'] == 'in_corso' ? 'selected' : '' ?>>In corso</option>
                    <option value="completato" <?= $edit_appointment && $edit_appointment['stato'] == 'completato' ? 'selected' : '' ?>>Completato</option>
                    <option value="annullato" <?= $edit_appointment && $edit_appointment['stato'] == 'annullato' ? 'selected' : '' ?>>Annullato</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="notes">Note</label>
                <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Note aggiuntive (opzionale)"><?= $edit_appointment ? htmlspecialchars($edit_appointment['note']) : '' ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salva Appuntamento</button>
                <button type="button" class="btn btn-secondary" onclick="closeAppointmentModal()">Annulla</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentDate = new Date('<?= $date ?>');
let currentView = '<?= $view ?>';

// Define functions globally so they're available to onclick handlers
function switchView(view) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('view', view);
    if (view === 'calendar') {
        urlParams.set('date', currentDate.toISOString().split('T')[0]);
    }
    window.location.href = '?' + urlParams.toString();
}

function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    window.location.href = `?view=calendar&date=${currentDate.toISOString().split('T')[0]}`;
}

function goToToday() {
    window.location.href = `?view=calendar&date=${new Date().toISOString().split('T')[0]}`;
}

function selectDate(date) {
    document.getElementById('appointment_date').value = date;
    openAppointmentModal();
}

function openAppointmentModal() {
    document.getElementById('appointmentModal').classList.add('show');
    document.getElementById('modalTitle').textContent = 'Nuovo Appuntamento';
    document.getElementById('formAction').value = 'add';
    document.getElementById('appointmentId').value = '';
    document.getElementById('appointmentForm').reset();
    
    // Set default date to today if not set
    if (!document.getElementById('appointment_date').value) {
        document.getElementById('appointment_date').value = new Date().toISOString().split('T')[0];
    }
}

function closeAppointmentModal() {
    document.getElementById('appointmentModal').classList.remove('show');
}

function editAppointment(id) {
    // Load appointment data via AJAX
    fetch(`?edit=${id}`)
        .then(response => response.text())
        .then(data => {
            // Parse the appointment data from the response
            // This is a simplified version - in a real app you'd return JSON
            openAppointmentModal();
            document.getElementById('modalTitle').textContent = 'Modifica Appuntamento';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('appointmentId').value = id;
            
            // You would populate the form fields here with the appointment data
            // For now, redirect to edit page
            window.location.href = `?edit=${id}`;
        });
}

function viewAppointment(id) {
    // Show appointment details in a modal or redirect to detail page
    alert('Visualizza appuntamento ID: ' + id);
}

function deleteAppointment(id) {
    if (confirm('Sei sicuro di voler eliminare questo appuntamento?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateStatus(id, status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= Auth::generateCSRFToken() ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="${id}">
        <input type="hidden" name="status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function loadVehicles(clientId) {
    const vehicleSelect = document.getElementById('vehicle_id');
    vehicleSelect.innerHTML = '<option value="">Caricamento...</option>';
    
    if (!clientId) {
        vehicleSelect.innerHTML = '<option value="">Seleziona veicolo</option>';
        return;
    }
    
    fetch(`?get_vehicles=1&client_id=${clientId}`)
        .then(response => response.json())
        .then(vehicles => {
            vehicleSelect.innerHTML = '<option value="">Seleziona veicolo</option>';
            vehicles.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = `${vehicle.marca} ${vehicle.modello} (${vehicle.targa})`;
                vehicleSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading vehicles:', error);
            vehicleSelect.innerHTML = '<option value="">Errore nel caricamento</option>';
        });
}

// Wait for DOM to be ready for event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Close modal when clicking outside
    document.getElementById('appointmentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAppointmentModal();
        }
    });

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>