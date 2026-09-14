<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Controllo medico
if ($utenteLoggato['ruolo'] !== 'medico') {
    die("Accesso negato. Solo i medici possono visualizzare questa pagina.");
}

$pageTitle = "Prossimi Appuntamenti";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_medico = $utenteLoggato['id'];
$message = '';

// Controllo per capire se ho triggerato la funzione di emissione fattura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emetti_fattura'])) {
    $codice_visita = (int)$_POST['codice_visita'];
    $codice_voucher = !empty(trim($_POST['codice_voucher'])) ? trim($_POST['codice_voucher']) : null;
    
    try {
        // Chiamo la procedura per emettere la fattura
        $stmtEmetti = $db->prepare("CALL EmettiFattura(:codice, :voucher)");
        $stmtEmetti->execute([
            ':codice' => $codice_visita, 
            ':voucher' => $codice_voucher
        ]);
        $result = $stmtEmetti->fetch(PDO::FETCH_ASSOC);
        $stmtEmetti->closeCursor();
        // Log fattura
        if ($result['esito'] === 'SUCCESS') {
            require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
            $logger = new \Utils\MongoLogger();
            $logger->logAzione(
                $utenteLoggato['id'], 
                $utenteLoggato['ruolo'],
                'CREATE', 
                'Fattura', 
                $result['numero_fattura'],
                [
                    'codice_visita' => $codice_visita,
                    'codice_voucher' => $codice_voucher,
                    'nota' => 'Generata tramite pannello medico'
                ]
            );

            $message = '<div class="alert alert-success">Fattura <strong>' . htmlspecialchars($result['numero_fattura']) . '</strong> emessa con successo e visita completata.</div>';
        } else {
            $message = '<div class="alert alert-danger">Errore: ' . htmlspecialchars($result['messaggio']) . '</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Errore di sistema: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Se post e se funzione emetti referto allora emetto il referto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emetti_referto'])) {
    $codice_visita = (int)$_POST['codice_visita'];
    $testo_referto = trim($_POST['testo_referto']);
    
    if (empty($testo_referto)) {
        $message = '<div class="alert alert-danger">Il testo del referto non può essere vuoto.</div>';
    } else {
        try {
            // Chiamo la procedura passando il testo del referto e il codice della visita
            $stmtRef = $db->prepare("CALL EmettiReferto(:codice, :testo)");
            $stmtRef->execute([':codice' => $codice_visita, ':testo' => $testo_referto]);
            $result = $stmtRef->fetch(PDO::FETCH_ASSOC);
            $stmtRef->closeCursor();
            // Log dell'azione
            if ($result['esito'] === 'SUCCESS') {
                require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
                $logger = new \Utils\MongoLogger();
                $logger->logAzione(
                    $utenteLoggato['id'], 
                    $utenteLoggato['ruolo'],
                    'CREATE', 
                    'Referto', 
                    $codice_visita,
                    [
                        'lunghezza_testo' => strlen($testo_referto),
                        'nota' => 'Referto compilato post-visita'
                    ]
                );

                $message = '<div class="alert alert-success">Referto salvato con successo per la visita. Ora puoi emettere la fattura.</div>';
            } else {
                $message = '<div class="alert alert-danger">Errore: ' . htmlspecialchars($result['messaggio']) . '</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Errore di sistema: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Logica di paginazione
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Ottengo il numero di prossimi appuntamenti del medico
$stmtCount = $db->prepare("CALL GetProssimiAppuntamentiMedico_Count(:id_medico)");
$stmtCount->bindValue(':id_medico', $id_medico, PDO::PARAM_INT);
$stmtCount->execute();
$totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
$total = $totalRow ? (int)$totalRow['total'] : 0;
$stmtCount->closeCursor();

$totalPages = ceil($total / $limit);

// Ottengo la lista dei prossimi apputamenti tramite paginazione
$stmt = $db->prepare("CALL GetProssimiAppuntamentiMedico(:id_medico, :limit, :offset)");
$stmt->bindValue(':id_medico', $id_medico, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$appuntamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$giorni_it = ['Sunday' => 'Domenica', 'Monday' => 'Lunedì', 'Tuesday' => 'Martedì', 'Wednesday' => 'Mercoledì', 'Thursday' => 'Giovedì', 'Friday' => 'Venerdì', 'Saturday' => 'Sabato'];
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>Prossimi Appuntamenti</h3>
            <p>Gestisci i pazienti, scrivi referti ed emetti fatture.</p>
        </div>

        <?php if ($message) echo $message; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Data e Ora</th>
                                <th scope="col">Paziente</th>
                                <th scope="col">Specializzazione</th>
                                <th scope="col" class="text-end">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($appuntamenti) === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <i class="bi bi-calendar2-check fs-2 d-block mb-2"></i>
                                        Nessun appuntamento trovato.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appuntamenti as $visita): 
                                    $timestamp_visita = strtotime($visita['data_visita'] . ' ' . $visita['ora_inizio']);
                                    $giorno_nome = $giorni_it[date('l', $timestamp_visita)];
                                    $data_formattata = date('d/m/Y', $timestamp_visita);
                                    $ora = date('H:i', $timestamp_visita);
                                    
                                    $nascita = new DateTime($visita['data_nascita']);
                                    $oggi = new DateTime('today');
                                    $eta = $nascita->diff($oggi)->y;

                                    $puo_emettere = $timestamp_visita <= time();
                                    $ha_referto = (bool)$visita['ha_referto'];
                                ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block"><?php echo $data_formattata; ?></strong>
                                            <small><?php echo $giorno_nome; ?> • <strong><?php echo $ora; ?></strong></small>
                                        </td>
                                        <td>
                                            <strong class="d-block"><?php echo htmlspecialchars($visita['paziente_cognome'] . ' ' . $visita['paziente_nome']); ?></strong>
                                            <small class="font-monospace"><?php echo htmlspecialchars($visita['codice_fiscale']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($visita['spec_nome']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalPaziente<?php echo $visita['codice']; ?>">
                                                    Dettagli
                                                </button>
                                                
                                                <?php if ($ha_referto): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success disabled" style="pointer-events: none;">
                                                        <i class="bi bi-check-circle me-1"></i> Refertato
                                                    </button>
                                                <?php elseif ($puo_emettere && !$ha_referto): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReferto<?php echo $visita['codice']; ?>">
                                                        Scrivi Referto
                                                    </button>
                                                <?php else: ?>
                                                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Disponibile solo al termine della visita">
                                                        <button type="button" class="btn btn-sm btn-outline-primary disabled" style="pointer-events: none; opacity: 0.5;">
                                                            Scrivi Referto
                                                        </button>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if ($puo_emettere && $ha_referto): ?>
                                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalEmettiFattura<?php echo $visita['codice']; ?>">
                                                        Emetti Fattura
                                                    </button>
                                                <?php else: ?>
                                                    <?php $tooltip_msg = !$puo_emettere ? "Disponibile solo al termine della visita" : "Scrivi prima il referto medico"; ?>
                                                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="<?php echo $tooltip_msg; ?>">
                                                        <button type="button" class="btn btn-sm btn-success disabled" style="pointer-events: none; opacity: 0.5;">
                                                            Emetti Fattura
                                                        </button>
                                                    </span>
                                                <?php endif; ?>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <div class="modal fade" id="modalEmettiFattura<?php echo $visita['codice']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Emissione Fattura</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="codice_visita" value="<?php echo $visita['codice']; ?>">
                                                        
                                                        <p>Stai per emettere la fattura per <strong><?php echo htmlspecialchars($visita['paziente_cognome'] . ' ' . $visita['paziente_nome']); ?></strong>.</p>
                                                        
                                                        <div class="mb-3">
                                                            <label for="voucher_<?php echo $visita['codice']; ?>" class="form-label fw-bold">Codice Voucher (Opzionale)</label>
                                                            <input type="text" class="form-control" id="voucher_<?php echo $visita['codice']; ?>" name="codice_voucher" placeholder="Es. PROMO2026">
                                                        </div>
                                                        
                                                        <div class="alert alert-info small mb-0">
                                                            Se inserisci un codice voucher e il paziente possiede un'assicurazione registrata, verrà generata una fattura assicurativa e lo sconto applicato. Lasciando il campo vuoto, verrà emessa una fattura standard.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="emetti_fattura" class="btn btn-success">Genera ed Emetti</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="modalReferto<?php echo $visita['codice']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Scrivi Referto Medico</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <div class="alert alert-light border mb-4">
                                                            <div class="row">
                                                                <div class="col-sm-6">
                                                                    <small class="d-block fw-bold">Paziente</small>
                                                                    <strong><?php echo htmlspecialchars($visita['paziente_cognome'] . ' ' . $visita['paziente_nome']); ?></strong>
                                                                </div>
                                                                <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                                                                    <small class="d-block fw-bold">Prestazione e Data</small>
                                                                    <span><?php echo htmlspecialchars($visita['spec_nome']); ?> - <?php echo $data_formattata; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <input type="hidden" name="codice_visita" value="<?php echo $visita['codice']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label for="testo_referto_<?php echo $visita['codice']; ?>" class="form-label fw-bold">Testo del Referto</label>
                                                            <textarea class="form-control" id="testo_referto_<?php echo $visita['codice']; ?>" name="testo_referto" rows="8" required placeholder="Inserisci qui l'esito della visita, diagnosi e prescrizioni..."></textarea>
                                                        </div>
                                                        <p class="text-muted small mb-0">Una volta salvato, il referto non potrà essere modificato e sarà visibile al paziente.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="emetti_referto" class="btn btn-primary">Salva Referto</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="modal fade" id="modalPaziente<?php echo $visita['codice']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Scheda Appuntamento</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-4 bg-light p-3 border">
                                                        <div class="fs-5 fw-bold"><?php echo "$giorno_nome $data_formattata"; ?></div>
                                                        <div class="fs-4 fw-bold"><?php echo $ora; ?></div>
                                                    </div>
                                                    
                                                    <div class="bg-light p-3 mb-3 border">
                                                        <h6 class="fw-bold mb-3">Dati Paziente</h6>
                                                        <div class="mb-2 fs-5 fw-bold">
                                                            <?php echo htmlspecialchars($visita['paziente_cognome'] . ' ' . $visita['paziente_nome']); ?>
                                                        </div>
                                                        <div class="row mb-2">
                                                            <div class="col-5">Codice Fiscale</div>
                                                            <div class="col-7 text-end font-monospace"><?php echo htmlspecialchars($visita['codice_fiscale']); ?></div>
                                                        </div>
                                                        <div class="row mb-2">
                                                            <div class="col-5">Data di nascita</div>
                                                            <div class="col-7 text-end"><?php echo date('d/m/Y', strtotime($visita['data_nascita'])) . " ({$eta} anni)"; ?></div>
                                                        </div>
                                                        <hr>
                                                        <div class="row mb-2">
                                                            <div class="col-5 align-self-center">Email</div>
                                                            <div class="col-7 text-end">
                                                                <a href="mailto:<?php echo htmlspecialchars($visita['email']); ?>">
                                                                    <?php echo htmlspecialchars($visita['email']); ?>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-5 align-self-center">Telefono</div>
                                                            <div class="col-7 text-end">
                                                                <?php if ($visita['telefono']): ?>
                                                                    <a href="tel:<?php echo htmlspecialchars($visita['telefono']); ?>">
                                                                        <?php echo htmlspecialchars($visita['telefono']); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="text-muted fst-italic">Non fornito</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="bg-light p-3 border">
                                                        <h6 class="fw-bold mb-2">Prestazione Richiesta</h6>
                                                        <div class="fw-bold mb-2"><?php echo htmlspecialchars($visita['spec_nome']); ?></div>
                                                        <small>Prezzo concordato: €<?php echo number_format($visita['prezzo_applicato'], 2); ?></small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Chiudi</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1 . $queryString; ?>">Precedente</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i . $queryString; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1 . $queryString; ?>">Successivo</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require __DIR__ . '/../_layout/footer.php'; ?>