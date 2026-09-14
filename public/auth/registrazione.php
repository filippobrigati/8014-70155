<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Controllers/AuthController.php';

$messaggioErrore = '';

$db = \Config\Database::getInstance()->getMySQL();
// Seleziono tutti i cap disponibili per l'autofill nel form
$stmtCap = $db->query("SELECT * FROM Citta_CAP ORDER BY comune ASC");
$listaCap = $stmtCap->fetchAll(PDO::FETCH_ASSOC);
// Se e' una post provo a fare il login tramite il controller per l'auth
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $ruolo = $_POST['ruolo'] ?? '';

    // Controllo campi obbligatori
    if ($email == '' || $password == '' || $ruolo == '') {
        $messaggioErrore = 'Campi utente obbligatori';
        return;
    }

    $datiAnagrafici = [
        'nome' => $_POST['nome'] ?? '',
        'cognome' => $_POST['cognome'] ?? '',
        'data_nascita' => $_POST['data_nascita'] ?? '',
        'sesso' => $_POST['sesso'] ?? '',
        'codice_fiscale' => $_POST['codice_fiscale'] ?? ''
    ];

    $controller = new \Controllers\AuthController();
    $risultato = null;

    // Devo eseguire funzioni diverse in base a che ruolo viene selezionato
    switch ($ruolo) {
        // In caso di admin devo registrare anche l'azienda e il collegamento
        // nella tabelle che fa da ponte tra utente e centro medico
        case 'admin':
            $partita_iva = $_POST['partita_iva'] ?? '';
            $ragione_sociale = $_POST['ragione_sociale'] ?? '';
            $via = $_POST['via'] ?? '';
            $civico = $_POST['civico'] ?? '';
            $cap = $_POST['cap'] ?? '';
            $comune = $_POST['comune'] ?? '';
            $provincia = $_POST['provincia'] ?? '';
            $regione = $_POST['regione'] ?? '';

            if ($partita_iva == '' || $ragione_sociale == '') {
                $messaggioErrore = 'PIVA e Ragione Sociale sono obbligatori';
                break;
            }

            $risultato = $controller->registrazioneAdminConAzienda(
                $email,
                $password,
                $datiAnagrafici,
                $partita_iva,
                $ragione_sociale,
                $via,
                $civico,
                $cap,
                $comune,
                $provincia,
                $regione
            );
            break;
        // Se e' un cliente mi basta una semplice insert
        // sempre tramite authController
        case 'cliente':
            $risultato = $controller->registrazioneUtente(
                $email,
                $password,
                $ruolo,
                $datiAnagrafici
            );
            break;
        default:
            $messaggioErrore = 'Tipologia utente selezionata non valida';
            break;
    }

    if (is_string($risultato)) {
        $messaggioErrore = $risultato;
    }
}

