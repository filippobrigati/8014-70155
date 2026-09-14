<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Controllo per il ruolo
if ($utenteLoggato['ruolo'] !== 'cliente') {
    die("Accesso negato. Solo i clienti possono visualizzare questa pagina.");
}

$pageTitle = "Le mie Recensioni";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_cliente = $utenteLoggato['id'];
$message = '';

// Se il metodo e' post ed esiste la variabile inserimento allora eseguo la condizione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inserisci_recensione'])) {
    $codice_visita = (int) $_POST['codice_visita'];
    $punteggio = (int) $_POST['punteggio'];
    $commento = trim($_POST['commento']);

    if ($punteggio < 1 || $punteggio > 5) {
        $message = '<div class="alert alert-danger">Il punteggio deve essere compreso tra 1 e 5.</div>';
    } else {
        try {
            // Faccio il controllo se la visita effettivamente corrisponde con chi sta facendo la recensione
            $stmtCheckVisita = $db->prepare("SELECT id_medico FROM Visita WHERE codice = :codice AND id_cliente = :id_cliente AND stato = 'completata'");
            $stmtCheckVisita->execute([':codice' => $codice_visita, ':id_cliente' => $id_cliente]);
            $visitaInfo = $stmtCheckVisita->fetch(PDO::FETCH_ASSOC);

            if (!$visitaInfo) {
                $message = '<div class="alert alert-danger">Visita non valida o non completata.</div>';
            } else {
                // Controllo che il cliente non abbia gia' recensito quel determinato medico
                $stmtCheckDuplicate = $db->prepare("
                    SELECT COUNT(*) FROM Recensione r
                    JOIN Visita v ON r.codice_visita = v.codice
                    WHERE v.id_cliente = :id_cliente AND v.id_medico = :id_medico
                ");
                $stmtCheckDuplicate->execute([':id_cliente' => $id_cliente, ':id_medico' => $visitaInfo['id_medico']]);

                if ($stmtCheckDuplicate->fetchColumn() > 0) {
                    $message = '<div class="alert alert-warning">Hai già recensito questo medico. È consentita una sola recensione per medico.</div>';
                } else {
                    // Se tutto viene controllato con esito positivo allora inserisco la recensione
                    $stmtInsert = $db->prepare("INSERT INTO Recensione (codice_visita, punteggio, commento) VALUES (:codice, :punteggio, :commento)");
                    $stmtInsert->execute([
                        ':codice' => $codice_visita,
                        ':punteggio' => $punteggio,
                        ':commento' => empty($commento) ? null : $commento
                    ]);
                    $message = '<div class="alert alert-success">Recensione inviata con successo! Grazie per il tuo feedback.</div>';
                }
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Errore durante il salvataggio: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Recupero tutti i dottori, che hanno una visita con l'utente loggato
// che non ha ancora recensito quel determinato medico, grazie al not in
$queryDaRecensire = "
    SELECT 
        v.codice AS codice_visita,
        v.data_visita,
        u.id AS id_medico,
        u.nome AS medico_nome,
        u.cognome AS medico_cognome,
        s.nome AS spec_nome
    FROM Visita v
    JOIN Utente u ON v.id_medico = u.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    WHERE v.codice IN (
        SELECT MAX(codice) 
        FROM Visita 
        WHERE id_cliente = :id_cliente1 AND stato = 'completata' 
        GROUP BY id_medico
    )
    AND v.id_medico NOT IN (
        SELECT v2.id_medico 
        FROM Recensione r
        JOIN Visita v2 ON r.codice_visita = v2.codice
        WHERE v2.id_cliente = :id_cliente2
    )
    ORDER BY v.data_visita DESC
";
$stmtDaRecensire = $db->prepare($queryDaRecensire);
$stmtDaRecensire->execute([
    ':id_cliente1' => $id_cliente,
    ':id_cliente2' => $id_cliente
]);
$mediciDaRecensire = $stmtDaRecensire->fetchAll(PDO::FETCH_ASSOC);


// Seleziono le recensioni gia inviate
$queryRecensioni = "
    SELECT 
        r.punteggio, 
        r.commento, 
        r.data_inserimento,
        v.data_visita,
        COALESCE(v.storico_nome_medico, u.nome) AS medico_nome,
        COALESCE(v.storico_cognome_medico, u.cognome) AS medico_cognome,
        s.nome AS spec_nome
    FROM Recensione r
    JOIN Visita v ON r.codice_visita = v.codice
    LEFT JOIN Utente u ON v.id_medico = u.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    WHERE v.id_cliente = :id_cliente
    ORDER BY r.data_inserimento DESC
";
$stmtRecensioni = $db->prepare($queryRecensioni);
$stmtRecensioni->execute([':id_cliente' => $id_cliente]);
$recensioniFatte = $stmtRecensioni->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>Le mie Recensioni</h3>
            <p>Valuta i medici con cui hai effettuato una visita.</p>
        </div>

        <?php if ($message)
            echo $message; ?>

        <h5 class="mb-3">Da Recensire</h5>
        <?php if (count($mediciDaRecensire) === 0): ?>
            <div class="alert alert-info text-center mb-5">
                <i class="bi bi-star mb-2 d-block"></i>
                <h6>Nessun medico da recensire.</h6>
                <small>Hai già valutato tutti i medici o non hai ancora completato visite.</small>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
                <?php foreach ($mediciDaRecensire as $medico): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-person-fill fs-2"></i>
                                </div>
                                <h5>Dott.
                                    <?php echo htmlspecialchars($medico['medico_cognome'] . ' ' . $medico['medico_nome']); ?>
                                </h5>
                                <p>
                                    <?php echo htmlspecialchars($medico['spec_nome']); ?>
                                </p>
                                <span class="badge bg-secondary mb-3">Ultima visita:
                                    <?php echo date('d/m/Y', strtotime($medico['data_visita'])); ?>
                                </span>
                                <br>
                                <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal"
                                    data-bs-target="#modalRecensione<?php echo $medico['codice_visita']; ?>">
                                    <i class="bi bi-star-fill me-1"></i> Lascia una Recensione
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="modalRecensione<?php echo $medico['codice_visita']; ?>" tabindex="-1"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Valuta il Dott.
                                        <?php echo htmlspecialchars($medico['medico_cognome']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <div class="modal-body">
                                        <input type="hidden" name="codice_visita"
                                            value="<?php echo $medico['codice_visita']; ?>">

                                        <p>Come valuti la tua esperienza relativa alla visita del
                                            <?php echo date('d/m/Y', strtotime($medico['data_visita'])); ?>?
                                        </p>

                                        <div class="mb-3">
                                            <label for="voto_<?php echo $medico['codice_visita']; ?>" class="form-label">Voto da
                                                1 a 5</label>
                                            <select class="form-select" id="voto_<?php echo $medico['codice_visita']; ?>"
                                                name="punteggio" required>
                                                <option value="" selected disabled>Seleziona un voto...</option>
                                                <option value="5">5/5 - Eccellente</option>
                                                <option value="4">4/5 - Molto buono</option>
                                                <option value="3">3/5 - Buono</option>
                                                <option value="2">2/5 - Sufficiente</option>
                                                <option value="1">1/5 - Scarso</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Commento (Opzionale)</label>
                                            <textarea class="form-control" name="commento" rows="4"
                                                placeholder="Scrivi qui la tua opinione sul medico e la visita..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                        <button type="submit" name="inserisci_recensione" class="btn btn-primary">Invia
                                            Recensione</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h5 class="mb-3">Le Tue Recensioni Pubblicate</h5>
        <?php if (count($recensioniFatte) === 0): ?>
            <p>Non hai ancora pubblicato nessuna recensione.</p>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-lg-2 g-4">
                <?php foreach ($recensioniFatte as $rec): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6>Dott.
                                            <?php echo htmlspecialchars($rec['medico_cognome'] . ' ' . $rec['medico_nome']); ?>
                                        </h6>
                                        <small>
                                            <?php echo htmlspecialchars($rec['spec_nome']); ?>
                                        </small>
                                    </div>

                                    <div class="badge bg-warning text-dark">
                                        <i class="bi bi-star-fill me-1"></i> <?php echo $rec['punteggio']; ?>/5
                                    </div>

                                </div>
                                <hr>
                                <?php if ($rec['commento']): ?>
                                    <p>"<?php echo nl2br(htmlspecialchars($rec['commento'])); ?>"</p>
                                <?php else: ?>
                                    <p class="text-muted">Nessun commento testuale inserito.</p>
                                <?php endif; ?>
                                <div class="text-end mt-3">
                                    <small>Pubblicata il:
                                        <?php echo date('d/m/Y', strtotime($rec['data_inserimento'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>