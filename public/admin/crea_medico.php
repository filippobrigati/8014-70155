<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Includo i file per il controllo dell'autenticazione e per le operazioni a database
// in modo da non dover riscrivere ogni volta la parte di connessione
require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
// Recupero l'istanza di mysql
$db = \config\Database::getInstance()->getMySQL();
// Recupero tramite il file di controllo autenticazione l-id dell'utente
$id_admin = $utenteLoggato['id'];

// Seleziono il centro medico dell'admin loggato tramite la tabella di collegamento
$stmtCentro = $db->prepare("
    SELECT c.partita_iva 
    FROM Centro_Medico c
    JOIN Admin_Centro ac ON c.partita_iva = ac.partita_iva_centro
    WHERE ac.id_admin = :id_admin
");
$stmtCentro->execute([':id_admin' => $id_admin]);
$centro = $stmtCentro->fetch(PDO::FETCH_ASSOC);
// Se l'utente non ha un centro medico associato allora esco
if (!$centro) {
    // Uso il comando die per interrompere l'esecuzione
    die("Non hai un centro medico associato. Non puoi creare medici.");
}
$partita_iva_centro = $centro['partita_iva'];

// Seleziono tutte le specializzazioni disponibili per il centro medico
$stmtSpecial = $db->prepare("
    SELECT id, nome 
    FROM Specializzazione 
    WHERE partita_iva_centro = :piva 
    ORDER BY nome ASC
");
$stmtSpecial->execute([':piva' => $partita_iva_centro]);
$specializzazioni = $stmtSpecial->fetchAll(PDO::FETCH_ASSOC); // in questo modo ritorno un array dove ogni riga ha come chiave
// le colonne della tabella

$messaggio = '';
$errore = '';

// Se la pagina viene caricato tramite POST allora significa che ho inviato il modulo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero le informazioni inserite nel form
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $cf = trim($_POST['codice_fiscale'] ?? '');
    $data_nascita = $_POST['data_nascita'] ?? '';
    $sesso = $_POST['sesso'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');

    // Recupero questi dati come se fossero array perche se ne possono aggiungere piu alla volta
    $id_specs = $_POST['id_specializzazione'] ?? [];
    $costi = $_POST['costo'] ?? [];
    $durate = $_POST['durata_minuti'] ?? [];

    if (!$nome || !$cognome || !$email || !$password || !$cf || !$data_nascita || !$sesso || empty($id_specs) || empty($costi) || empty($durate)) {
        $errore = "Tutti i campi (tranne il telefono) sono obbligatori e devi selezionare almeno una specializzazione.";
    } else {
        try {
            // Inizio una transazione, in questo modo se qualcosa faila posso annullare tutte le query
            // garantendo una consistenza delle operazioni
            $db->beginTransaction();
            // Creo l'hash della password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            // Inserisco la riga utente nella tabella Utenti con il ruolo 'medico'
            $stmtInserimentoUtente = $db->prepare("
                INSERT INTO Utente (email, password_hash, ruolo, codice_fiscale, nome, cognome, data_nascita, sesso, telefono)
                VALUES (:email, :password, 'medico', :cf, :nome, :cognome, :data_nascita, :sesso, :telefono)
            ");
            $stmtInserimentoUtente->execute([
                ':email' => $email,
                ':password' => $passwordHash,
                ':cf' => $cf,
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':data_nascita' => $data_nascita,
                ':sesso' => $sesso,
                ':telefono' => $telefono ? $telefono : null
            ]);
            $id_medico = $db->lastInsertId();
            // Connetto il medico con il centro corretto recuperato in precedenza
            $stmtLinkCentro = $db->prepare("
                INSERT INTO Medico_Centro (id_medico, partita_iva_centro)
                VALUES (:id_medico, :piva)
            ");
            $stmtLinkCentro->execute([
                ':id_medico' => $id_medico,
                ':piva' => $partita_iva_centro
            ]);
            // Abbino le specializzazioni selezionate al medico appena inserito, con i relativi costi e durata
            $abbinamentoSpecializzazioni = $db->prepare("
                INSERT INTO Medico_Specializzazione (id_medico, id_specializzazione, costo, durata_minuti)
                VALUES (:id_medico, :id_spec, :costo, :durata)
            ");

            for ($i = 0; $i < count($id_specs); $i++) {
                $abbinamentoSpecializzazioni->execute([
                    ':id_medico' => $id_medico,
                    ':id_spec' => $id_specs[$i],
                    ':costo' => $costi[$i],
                    ':durata' => $durate[$i]
                ]);
            }
            // Committo tutte le query, se si verifica un errore faccio il rollback
            $db->commit();
            // Importo il file che gestisce le operazioni di logging e aggiungo il log
            // nella collection (se tutto e' andato a buon fine)
            require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
            $logger = new \Utils\MongoLogger();
            $logger->logAzione(
                $utenteLoggato['id'],
                $utenteLoggato['ruolo'],
                'CREATE',
                'Utente_Medico',
                $id_medico,
                [
                    'email_assegnata' => $email,
                    'partita_iva_centro' => $partita_iva_centro
                ]
            );

            $messaggio = "Medico creato e associato al centro con successo!";
        } catch (\PDOException $e) {
            $db->rollBack();
            // Errore 23000 e 1062 rappresentano gli identificativi per una violazione delle chiavi
            // 1062 specifico per mysql sulla duplicate key
            if ($e->getCode() == 23000 || $e->getCode() == 1062) {
                $errore = "Esiste già un utente con questa email o codice fiscale.";
            } else {
                $errore = "Errore durante la creazione del medico: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Crea Nuovo Medico - Admin";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Nuovo Medico</h3>
                <p>Inserisci i dati del nuovo specialista</p>
            </div>
            <a href="/admin/gestione_medici.php" class="btn btn-secondary">
                Torna alla lista
            </a>
        </div>

        <?php if ($messaggio): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($messaggio); ?></div>
        <?php endif; ?>
        <?php if ($errore): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errore); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="/admin/crea_medico.php" method="POST">

                    <h5 class="mb-4">Dati Anagrafici e Accesso</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cognome" class="form-label">Cognome</label>
                            <input type="text" class="form-control" id="cognome" name="cognome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-4">
                            <label for="codice_fiscale" class="form-label">Codice Fiscale</label>
                            <input type="text" class="form-control" id="codice_fiscale" name="codice_fiscale"
                                maxlength="16" required>
                        </div>
                        <div class="col-md-3">
                            <label for="data_nascita" class="form-label">Data di Nascita</label>
                            <input type="date" class="form-control" id="data_nascita" name="data_nascita" required>
                        </div>
                        <div class="col-md-2">
                            <label for="sesso" class="form-label">Sesso</label>
                            <select class="form-select" id="sesso" name="sesso" required>
                                <option value="" selected disabled>...</option>
                                <option value="M">M</option>
                                <option value="F">F</option>
                                <option value="X">Altro</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="telefono" class="form-label">Telefono (Opzionale)</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                    </div>

                    <hr class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">Specializzazioni e Tariffe</h5>
                        <button type="button" class="btn btn-primary" onclick="aggiungiSpecializzazione()">
                            + Aggiungi
                        </button>
                    </div>

                    <div id="specializzazioni-container">
                        <div class="row g-3 mb-3 specializzazione-row">
                            <div class="col-md-5">
                                <label class="form-label">Specializzazione</label>
                                <select class="form-select" name="id_specializzazione[]" required>
                                    <option value="" selected disabled>Seleziona una specializzazione</option>
                                    <?php foreach ($specializzazioni as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec['id']); ?>">
                                            <?php echo htmlspecialchars($spec['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Costo (&euro;)</label>
                                <input type="number" step="0.01" class="form-control" name="costo[]"
                                    placeholder="es. 100.00" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Durata (min)</label>
                                <input type="number" class="form-control" name="durata_minuti[]" placeholder="es. 30"
                                    required>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-danger w-100"
                                    onclick="rimuoviSpecializzazione(this)" disabled title="Rimuovi">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-trash" viewBox="0 0 16 16">
                                        <path
                                            d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5Zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6Z" />
                                        <path fill-rule="evenodd"
                                            d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1ZM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118ZM2.5 3V2h11v1h-11Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Crea Medico</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Aggiungo un nuovo item  per la specializzaione
    function aggiungiSpecializzazione() {
        const container = document.getElementById('specializzazioni-container');
        const firstRow = container.querySelector('.specializzazione-row');
        const newRow = firstRow.cloneNode(true);

        newRow.querySelectorAll('input').forEach(input => input.value = '');
        newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

        const removeBtn = newRow.querySelector('button');
        removeBtn.disabled = false;

        const allRows = container.querySelectorAll('.specializzazione-row');
        if (allRows.length === 1) {
            firstRow.querySelector('button').disabled = false;
        }

        container.appendChild(newRow);
    }
    // Lo rimuovo
    function rimuoviSpecializzazione(btn) {
        const container = document.getElementById('specializzazioni-container');
        const allRows = container.querySelectorAll('.specializzazione-row');

        if (allRows.length > 1) {
            btn.closest('.specializzazione-row').remove();

            const remainingRows = container.querySelectorAll('.specializzazione-row');
            if (remainingRows.length === 1) {
                remainingRows[0].querySelector('button').disabled = true;
            }
        }
    }
</script>

<?php require __DIR__ . '/../_layout/footer.php'; ?>