$pageTitle = "Registrazione - Accesso Piattaforma";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex justify-content-center align-items-center min-vh-100">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Registrati</h3>

                <?php if ($messaggioErrore): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($messaggioErrore); ?></div>
                <?php endif; ?>

                <form action="/auth/registrazione.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cognome" class="form-label">Cognome</label>
                            <input type="text" class="form-control" id="cognome" name="cognome" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Indirizzo Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="sessoSelect" class="form-label">Sesso</label>
                            <select class="form-select" id="sessoSelect" name="sesso" required>
                                <option value="" selected disabled>ND</option>
                                <option value="M">Uomo</option>
                                <option value="F">Donna</option>
                                <option value="ND">Altro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="data_nascita" class="form-label">Nascita</label>
                            <input type="date" class="form-control" id="data_nascita" name="data_nascita" required>
                        </div>

                        <div class="col-md-6">
                            <label for="codice_fiscale" class="form-label">Codice Fiscale</label>
                            <input type="text" class="form-control" id="codice_fiscale" name="codice_fiscale"
                                maxlength="16" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ruoloSelect" class="form-label">Tipo di Utente</label>
                        <select class="form-select" id="ruoloSelect" name="ruolo" required>
                            <option value="" selected disabled>Seleziona il tipo di utente</option>
                            <option value="cliente">Cliente</option>
                            <option value="admin">Admin (Centro Medico)</option>
                        </select>
                    </div>

                    <div id="campiAzienda" class="border p-3 mb-3 bg-light d-none">
                        <h6 class="mb-3"><b>Dati Centro Medico</b></h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="partita_iva" class="form-label">Partita IVA</label>
                                <input type="text" class="form-control" id="partita_iva" name="partita_iva"
                                    maxlength="11">
                            </div>
                            <div class="col-md-6">
                                <label for="ragione_sociale" class="form-label">Ragione Sociale</label>
                                <input type="text" class="form-control" id="ragione_sociale" name="ragione_sociale">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="cap" class="form-label">CAP</label>
                                <select class="form-select" id="cap" name="cap">
                                    <option value="" selected disabled>Seleziona CAP</option>
                                    <?php foreach ($listaCap as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['cap']); ?>"
                                            data-comune="<?php echo htmlspecialchars($c['comune']); ?>"
                                            data-provincia="<?php echo htmlspecialchars($c['provincia']); ?>"
                                            data-regione="<?php echo htmlspecialchars($c['regione']); ?>">
                                            <?php echo htmlspecialchars($c['cap'] . ' - ' . $c['comune']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="regione" class="form-label">Regione</label>
                                <input type="text" class="form-control" id="regione" name="regione">
                            </div>
                            <div class="col-md-2">
                                <label for="provincia" class="form-label">Prov.</label>
                                <input type="text" class="form-control" id="provincia" name="provincia">
                            </div>
                            <div class="col-md-3">
                                <label for="comune" class="form-label">Comune</label>
                                <input type="text" class="form-control" id="comune" name="comune">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-9">
                                <label for="via" class="form-label">Via</label>
                                <input type="text" class="form-control" id="via" name="via">
                            </div>
                            <div class="col-md-3">
                                <label for="civico" class="form-label">Civico</label>
                                <input type="text" class="form-control" id="civico" name="civico">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Registrati</button>
                    <div class="text-center mt-3">
                        <p class="mb-0">Hai un account? <a href="/auth/login.php">Esegui l'accesso</a></p>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('ruoloSelect').addEventListener('change', function () {
        var campiAzienda = document.getElementById('campiAzienda');
        if (this.value === 'admin') {
            campiAzienda.classList.remove('d-none');
        } else {
            campiAzienda.classList.add('d-none');
        }
    });

    document.getElementById('cap').addEventListener('change', function () {
        // Se seleziono un cap presente completo i campi in automatico
        let selectedOption = this.options[this.selectedIndex];
        let regioneInput = document.getElementById('regione');
        let provinciaInput = document.getElementById('provincia');
        let comuneInput = document.getElementById('comune');

        if (selectedOption.value) {
            // Qua li imposto readonly cosi' nn possono essere modificati e "rompere" il db
            // altrimenti ci sarebbero incongruenze tra i dati gia presenti e quelli modificati
            regioneInput.value = selectedOption.getAttribute('data-regione');
            provinciaInput.value = selectedOption.getAttribute('data-provincia');
            comuneInput.value = selectedOption.getAttribute('data-comune');

            regioneInput.readOnly = true;
            provinciaInput.readOnly = true;
            comuneInput.readOnly = true;

            regioneInput.classList.add('bg-white');
            provinciaInput.classList.add('bg-white');
            comuneInput.classList.add('bg-white');
        } else {
            // Altrimenti li resetto a come erano primaa
            regioneInput.value = '';
            provinciaInput.value = '';
            comuneInput.value = '';

            regioneInput.readOnly = false;
            provinciaInput.readOnly = false;
            comuneInput.readOnly = false;

            regioneInput.classList.remove('bg-white');
            provinciaInput.classList.remove('bg-white');
            comuneInput.classList.remove('bg-white');
        }
    });
</script>

<?php require __DIR__ . '/../_layout/footer.php'; ?>