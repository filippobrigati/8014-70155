<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

$pageTitle = "Storico visite - Centro Medico";
require __DIR__ . '/../_layout/header.php';

// Sempre dati per la paginazione, con calcolo limit e offset
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = \config\Database::getInstance()->getMySQL();
$id_cliente = $utenteLoggato['id'];

// Seleziono tutte le visite completate, per ottenerne il numero totale
$countQuery = "SELECT COUNT(*) FROM Visita WHERE stato = 'completata' AND id_cliente = :id_cliente";
$stmtCount = $db->prepare($countQuery);
$stmtCount->execute([':id_cliente' => $id_cliente]);
$total = $stmtCount->fetchColumn();
$totalPages = ceil($total / $limit);

// Recupero le informazioni della visita, in caso in cui lo storico sia null
// prova a recuperare le info del medico direttamente dalla tabella del medico
$query = "
    SELECT 
        v.*, 
        s.nome AS nome_specializzazione,
        COALESCE(v.storico_nome_medico, m.nome) AS medico_nome,
        COALESCE(v.storico_cognome_medico, m.cognome) AS medico_cognome,
        COALESCE(v.storico_nome_cliente, c.nome) AS cliente_nome,
        COALESCE(v.storico_cognome_cliente, c.cognome) AS cliente_cognome,
        r.testo_referto,
        r.data_creazione AS data_referto
    FROM Visita v
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN Utente m ON v.id_medico = m.id
    LEFT JOIN Utente c ON v.id_cliente = c.id
    LEFT JOIN Referto r ON v.codice = r.codice_visita
    WHERE v.stato = 'completata' AND v.id_cliente = :id_cliente
    ORDER BY v.data_visita DESC, v.ora_inizio DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);
$stmt->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$visite = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <h3>Storico visite</h3>
        <p>Stai visualizzando lo storico visite di
            <strong><?php echo htmlspecialchars($utenteLoggato['nome'] . ' ' . $utenteLoggato['cognome']); ?></strong>
        </p>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Data e Ora</th>
                                <th scope="col">Specializzazione</th>
                                <th scope="col">Medico</th>
                                <th scope="col">Prezzo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($visite) > 0): ?>
                                <?php foreach ($visite as $visita): ?>
                                    <tr onclick="apriDettaglioVisita(this)"
                                        data-visita='<?php echo htmlspecialchars(json_encode($visita), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($visita['data_visita'])); ?></strong><br>
                                            <small><?php echo date('H:i', strtotime($visita['ora_inizio'])); ?></small>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-primary"><?php echo htmlspecialchars($visita['nome_specializzazione']); ?></span>
                                            <?php if (!empty($visita['testo_referto'])): ?>
                                                <br><span class="badge bg-success"><i class="bi bi-file-medical"></i> Referto</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars("Dr. " . $visita['medico_nome'] . " " . $visita['medico_cognome']); ?>
                                        </td>
                                        <td>
                                            &euro; <?php echo number_format($visita['prezzo_applicato'], 2, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">Nessuna visita completata nello
                                        storico.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Precedente</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Successivo</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDettaglioVisita" tabindex="-1" aria-labelledby="modalDettaglioVisitaLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDettaglioVisitaLabel">Dettagli Visita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center mb-4">
                    <div class="ms-3">
                        <h5 id="modalSpecializzazione">Specializzazione</h5>
                        <p id="modalDataOra">Data e Ora</p>
                    </div>
                </div>

                <div class="bg-light p-3 mb-3 border">
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="d-block fw-bold">Medico</small>
                            <strong id="modalMedico">Dr. Nome Cognome</strong>
                        </div>
                        <div class="col-6 text-end">
                            <small class="d-block fw-bold">Prezzo Applicato</small>
                            <strong id="modalPrezzo">&euro; 0,00</strong>
                        </div>
                        <div class="col-6 mt-3">
                            <small class="d-block fw-bold">Paziente</small>
                            <span id="modalPaziente">Nome Cognome</span>
                        </div>
                        <div class="col-6 mt-3 text-end">
                            <small class="d-block fw-bold">Codice Visita</small>
                            <span id="modalCodice">#0</span>
                        </div>
                    </div>
                </div>

                <div id="modalRefertoContainer" class="d-none mt-4">
                    <h6 class="fw-bold"><i class="bi bi-file-earmark-medical me-2"></i>Referto Medico
                    </h6>
                    <div class="p-3 border">
                        <div id="modalRefertoTesto" style="white-space: pre-wrap;">
                        </div>
                        <hr>
                        <div class="text-end small" id="modalRefertoData"></div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>

<script>
    let modalDettaglioVisita;

    document.addEventListener('DOMContentLoaded', function () {
        modalDettaglioVisita = new bootstrap.Modal(document.getElementById('modalDettaglioVisita'));
    });

    function apriDettaglioVisita(rowElement) {
        const visitaData = rowElement.getAttribute('data-visita');
        if (!visitaData) return;

        const visita = JSON.parse(visitaData);

        const dataParts = visita.data_visita.split('-');
        const dataFormattata = `${dataParts[2]}/${dataParts[1]}/${dataParts[0]}`;

        const oraParts = visita.ora_inizio.split(':');
        const oraFormattata = `${oraParts[0]}:${oraParts[1]}`;

        document.getElementById('modalSpecializzazione').textContent = visita.nome_specializzazione;
        document.getElementById('modalDataOra').textContent = `${dataFormattata} alle ore ${oraFormattata}`;
        document.getElementById('modalMedico').textContent = `Dr. ${visita.medico_nome} ${visita.medico_cognome}`;
        document.getElementById('modalPaziente').textContent = `${visita.cliente_nome} ${visita.cliente_cognome}`;
        document.getElementById('modalPrezzo').textContent = `€ ${parseFloat(visita.prezzo_applicato).toFixed(2).replace('.', ',')}`;
        document.getElementById('modalCodice').textContent = `#${visita.codice}`;

        const refertoContainer = document.getElementById('modalRefertoContainer');
        if (visita.testo_referto) {
            document.getElementById('modalRefertoTesto').textContent = visita.testo_referto;

            const d = new Date(visita.data_referto);
            const dataRif = d.toLocaleDateString('it-IT');
            const oraRif = d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });

            document.getElementById('modalRefertoData').innerHTML = `<em>Emesso il: ${dataRif} alle ${oraRif}</em>`;
            refertoContainer.classList.remove('d-none');
        } else {
            document.getElementById('modalRefertoTesto').textContent = '';
            document.getElementById('modalRefertoData').innerHTML = '';
            refertoContainer.classList.add('d-none');
        }

        modalDettaglioVisita.show();
    }
</script>

<?php require __DIR__ . '/../_layout/footer.php'; ?>