<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Controllo che solo gli admin possano accedere
if ($utenteLoggato['ruolo'] !== 'admin') {
    die("Accesso negato. Solo gli amministratori possono visualizzare questa pagina.");
}

$pageTitle = "Orari Medici - Admin";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_admin = $utenteLoggato['id'];

// Seleziono la lista di medici che appartengono a quel centro
// Per farlo ho bisogno di collegare la tabella Utenti (con i dati dei medici)
// con la tabella Medico_Centro per controllare che appartengano a quel centro
// che deve essere confrontato con la partita iva presente nella tabella Admin
// Centro in base all'id dell'admin
$stmtMedici = $db->prepare("
    SELECT u.id, u.nome, u.cognome, u.codice_fiscale
    FROM Utente u
    JOIN Medico_Centro mc ON u.id = mc.id_medico
    JOIN Admin_Centro ac ON mc.partita_iva_centro = ac.partita_iva_centro
    WHERE ac.id_admin = :id_admin 
      AND u.ruolo = 'medico'
    ORDER BY u.cognome ASC, u.nome ASC
");
$stmtMedici->execute([':id_admin' => $id_admin]);
$medici = $stmtMedici->fetchAll(PDO::FETCH_ASSOC);

// Se un medico e' selezionato tramite query parametro allora converto l'id passato
$id_medico_selezionato = isset($_GET['id_medico']) ? (int) $_GET['id_medico'] : 0;
$medico_selezionato = null;
$orari = [];

if ($id_medico_selezionato > 0) {
    foreach ($medici as $m) {
        if ($m['id'] === $id_medico_selezionato) {
            $medico_selezionato = $m;
            break;
        }
    }
    // Dopo aver entratto il medico vado ad ottenere tutte le informazioni sulle sue presenze
    if ($medico_selezionato) {
        $stmtOrari = $db->prepare("
            SELECT giorno_settimana, ora_inizio, ora_fine 
            FROM Agenda_Presenze 
            WHERE id_medico = :id_medico 
            ORDER BY giorno_settimana ASC
        ");
        $stmtOrari->execute([':id_medico' => $id_medico_selezionato]);
        // Organizzo gli orari in base al giorno (un intero) con le chiavi inizio e fine
        while ($row = $stmtOrari->fetch(PDO::FETCH_ASSOC)) {
            $orari[$row['giorno_settimana']] = [
                'inizio' => substr($row['ora_inizio'], 0, 5),
                'fine' => substr($row['ora_fine'], 0, 5)
            ];
        }
    }
}

$giorni_settimana = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì'
];
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>Orari Medici</h3>
            <p>Seleziona un medico per visualizzare i suoi turni di lavoro nel tuo centro.</p>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <label for="id_medico" class="form-label">Seleziona Medico</label>
                    <select name="id_medico" id="id_medico" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Scegli un medico --</option>
                        <!-- Faccio vedere tutti i medici disponibili -->
                        <?php foreach ($medici as $medico): ?>
                            <option value="<?php echo $medico['id']; ?>" <?php echo ($id_medico_selezionato === $medico['id']) ? 'selected' : ''; ?>>
                                Dott. <?php echo htmlspecialchars($medico['cognome'] . ' ' . $medico['nome']); ?>
                                (<?php echo htmlspecialchars($medico['codice_fiscale']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if ($medico_selezionato): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        Agenda di Dott.
                        <?php echo htmlspecialchars($medico_selezionato['nome'] . ' ' . $medico_selezionato['cognome']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Giorno</th>
                                    <th scope="col">Ora Inizio</th>
                                    <th scope="col">Ora Fine</th>
                                    <th scope="col" class="text-end">Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Mostro i dati raccolti in precedenza dopo aver selezionato il medico -->
                                <?php foreach ($giorni_settimana as $indice => $nomeGiorno): ?>
                                    <?php
                                    $haTurno = isset($orari[$indice]);
                                    $inizio = $haTurno ? $orari[$indice]['inizio'] : '-';
                                    $fine = $haTurno ? $orari[$indice]['fine'] : '-';
                                    ?>
                                    <tr>
                                        <td><?php echo $nomeGiorno; ?></td>
                                        <td><?php echo $inizio; ?></td>
                                        <td><?php echo $fine; ?></td>
                                        <td class="text-end">
                                            <?php if ($haTurno): ?>
                                                <span class="badge bg-success">In Servizio</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Riposo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($id_medico_selezionato > 0): ?>
            <div class="alert alert-warning">
                Il medico selezionato non è valido o non appartiene al tuo centro.
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>