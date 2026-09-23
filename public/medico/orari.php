<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

if ($utenteLoggato['ruolo'] !== 'medico') {
    die("Accesso negato. Solo i medici possono gestire l'agenda.");
}

$pageTitle = "Gestione Agenda - Medico";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_medico = $utenteLoggato['id'];
$message = '';

// Mappatura della settimana
$giorni_settimana = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Se inserisco elimino prima l'eventuale agenda gia presente
        $stmtDelete = $db->prepare("DELETE FROM Agenda_Presenze WHERE id_medico = :id_medico");
        $stmtDelete->execute([':id_medico' => $id_medico]);
        // Preparo query per insermento
        $stmtInsert = $db->prepare("
            INSERT INTO Agenda_Presenze (id_medico, giorno_settimana, ora_inizio, ora_fine) 
            VALUES (:id_medico, :giorno, :inizio, :fine)
        ");
        // Inserisco per ogni giorno
        foreach ($giorni_settimana as $giorno => $nome) {
            $inizio = $_POST['orari'][$giorno]['inizio'] ?? '';
            $fine = $_POST['orari'][$giorno]['fine'] ?? '';

            if (!empty($inizio) && !empty($fine)) {
                $stmtInsert->execute([
                    ':id_medico' => $id_medico,
                    ':giorno' => $giorno,
                    ':inizio' => $inizio,
                    ':fine' => $fine
                ]);
            }
        }

        $db->commit();
        $message = '<div class="alert alert-success">Orari aggiornati con successo.</div>';
    } catch (Exception $e) {
        $db->rollBack();
        $message = '<div class="alert alert-danger">Errore di salvataggio: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Seleziono le info correnti per mostrarle in pagina
$stmt = $db->prepare("SELECT giorno_settimana, ora_inizio, ora_fine FROM Agenda_Presenze WHERE id_medico = :id_medico");
$stmt->execute([':id_medico' => $id_medico]);
$agenda_attuale = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $agenda_attuale[$row['giorno_settimana']] = [
        'inizio' => substr($row['ora_inizio'], 0, 5),
        'fine' => substr($row['ora_fine'], 0, 5)
    ];
}
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>La tua Agenda</h3>
            <p>Imposta i tuoi turni di lavoro settimanali</p>
        </div>

        <?php if ($message)
            echo $message; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Giorno</th>
                                    <th scope="col">Ora Inizio</th>
                                    <th scope="col">Ora Fine</th>
                                    <th scope="col" class="text-center">Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($giorni_settimana as $giorno => $nomeGiorno):
                                    $inizio = $agenda_attuale[$giorno]['inizio'] ?? '';
                                    $fine = $agenda_attuale[$giorno]['fine'] ?? '';
                                    $riposaQuelGiorno = !empty($inizio) && !empty($fine);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo $nomeGiorno; ?>
                                        </td>
                                        <td>
                                            <input type="time" class="form-control"
                                                name="orari[<?php echo $giorno; ?>][inizio]"
                                                value="<?php echo htmlspecialchars($inizio); ?>">
                                        </td>
                                        <td>
                                            <input type="time" class="form-control"
                                                name="orari[<?php echo $giorno; ?>][fine]"
                                                value="<?php echo htmlspecialchars($fine); ?>">
                                        </td>
                                        <td class="text-center">
                                            <?php if ($riposaQuelGiorno): ?>
                                                <span class="badge bg-success">Attivo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Riposo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">
                            Salva Orari
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <p class="mt-3">
            Lascia vuoti gli orari di inizio e fine per impostare il giorno come "Riposo".
        </p>
    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>