<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Solito controllo
if ($utenteLoggato['ruolo'] !== 'cliente') {
    die("Accesso negato. Solo i clienti possono prenotare una visita.");
}

$pageTitle = "Prenota Visita";
require __DIR__ . '/../_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$id_cliente = $utenteLoggato['id'];

// Ottengo lo step in corso e gli eventuali dati che mi servono
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$id_centro = $_GET['id_centro'] ?? null;
$id_spec = $_GET['id_spec'] ?? null;
$id_medico = $_GET['id_medico'] ?? null;
$data_visita = $_GET['data'] ?? null;

$message = '';

// Mappa con giorni della settimana -> testo, per farli vedere in html
$giorni_settimana = [
    1 => 'Lunedì',
    2 => 'Martedì',
    3 => 'Mercoledì',
    4 => 'Giovedì',
    5 => 'Venerdì',
    6 => 'Sabato',
    7 => 'Domenica'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_prenotazione'])) {
    $id_centro_post = $_POST['id_centro'];
    $id_spec_post = $_POST['id_spec'];
    $id_medico_post = $_POST['id_medico'];
    $data_post = $_POST['data'];
    $ora_post = $_POST['ora_inizio'] . ':00';

    $stmtCosto = $db->prepare("SELECT costo, durata_minuti FROM Medico_Specializzazione WHERE id_medico = :medico AND id_specializzazione = :spec");
    $stmtCosto->execute([':medico' => $id_medico_post, ':spec' => $id_spec_post]);
    $specInfo = $stmtCosto->fetch(PDO::FETCH_ASSOC);

    if ($specInfo) {
        try {
            $stmtCheckSlot = $db->prepare("SELECT codice FROM Visita WHERE id_medico = :medico AND data_visita = :data AND ora_inizio = :ora AND stato != 'disdetta'");
            $stmtCheckSlot->execute([':medico' => $id_medico_post, ':data' => $data_post, ':ora' => $ora_post]);

            if ($stmtCheckSlot->rowCount() > 0) {
                $message = '<div class="alert alert-danger">Spiacenti, l\'orario selezionato è stato appena prenotato. Riprova.</div>';
                $step = 5;
            } else {
                $stmtAnagrafica = $db->prepare("
                    SELECT id, codice_fiscale, nome, cognome 
                    FROM Utente 
                    WHERE id IN (:cliente, :medico)
                ");
                $stmtAnagrafica->execute([
                    ':cliente' => $id_cliente,
                    ':medico' => $id_medico_post
                ]);
                $utenti = $stmtAnagrafica->fetchAll(PDO::FETCH_ASSOC);

                $storico = [];
                foreach ($utenti as $u) {
                    if ($u['id'] == $id_cliente)
                        $storico['cliente'] = $u;
                    if ($u['id'] == $id_medico_post)
                        $storico['medico'] = $u;
                }

                $stmtBook = $db->prepare("
                    INSERT INTO Visita (
                        id_cliente, id_medico, id_specializzazione, data_visita, 
                        ora_inizio, prezzo_applicato, stato,
                        storico_cf_cliente, storico_nome_cliente, storico_cognome_cliente,
                        storico_nome_medico, storico_cognome_medico
                    )
                    VALUES (
                        :cliente, :medico, :spec, :data, :ora, :prezzo, 'prenotata',
                        :cf_cliente, :nome_cliente, :cognome_cliente,
                        :nome_medico, :cognome_medico
                    )
                ");
                $stmtBook->execute([
                    ':cliente' => $id_cliente,
                    ':medico' => $id_medico_post,
                    ':spec' => $id_spec_post,
                    ':data' => $data_post,
                    ':ora' => $ora_post,
                    ':prezzo' => $specInfo['costo'],
                    ':cf_cliente' => $storico['cliente']['codice_fiscale'],
                    ':nome_cliente' => $storico['cliente']['nome'],
                    ':cognome_cliente' => $storico['cliente']['cognome'],
                    ':nome_medico' => $storico['medico']['nome'],
                    ':cognome_medico' => $storico['medico']['cognome']
                ]);


                $nuovo_codice_visita = $db->lastInsertId();

                require_once __DIR__ . '/../../src/Utils/MongoLogger.php';
                $logger = new \Utils\MongoLogger();
                $logger->logAzione(
                    $utenteLoggato['id'],
                    $utenteLoggato['ruolo'],
                    'CREATE',
                    'Visita',
                    $nuovo_codice_visita,
                    [
                        'id_medico' => $id_medico_post,
                        'data_visita' => $data_post,
                        'ora_inizio' => $ora_post
                    ]
                );

                $step = 7; // ho finito
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Errore durante la prenotazione: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Dati specializzazione non validi.</div>';
    }
}
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="mb-4">
            <h3>Prenota una Nuova Visita</h3>
            <p>Segui i passaggi per fissare il tuo appuntamento.</p>
        </div>

        <?php if ($message)
            echo $message; ?>

        <div class="card mb-4">
            <div class="card-body">

                <?php
                // Primo step. Seleziono il centro
                if ($step === 1):
                    $stmt = $db->query("SELECT * FROM Centro_Medico");
                    $centri = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <h5 class="mb-4">Step 1: Scegli il Centro Medico</h5>
                    <div class="list-group">
                        <?php foreach ($centri as $centro): ?>
                            <a href="?step=2&id_centro=<?php echo urlencode($centro['partita_iva']); ?>"
                                class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong
                                        class="d-block mb-1"><?php echo htmlspecialchars($centro['ragione_sociale']); ?></strong>
                                    <small><?php echo htmlspecialchars($centro['via'] . ' ' . $centro['civico'] . ', ' . $centro['cap']); ?></small>
                                </div>
                                <span class="badge bg-primary">Seleziona &rarr;</span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($centri) === 0): ?>
                            <p>Nessun centro medico disponibile al momento.</p>
                        <?php endif; ?>
                    </div>

                    <?php
                    // Step 2. Seleziono la specializzazione
                elseif ($step === 2 && $id_centro):
                    // Con questa query seleziono solamente le specializzazioni prenotabili
                    // che quindi corrispondono a quelle che hanno un medico associato (sempre
                    // per quel determinato centro medico)
                    $stmtAvail = $db->prepare("
                        SELECT DISTINCT s.id 
                        FROM Specializzazione s
                        JOIN Medico_Specializzazione ms ON s.id = ms.id_specializzazione
                        JOIN Medico_Centro mc ON ms.id_medico = mc.id_medico
                        WHERE mc.partita_iva_centro = :centro
                    ");
                    $stmtAvail->execute([':centro' => $id_centro]);
                    $available_ids = $stmtAvail->fetchAll(PDO::FETCH_COLUMN);

                    // Seleziono tutte le specializzazioni di questo centro medico
                    $stmtAll = $db->prepare("
                        SELECT id, nome, id_padre 
                        FROM Specializzazione 
                        WHERE partita_iva_centro = :centro 
                        ORDER BY nome ASC
                    ");
                    $stmtAll->execute([':centro' => $id_centro]);
                    $all_specs = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

                    $tree = [];
                    // Raggruppamento dei vari figli:
                    // Se una specializzazione non ha padre, quindi e' una principale, viene inserita
                    // nell'indice 0, invece se la specializzazione ha un padre, questa viene inserita
                    // nell'indice che corrisponde all'id del padre. Ad esempio Spec A con id_padre 3
                    // verra' inserita in $tree[3].
                    foreach ($all_specs as $spec) {
                        $padre = $spec['id_padre'] === null ? 0 : $spec['id_padre'];
                        $tree[$padre][] = $spec;
                    }

                    // A volte mi dava errore perche' le funzioni erano gia' create, quando ricaricavo, quindi ho messo
                    // il controllo, viene creata solo se non esiste gia'
                    if (!function_exists('hasAvailableDescendant')) {
                        // Questa funzione serve per capire se una categoria o una sua qualsiasi sotto categoria
                        // ha almeno un medico prenotabile, in caso contrario non viene neanche mostrata
                        function hasAvailableDescendant($node_id, $tree, $available_ids)
                        {
                            // Innanzi tutto controllo direttamnte il nodo che mi viene passato,
                            // se lo contengo nell'array dei disponibili allora mi fermo
                            if (in_array($node_id, $available_ids))
                                return true;
                            // Inizio a scorrere gli eventuali figli, richiamando questa funzione in 
                            // maniera ricorsiva, prima o poi arrivero alla fine e trovero o una visita
                            // con medico oppure mi fermero' perche' non ho alternative
                            if (isset($tree[$node_id])) {
                                foreach ($tree[$node_id] as $child) {
                                    // Chiamata ricorsiva al figlio
                                    if (hasAvailableDescendant($child['id'], $tree, $available_ids))
                                        return true;
                                }
                            }
                            return false;
                        }
                    }

                    if (!function_exists('renderSpecializationTree')) {
                        // Con la stessa logica della funzione precedente, solo che in questo punto strutturo la pagina
                        function renderSpecializationTree($parent_id, $tree, $available_ids, $id_centro, $is_root = true)
                        {
                            if (!isset($tree[$parent_id]))
                                return;

                            $ul_id = $is_root ? '' : 'id="collapse_spec_' . $parent_id . '"';
                            $ul_class = $is_root ? "list-unstyled" : "list-unstyled collapse ms-4 mt-2 border-start ps-3";

                            echo "<ul $ul_id class=\"$ul_class\">";

                            foreach ($tree[$parent_id] as $node) {
                                $is_available = in_array($node['id'], $available_ids);
                                $has_available_children = hasAvailableDescendant($node['id'], $tree, $available_ids);

                                if ($is_available || $has_available_children) {
                                    echo "<li class=\"mb-3\">";

                                    if ($has_available_children) {
                                        if ($is_available) {
                                            echo '<div class="d-flex align-items-center gap-2">';
                                            echo '<button class="btn btn-sm btn-light border" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_spec_' . $node['id'] . '" aria-expanded="false" aria-controls="collapse_spec_' . $node['id'] . '">';
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>';
                                            echo '</button>';

                                            echo '<a href="?step=3&id_centro=' . urlencode($id_centro) . '&id_spec=' . $node['id'] . '" class="flex-grow-1 text-decoration-none d-flex align-items-center p-2 border rounded">';
                                            echo '<span class="fw-bold">' . htmlspecialchars($node['nome']) . '</span>';
                                            echo '<span class="badge bg-primary ms-auto">Seleziona &rarr;</span>';
                                            echo '</a>';
                                            echo '</div>';
                                        } else {
                                            echo '<div class="d-flex align-items-center fw-bold p-2" data-bs-toggle="collapse" data-bs-target="#collapse_spec_' . $node['id'] . '" aria-expanded="false" aria-controls="collapse_spec_' . $node['id'] . '" style="cursor: pointer;">';
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-folder2-open me-2" viewBox="0 0 16 16"><path d="M1 3.5A1.5 1.5 0 0 1 2.5 2h2.764c.958 0 1.76.56 2.311 1.184C7.985 3.648 8.48 4 9 4h4.5A1.5 1.5 0 0 1 15 5.5v.64c.57.265.94.876.856 1.546l-.64 5.124A2.5 2.5 0 0 1 12.733 15H3.266a2.5 2.5 0 0 1-2.481-2.19l-.64-5.124A1.5 1.5 0 0 1 1 6.14V3.5zM2 6h12v-.5a.5.5 0 0 0-.5-.5H9c-.964 0-1.71-.629-2.174-1.154C6.374 3.334 5.82 3 5.264 3H2.5a.5.5 0 0 0-.5.5V6zm-.367 1a.5.5 0 0 0-.496.562l.64 5.124A1.5 1.5 0 0 0 3.266 14h9.468a1.5 1.5 0 0 0 1.489-1.314l.64-5.124A.5.5 0 0 0 14.367 7H1.633z"/></svg>';
                                            echo '<span class="flex-grow-1">' . htmlspecialchars($node['nome']) . '</span>';
                                            echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<a href="?step=3&id_centro=' . urlencode($id_centro) . '&id_spec=' . $node['id'] . '" class="text-decoration-none d-flex align-items-center p-2 border rounded">';
                                        echo '<span class="fw-bold">' . htmlspecialchars($node['nome']) . '</span>';
                                        echo '<span class="badge bg-primary ms-auto">Seleziona &rarr;</span>';
                                        echo '</a>';
                                    }

                                    if ($has_available_children) {
                                        renderSpecializationTree($node['id'], $tree, $available_ids, $id_centro, false);
                                    }

                                    echo "</li>";
                                }
                            }
                            echo "</ul>";
                        }
                    }
                    ?>
                    <h5 class="mb-4">Step 2: Scegli la Specializzazione</h5>

                    <div class="border p-3">
                        <?php
                        if (empty($available_ids)) {
                            echo '<p class="mb-0">Nessuna specializzazione disponibile in questo centro.</p>';
                        } else {
                            renderSpecializationTree(0, $tree, $available_ids, $id_centro);
                        }
                        ?>
                    </div>

                    <div class="mt-4"><a href="?step=1" class="btn btn-outline-secondary btn-sm">&larr; Torna indietro</a>
                    </div>

                    <?php
                    // Step 3. Seleziono il medico
                elseif ($step === 3 && $id_centro && $id_spec):
                    // Seleziono il medico per quella specializzazione, centro medico
                    $stmt = $db->prepare("
                        SELECT u.id, u.nome, u.cognome, ms.costo, ms.durata_minuti
                        FROM Utente u
                        JOIN Medico_Centro mc ON u.id = mc.id_medico
                        JOIN Medico_Specializzazione ms ON u.id = ms.id_medico
                        WHERE mc.partita_iva_centro = :centro AND ms.id_specializzazione = :spec
                    ");
                    $stmt->execute([':centro' => $id_centro, ':spec' => $id_spec]);
                    $medici = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <h5 class="mb-4">Step 3: Scegli il Medico</h5>
                    <div class="list-group">
                        <?php foreach ($medici as $medico): ?>
                            <a href="?step=4&id_centro=<?php echo urlencode($id_centro); ?>&id_spec=<?php echo $id_spec; ?>&id_medico=<?php echo $medico['id']; ?>"
                                class="list-group-item list-group-item-action py-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="d-block mb-1">Dott.
                                        <?php echo htmlspecialchars($medico['cognome'] . ' ' . $medico['nome']); ?></strong>
                                    <small>Costo: €<?php echo number_format($medico['costo'], 2); ?> •
                                        Durata: <?php echo $medico['durata_minuti']; ?> min</small>
                                </div>
                                <span class="badge bg-primary">Seleziona &rarr;</span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (count($medici) === 0): ?>
                            <p>Nessun medico disponibile per questa specializzazione nel centro selezionato.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4"><a href="?step=2&id_centro=<?php echo urlencode($id_centro); ?>"
                            class="btn btn-outline-secondary btn-sm">&larr; Torna indietro</a></div>

                    <?php
                    // Step 4. Seleziono da data
                elseif ($step === 4 && $id_centro && $id_spec && $id_medico):
                    // Seleziono i giorni della settimana per quel determinato medico
                    $stmtDays = $db->prepare("SELECT giorno_settimana FROM Agenda_Presenze WHERE id_medico = :medico ORDER BY giorno_settimana ASC");
                    $stmtDays->execute([':medico' => $id_medico]);
                    $working_days_nums = $stmtDays->fetchAll(PDO::FETCH_COLUMN);
                    // Filtro in base ai giorni di lavoro che sono stati settati
                    $working_days_names = array_map(function ($num) use ($giorni_settimana) {
                        return $giorni_settimana[$num];
                    }, $working_days_nums);
                    ?>
                    <h5 class="mb-4">Step 4: Scegli la Data</h5>

                    <?php if (empty($working_days_nums)): ?>
                        <div class="alert alert-warning">Questo medico non ha ancora impostato i suoi orari di lavoro.</div>
                    <?php else: ?>
                        <p class="small mb-3"><i class="bi bi-info-circle"></i> Il medico riceve nei seguenti giorni:
                            <strong><?php echo implode(', ', $working_days_names); ?></strong>
                        </p>

                        <form method="GET" action="">
                            <input type="hidden" name="step" value="5">
                            <input type="hidden" name="id_centro" value="<?php echo htmlspecialchars($id_centro); ?>">
                            <input type="hidden" name="id_spec" value="<?php echo htmlspecialchars($id_spec); ?>">
                            <input type="hidden" name="id_medico" value="<?php echo htmlspecialchars($id_medico); ?>">

                            <div class="mb-3">
                                <label class="form-label">Data della visita</label>
                                <input type="date" name="data" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Mostra Orari Disponibili</button>
                        </form>
                    <?php endif; ?>
                    <div class="mt-4"><a
                            href="?step=3&id_centro=<?php echo urlencode($id_centro); ?>&id_spec=<?php echo $id_spec; ?>"
                            class="btn btn-outline-secondary btn-sm">&larr; Torna indietro</a></div>

                    <?php
                    // Step 5. Seleziono l'ora
                elseif ($step === 5 && $id_centro && $id_spec && $id_medico && $data_visita):
                    $dayOfWeek = date('N', strtotime($data_visita));
                    // Seleziono ora inizio e ora fine
                    $stmtShift = $db->prepare("SELECT ora_inizio, ora_fine FROM Agenda_Presenze WHERE id_medico = :medico AND giorno_settimana = :giorno");
                    $stmtShift->execute([':medico' => $id_medico, ':giorno' => $dayOfWeek]);
                    $shift = $stmtShift->fetch(PDO::FETCH_ASSOC);
                    // Se non ci sono mostro errore
                    if (!$shift) {
                        echo '<div class="alert alert-warning">Il medico non lavora nel giorno selezionato (' . $giorni_settimana[$dayOfWeek] . ').</div>';
                        echo '<div class="mt-4"><a href="?step=4&id_centro=' . urlencode($id_centro) . '&id_spec=' . $id_spec . '&id_medico=' . $id_medico . '" class="btn btn-outline-secondary btn-sm">&larr; Scegli un\'altra data</a></div>';
                    } else {
                        // Seleziono la durata dei minuti
                        $stmtSpec = $db->prepare("SELECT durata_minuti, costo FROM Medico_Specializzazione WHERE id_medico = :medico AND id_specializzazione = :spec");
                        $stmtSpec->execute([':medico' => $id_medico, ':spec' => $id_spec]);
                        $spec = $stmtSpec->fetch(PDO::FETCH_ASSOC);
                        // Seleziono le visite gia' prenotate per escudere quegli slot di tempo
                        $stmtBooked = $db->prepare("SELECT ora_inizio FROM Visita WHERE id_medico = :medico AND data_visita = :data AND stato != 'disdetta'");
                        $stmtBooked->execute([':medico' => $id_medico, ':data' => $data_visita]);
                        $bookedSlots = $stmtBooked->fetchAll(PDO::FETCH_COLUMN);
                        // Calcolo la durata dei secondi
                        $durata_secondi = $spec['durata_minuti'] * 60;
                        $start = strtotime($data_visita . ' ' . $shift['ora_inizio']);
                        $end = strtotime($data_visita . ' ' . $shift['ora_fine']);
                        $slots = [];

                        // Scorro tutte le possibili fasce orarie
                        while ($start + $durata_secondi <= $end) {
                            // Ottendo la data da mostrare
                            $timeStrDB = date('H:i:s', $start);
                            $timeStrUI = date('H:i', $start);
                            // Se non e' gia' presente la aggiungo
                            if (!in_array($timeStrDB, $bookedSlots)) {
                                $slots[] = $timeStrUI;
                            }
                            $start += $durata_secondi;
                        }
                        ?>
                        <h5 class="mb-4">Step 5: Scegli l'Orario</h5>
                        <p class="mb-3">Disponibilità per il
                            <strong><?php echo date('d/m/Y', strtotime($data_visita)); ?></strong> (Costo visita:
                            €<?php echo number_format($spec['costo'], 2); ?>)
                        </p>

                        <?php if (empty($slots)): ?>
                            <div class="alert alert-warning">Nessun orario disponibile per questa data.</div>
                        <?php else: ?>
                            <form method="POST" action="?step=6">
                                <input type="hidden" name="id_centro" value="<?php echo htmlspecialchars($id_centro); ?>">
                                <input type="hidden" name="id_spec" value="<?php echo htmlspecialchars($id_spec); ?>">
                                <input type="hidden" name="id_medico" value="<?php echo htmlspecialchars($id_medico); ?>">
                                <input type="hidden" name="data" value="<?php echo htmlspecialchars($data_visita); ?>">
                                <input type="hidden" name="conferma_prenotazione" value="1">

                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <?php foreach ($slots as $orario): ?>
                                        <input type="radio" class="btn-check" name="ora_inizio" id="slot_<?php echo $orario; ?>"
                                            value="<?php echo $orario; ?>" required>
                                        <label class="btn btn-outline-primary"
                                            for="slot_<?php echo $orario; ?>"><?php echo $orario; ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    Conferma Prenotazione
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="mt-4"><a
                                href="?step=4&id_centro=<?php echo urlencode($id_centro); ?>&id_spec=<?php echo $id_spec; ?>&id_medico=<?php echo $id_medico; ?>"
                                class="btn btn-outline-secondary btn-sm">&larr; Cambia Data</a></div>
                        <?php
                    }
                    // Lo step 6 non ha un layout html perche' esegue la funzione riportata a inizio file
                    // Step 7. Se tutto va a buon fine mostro svg di successo
                elseif ($step === 7):
                    ?>
                    <div class="text-center py-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor"
                            class="bi bi-check-circle text-success mb-3" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                            <path
                                d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z" />
                        </svg>
                        <h4 class="text-success mb-2">Prenotazione Confermata!</h4>
                        <p>La tua visita è stata registrata con successo.</p>
                        <a href="/cliente/appuntamenti.php" class="btn btn-primary mt-3">Vai ai Miei
                            Appuntamenti</a>
                    </div>

                <?php else:
                    // Step non previsto torno al primo
                    echo '<script>window.location.href="?step=1";</script>';
                endif;
                ?>

            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>