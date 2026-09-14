<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Controllo sempre se il ruolo corrisponde
if ($utenteLoggato['ruolo'] !== 'cliente') {
    die("Accesso negato. Solo i clienti possono visualizzare questa pagina.");
}

$pageTitle = "Storico Fatture";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_cliente = $utenteLoggato['id'];

// Recupero i metodi di pagamento attivi dal DB
$stmtMetodi = $db->query("SELECT id, descrizione FROM Metodo_Pagamento WHERE attivo = 1 ORDER BY descrizione ASC");
$metodiPagamento = $stmtMetodi->fetchAll(PDO::FETCH_ASSOC);

// Con questa query invece, devo recuperare i dati delle fatture. Come da schema ho specializzato le fatture in due
// tabelle diverse, in base alla convenzione con assicurazione o no. Nella pagina pero' le voglio mostrare nella stessa
// tabella....senza distinzione, quindi faccio una UNION ALL per unire i risultati, formattando i nomi delle colonne e
// settando a null i campi assicurazione che non sono ovviamente presenti nella tabella "classica" delle fatture.
$query = "
    SELECT 
        f.id AS fattura_id, 
        f.numero_fattura, 
        f.data_emissione, 
        f.importo_totale, 
        f.importo_totale AS prezzo_finale,
        0.00 AS sconto_applicato,
        NULL AS codice_assicurazione,
        NULL AS codice_voucher,
        f.stato AS fattura_stato,
        mp.descrizione AS metodo_pagamento,
        v.codice AS codice_visita, 
        v.data_visita, 
        v.ora_inizio,
        s.nome AS spec_nome,
        u.nome AS medico_nome, 
        u.cognome AS medico_cognome
    FROM Fattura f
    JOIN Visita v ON f.codice_visita = v.codice
    LEFT JOIN Metodo_Pagamento mp ON f.id_metodo_pagamento = mp.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN Utente u ON v.id_medico = u.id
    WHERE v.id_cliente = :id_cliente1

    UNION ALL

    SELECT 
        fa.id AS fattura_id, 
        fa.numero_fattura, 
        fa.data_emissione, 
        fa.importo_totale, 
        fa.prezzo_finale,
        fa.sconto_applicato,
        fa.codice_assicurazione,
        fa.codice_voucher,
        fa.stato AS fattura_stato,
        mp.descrizione AS metodo_pagamento,
        v.codice AS codice_visita, 
        v.data_visita, 
        v.ora_inizio,
        s.nome AS spec_nome,
        u.nome AS medico_nome, 
        u.cognome AS medico_cognome
    FROM Fatturazione_Con_Assicurazione fa
    JOIN Visita v ON fa.codice_visita = v.codice
    LEFT JOIN Metodo_Pagamento mp ON fa.id_metodo_pagamento = mp.id
    JOIN Specializzazione s ON v.id_specializzazione = s.id
    LEFT JOIN Utente u ON v.id_medico = u.id
    WHERE v.id_cliente = :id_cliente2

    ORDER BY data_emissione DESC
";

