<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Controllo che il ruolo sia utente
if ($utenteLoggato['ruolo'] !== 'cliente') {
    die("Accesso negato. Solo i clienti possono visualizzare questa pagina.");
}

$pageTitle = "I Miei Appuntamenti";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_cliente = $utenteLoggato['id'];
$message = '';

// Controllo se il metodo e' post e se e' presente la variabile annulla visita, nel caso devo eseguire un annullo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annulla_visita'])) {
    // recupero il codice della visita
    $codice_visita = (int) $_POST['codice_visita'];

    // Verifico che chi prenota sia effettivamente l'utente che vuole eliminare la visite in quel momento
    // questo evita che io possa eliminare le visite di un altro cliente iscritto
    $stmtCheck = $db->prepare("SELECT data_visita FROM Visita WHERE codice = :codice AND id_cliente = :id AND stato = 'prenotata'");
    $stmtCheck->execute([':codice' => $codice_visita, ':id' => $id_cliente]);
    $visitaCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($visitaCheck) {
        $data_visita_obj = new DateTime($visitaCheck['data_visita']);
        $oggi_obj = new DateTime('today');
        // controllo che la data della visita non sia < di 3 giorni a partire da oggi
        $diff = $oggi_obj->diff($data_visita_obj);
        // calcolo i giorni di differenza
        $giorni_mancanti = $diff->invert ? -$diff->days : $diff->days;

        // eseguo controllo effettivo
        if ($giorni_mancanti >= 3) {
            try {
                // Aggiorno lo stato se passo il controllo
                $stmtCancel = $db->prepare("UPDATE Visita SET stato = 'disdetta' WHERE codice = :codice");
                $stmtCancel->execute([':codice' => $codice_visita]);

                // Importo la classe che si occupa dei log ed inserisco nella collection l'evento
                require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
                $logger = new \Utils\MongoLogger();
                $logger->logAzione(
                    $utenteLoggato['id'],
                    $utenteLoggato['ruolo'],
                    'CANCEL',
                    'Visita',
                    $codice_visita,
                    [
                        'motivo' => 'Annullata dal cliente online',
                        'giorni_di_preavviso' => $giorni_mancanti
                    ]
                );
                // Torno dall'utente con un alert di successo, se no di errore
                $message = '<div class="alert alert-success">Visita annullata con successo.</div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Errore durante l\'annullamento: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Non puoi annullare una visita a meno di 3 giorni dalla data prevista. Contatta direttamente il centro medico.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Richiesta non valida.</div>';
    }
}

// Recupero i dati completi della visita.
// Prima seleziono quelli che mis servono della visita stessa, poi joino tramite id_medico la
// tabella dei medici per recuperare le sue informazioni (che poi e' la tabella Utenti)
// poi faccio una join con la specializzazione per trovare il nome della visita.
// Poi con i vari left join vado a recuperare le informazioni secondarie che sono associate alla
// visita come indirizzo e partita iva del centro medico in cui si svolgera' la visita stessa.
// Tutto questo ordinato per data visita, lo stato deve essere prenotato
$query = "
    SELECT 
        v.codice, v.data_visita, v.ora_inizio, v.prezzo_applicato, 
        u.id AS id_medico, u.nome AS medico_nome, u.cognome AS medico_cognome, u.email AS medico_email,
        s.id AS id_spec, s.nome AS spec_nome,
        cm.partita_iva AS id_centro, cm.ragione_sociale, cm.via, cm.civico, cm.cap, 
        cc.comune, cc.provincia
    FROM Visita v
    JOIN Utente u ON v.id_medico = u.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN Medico_Centro mc ON u.id = mc.id_medico
    LEFT JOIN Centro_Medico cm ON mc.partita_iva_centro = cm.partita_iva
    LEFT JOIN Citta_CAP cc ON cm.cap = cc.cap
    WHERE v.id_cliente = :id_cliente 
      AND v.stato = 'prenotata'
    ORDER BY v.data_visita ASC, v.ora_inizio ASC
";

$stmt = $db->prepare($query);
$stmt->execute([':id_cliente' => $id_cliente]);
$appuntamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Questi servono per convertire il "formato php" dei giorni e mesi in quello italiano
$giorni_it = ['Sunday' => 'Domenica', 'Monday' => 'Lunedì', 'Tuesday' => 'Martedì', 'Wednesday' => 'Mercoledì', 'Thursday' => 'Giovedì', 'Friday' => 'Venerdì', 'Saturday' => 'Sabato'];
$mesi_it = ['January' => 'Gennaio', 'February' => 'Febbraio', 'March' => 'Marzo', 'April' => 'Aprile', 'May' => 'Maggio', 'June' => 'Giugno', 'July' => 'Luglio', 'August' => 'Agosto', 'September' => 'Settembre', 'October' => 'Ottobre', 'November' => 'Novembre', 'December' => 'Dicembre'];
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h3>I Miei Appuntamenti</h3>
                <p>Visualizza o gestisci le tue visite mediche prenotate.</p>
            </div>
            <a href="/cliente/prenota.php" class="btn btn-primary">
                + Nuova Prenotazione
            </a>
        </div>

        <?php if ($message)
            echo $message; ?>

        <?php if (count($appuntamenti) === 0): ?>
            <div class="alert alert-info text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor"
                    class="bi bi-calendar2-x mb-3" viewBox="0 0 16 16">
                    <path
                        d="M6.146 8.146a.5.5 0 0 1 .708 0L8 9.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 10l1.147 1.146a.5.5 0 0 1-.708.708L8 10.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 10 6.146 8.854a.5.5 0 0 1 0-.708z" />
                    <path
                        d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z" />
                    <path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z" />
                </svg>
                <h5>Nessun appuntamento in programma</h5>
                <p>Non hai visite prenotate al momento.</p>
                <a href="/cliente/prenota.php" class="btn btn-outline-primary mt-2">Prenota ora</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($appuntamenti as $visita):
                    // Recupero il timestamp in base alla data della visita
                    $timestamp = strtotime($visita['data_visita']);
                    // Qua la funzione date del timestamp con 'l' mi ritorna il giono in inglese, grazie alla mappa lo converto in italiano
                    $giorno_nome = $giorni_it[date('l', $timestamp)];
                    // Ottengo il numero giorno
                    $giorno_num = date('d', $timestamp);
                    // Con la stessa logica del giorno ottengo il nome in italiano del mese grazie alla lettera F
                    $mese_nome = $mesi_it[date('F', $timestamp)];
                    // Annno
                    $anno = date('Y', $timestamp);
                    // Ora di inizio formattata come orario + minuti: 15:40
                    $ora = date('H:i', strtotime($visita['ora_inizio']));

                    // Controllo se la data e' < 3
                    $data_visita_obj = new DateTime($visita['data_visita']);
                    $oggi_obj = new DateTime('today');
                    $diff = $oggi_obj->diff($data_visita_obj);
                    $giorni_mancanti = $diff->invert ? -$diff->days : $diff->days;
                    $cancellabile = ($giorni_mancanti >= 3);
                    ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-primary">
                                        <?php echo "$giorno_nome $giorno_num $mese_nome"; ?>
                                    </span>
                                    <span class="fw-bold"><?php echo $ora; ?></span>
                                </div>

                                <h5 class="card-title">
                                    <?php echo htmlspecialchars($visita['spec_nome']); ?>
                                </h5>
                                <p class="card-text">
                                    Dott.
                                    <?php echo htmlspecialchars($visita['medico_cognome'] . ' ' . $visita['medico_nome']); ?>
                                </p>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <span>
                                        €<?php echo number_format($visita['prezzo_applicato'], 2); ?>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalVisita<?php echo $visita['codice']; ?>">
                                        Dettagli
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="modalVisita<?php echo $visita['codice']; ?>" tabindex="-1"
                        aria-labelledby="modalLabel<?php echo $visita['codice']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalLabel<?php echo $visita['codice']; ?>">Dettagli
                                        Appuntamento</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="text-center mb-4">
                                        <h2><?php echo $ora; ?></h2>
                                        <div><?php echo "$giorno_nome $giorno_num $mese_nome $anno"; ?>
                                        </div>
                                        <span class="badge bg-success mt-2">Stato: Prenotata</span>
                                    </div>

                                    <div class="bg-light p-3 mb-3">
                                        <div class="mb-2">
                                            <small class="d-block fw-bold">Specializzazione</small>
                                            <span><?php echo htmlspecialchars($visita['spec_nome']); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <small class="d-block fw-bold">Medico</small>
                                            <span>Dott.
                                                <?php echo htmlspecialchars($visita['medico_cognome'] . ' ' . $visita['medico_nome']); ?></span>
                                            <br>
                                            <a href="mailto:<?php echo htmlspecialchars($visita['medico_email']); ?>">
                                                <?php echo htmlspecialchars($visita['medico_email']); ?>
                                            </a>
                                        </div>
                                        <div>
                                            <small class="d-block fw-bold">Costo Visita</small>
                                            <span>€<?php echo number_format($visita['prezzo_applicato'], 2); ?></span>
                                        </div>
                                    </div>

                                    <div class="bg-light p-3 mb-4">
                                        <small class="d-block fw-bold mb-1">Luogo</small>
                                        <?php if ($visita['ragione_sociale']): ?>
                                            <strong><?php echo htmlspecialchars($visita['ragione_sociale']); ?></strong><br>
                                            <?php echo htmlspecialchars($visita['via'] . ' ' . $visita['civico']); ?><br>
                                            <?php echo htmlspecialchars($visita['cap'] . ' ' . $visita['comune'] . ' (' . $visita['provincia'] . ')'); ?>
                                        <?php else: ?>
                                            <span>Indirizzo centro non disponibile.</span>
                                        <?php endif; ?>
                                    </div>

                                    <h6 class="fw-bold mb-3 border-bottom pb-2">Gestione Appuntamento</h6>

                                    <?php if ($cancellabile): ?>
                                        <div class="row g-2">
                                            <!-- <div class="col-sm-6">
                                                <a href="/cliente/prenota.php?step=4&id_centro=<?php echo urlencode($visita['id_centro']); ?>&id_spec=<?php echo $visita['id_spec']; ?>&id_medico=<?php echo $visita['id_medico']; ?>"
                                                    class="btn btn-outline-primary w-100">
                                                    Riprogramma Ora
                                                </a>
                                            </div> -->
                                            <div class="col-sm-12">
                                                <form method="POST" action=""
                                                    onsubmit="return confirm('Sei sicuro di voler annullare definitivamente questa visita?');">
                                                    <input type="hidden" name="codice_visita"
                                                        value="<?php echo $visita['codice']; ?>">
                                                    <button type="submit" name="annulla_visita" class="btn btn-danger w-100">Annulla
                                                        Visita</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">
                                            Mancano meno di 3 giorni a questa
                                            visita. Non è più possibile annullarla o modificarla autonomamente online. Contatta il
                                            centro medico per necessità.
                                        </div>
                                    <?php endif; ?>

                                </div>
                                <div class="modal-footer">
                                    <p class="w-100 text-center mb-2">
                                        Codice Prenotazione: #<?php echo str_pad($visita['codice'], 6, '0', STR_PAD_LEFT); ?>
                                    </p>
                                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Chiudi
                                        Dettagli</button>
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