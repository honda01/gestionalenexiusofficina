</main>
    
    <?php if (isLoggedIn()): ?>
    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Gestionale Officina Moto</h4>
                    <p>Sistema completo per la gestione dell'officina</p>
                </div>
                
                <div class="footer-section">
                    <h4>Funzionalità</h4>
                    <ul>
                        <li>Gestione Clienti</li>
                        <li>Gestione Veicoli</li>
                        <li>Interventi e Riparazioni</li>
                        <li>Magazzino Ricambi</li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Supporto</h4>
                    <ul>
                        <li>Documentazione</li>
                        <li>Assistenza Tecnica</li>
                        <li>Aggiornamenti</li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Statistiche Rapide</h4>
                    <div class="footer-stats">
                        <?php
                        try {
                            $db = getDB();
                            $clientiCount = $db->count("SELECT COUNT(*) FROM clienti");
                            $veicoliCount = $db->count("SELECT COUNT(*) FROM veicoli");
                            $interventiAperti = $db->count("SELECT COUNT(*) FROM interventi WHERE stato IN ('in_attesa', 'lavorazione')");
                        } catch (Exception $e) {
                            $clientiCount = $veicoliCount = $interventiAperti = 0;
                        }
                        ?>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $clientiCount; ?></span>
                            <span class="stat-label">Clienti</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $veicoliCount; ?></span>
                            <span class="stat-label">Veicoli</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $interventiAperti; ?></span>
                            <span class="stat-label">Interventi Aperti</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-info">
                    <p>&copy; <?php echo date('Y'); ?> Gestionale Officina Moto. Tutti i diritti riservati.</p>
                    <p>Versione 1.0 - Sviluppato con PHP, MySQL, HTML5 e CSS3</p>
                </div>
                
                <div class="footer-user">
                    <p>Connesso come: <strong><?php echo htmlspecialchars(getCurrentUser()['name']); ?></strong></p>
                    <p>Ruolo: <strong><?php echo Auth::getRoleName(getCurrentUser()['role']); ?></strong></p>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- Scripts comuni -->
    <script>
    // Funzioni JavaScript comuni
    
    // Conferma eliminazione
    function confirmDelete(message = 'Sei sicuro di voler eliminare questo elemento?') {
        return confirm(message);
    }
    
    // Mostra/nascondi loading
    function showLoading(element) {
        if (element) {
            element.disabled = true;
            element.innerHTML = '<span class="loading-spinner"></span> Caricamento...';
        }
    }
    
    function hideLoading(element, originalText) {
        if (element) {
            element.disabled = false;
            element.innerHTML = originalText;
        }
    }
    
    // Validazione form
    function validateForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        return isValid;
    }
    
    // Formattazione numeri
    function formatCurrency(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
    
    // Formattazione date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT');
    }
    
    // Ricerca in tabella
    function searchTable(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        
        if (!input || !table) return;
        
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(filter)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
    }
    
    // Notifiche toast
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Inizializzazione al caricamento della pagina
    document.addEventListener('DOMContentLoaded', function() {
        // Aggiungi listener per form con validazione
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this.id)) {
                    e.preventDefault();
                    showToast('Compila tutti i campi obbligatori', 'error');
                }
            });
        });
        
        // Aggiungi listener per conferma eliminazione
        const deleteButtons = document.querySelectorAll('[data-confirm-delete]');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm-delete') || 'Sei sicuro di voler eliminare questo elemento?';
                if (!confirmDelete(message)) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
    
</body>
</html>