$stmt = $db->prepare($query);
$stmt->execute([
    ':id_cliente1' => $id_cliente,
    ':id_cliente2' => $id_cliente
]);
$fatture = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Funzione per gestire lo stato di una fattura
function getStatoFatturaBadge($stato)
{
    switch ($stato) {
        case 'pagata':
            return '<span class="badge bg-success">Pagata</span>';
        case 'emessa':
            return '<span class="badge bg-warning text-dark">Da Pagare (Emessa)</span>';
        case 'stornata':
            return '<span class="badge bg-danger">Stornata</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($stato) . '</span>';
    }
}
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>Storico Fatture</h3>
            <p>Visualizza e consulta i dettagli delle tue fatture e pagamenti.</p>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (count($fatture) === 0): ?>
                    <div class="text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor"
                            class="bi bi-receipt mb-3" viewBox="0 0 16 16">
                            <path
                                d="M1.92.506a.5.5 0 0 1 .434.14L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.708 0L3 14.707l-.646.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l.5-1a.5.5 0 0 1 .367-.27zM0 2v12c0 .27.22.5.5.5h15a.5.5 0 0 0 .5-.5V2a.5.5 0 0 0-.5-.5H.5a.5.5 0 0 0-.5.5z" />
                            <path
                                d="M3 4.5a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5zm8-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5z" />
                        </svg>
                        <h5>Nessuna fattura presente</h5>
                        <p>Non hai ancora ricevuto alcuna fattura per i tuoi appuntamenti.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">N. Fattura</th>
                                    <th scope="col">Data Emissione</th>
                                    <th scope="col">Visita / Specializzazione</th>
                                    <th scope="col">Importo</th>
                                    <th scope="col" class="text-end">Stato</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fatture as $fattura): ?>
                                    <tr data-bs-toggle="modal"
                                        data-bs-target="#modalFattura<?php echo $fattura['fattura_id']; ?>"
                                        style="cursor: pointer;">
                                        <td>
                                            #<?php echo htmlspecialchars($fattura['numero_fattura']); ?>
                                            <?php if ($fattura['sconto_applicato'] > 0): ?>
                                                <i class="bi bi-shield-check text-success" title="Assicurazione Applicata"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($fattura['data_emissione'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($fattura['spec_nome']); ?>
                                        </td>
                                        <td>
                                            €<?php echo number_format($fattura['prezzo_finale'], 2); ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo getStatoFatturaBadge($fattura['fattura_stato']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal delle fatture -->
<?php foreach ($fatture as $fattura): ?>
    <div class="modal fade" id="modalFattura<?php echo $fattura['fattura_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dettagli Fattura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <div class="text-center mb-4">
                        <div>Fattura N°</div>
                        <h2>
                            <?php echo htmlspecialchars($fattura['numero_fattura']); ?>
                        </h2>
                        <div>
                            <?php echo getStatoFatturaBadge($fattura['fattura_stato']); ?>
                        </div>
                    </div>

                    <div class="bg-light p-3 mb-3 border">
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Data Emissione</div>
                            <div class="col-7 text-end">
                                <?php echo date('d/m/Y H:i', strtotime($fattura['data_emissione'])); ?>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-5 fw-bold">Metodo Pagamento</div>
                            <div class="col-7 text-end">
                                <?php echo htmlspecialchars($fattura['metodo_pagamento'] ?? 'Non saldata'); ?>
                            </div>
                        </div>

                        <hr>

                        <?php if ($fattura['sconto_applicato'] > 0 || !empty($fattura['codice_voucher'])): ?>
                            <div class="row mb-1">
                                <div class="col-6">Importo Iniziale</div>
                                <div class="col-6 text-end text-decoration-line-through">
                                    €<?php echo number_format($fattura['importo_totale'], 2); ?>
                                </div>
                            </div>

                            <?php if ($fattura['sconto_applicato'] > 0): ?>
                                <div class="row mb-1">
                                    <div class="col-6 text-success">
                                        <i class="bi bi-shield-check me-1"></i> Sconto Assicurazione
                                    </div>
                                    <div class="col-6 text-end text-success">
                                        -<?php echo number_format($fattura['sconto_applicato'], 0); ?>%
                                    </div>
                                </div>
                            <?php endif; ?>
                            <hr>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-5 fw-bold align-self-center">Importo Totale
                            </div>
                            <div class="col-7 text-end fw-bold text-success">
                                €<?php echo number_format($fattura['prezzo_finale'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <div class="border p-3 mb-3">
                        <h6>Dettagli Visita Associata</h6>
                        <div class="mb-2">
                            <small class="d-block fw-bold">Data e Ora Visita</small>
                            <span>
                                <?php echo date('d/m/Y', strtotime($fattura['data_visita'])) . ' ore ' . date('H:i', strtotime($fattura['ora_inizio'])); ?>
                            </span>
                        </div>
                        <div class="mb-2">
                            <small class="d-block fw-bold">Specializzazione</small>
                            <span>
                                <?php echo htmlspecialchars($fattura['spec_nome']); ?>
                            </span>
                        </div>
                        <div class="mb-0">
                            <small class="d-block fw-bold">Medico</small>
                            <span>Dott.
                                <?php echo htmlspecialchars($fattura['medico_cognome'] . ' ' . $fattura['medico_nome']); ?></span>
                        </div>
                    </div>

                    <!-- Sezione Pagamento -->
                    <?php if ($fattura['fattura_stato'] === 'emessa'): ?>
                        <div class="border p-3">
                            <form action="/cliente/paga_fattura.php" method="POST">
                                <input type="hidden" name="numero_fattura"
                                    value="<?php echo htmlspecialchars($fattura['numero_fattura']); ?>">

                                <div class="mb-3">
                                    <label for="metodo_pagamento_<?php echo $fattura['fattura_id']; ?>"
                                        class="form-label">Seleziona Metodo di
                                        Pagamento</label>
                                    <select class="form-select" id="metodo_pagamento_<?php echo $fattura['fattura_id']; ?>"
                                        name="id_metodo_pagamento" required>
                                        <option value="" selected disabled>Scegli come pagare...</option>
                                        <!-- Scorro i pagamenti disponibili nella select -->
                                        <?php foreach ($metodiPagamento as $metodo): ?>
                                            <option value="<?php echo $metodo['id']; ?>">
                                                <?php echo htmlspecialchars($metodo['descrizione']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <!-- Formatto il numero per avere due cifre decimali -->
                                    Paga Ora (€<?php echo number_format($fattura['prezzo_finale'], 2); ?>)
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Chiudi Dettagli</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../_layout/footer.php'; ?>