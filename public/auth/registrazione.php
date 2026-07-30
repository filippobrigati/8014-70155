<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Controllers/AuthController.php';

$messaggioErrore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $ruolo = $_POST['ruolo'] ?? '';

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

    switch ($ruolo) {
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
        case 'utente':
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

// 3. Stampa dell'Interfaccia Grafica
$pageTitle = "Registrazione - Accesso Piattaforma";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex justify-content-center align-items-center" style="min-height: 100vh">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="card-title text-center mb-4">Registrati</h3>

                <?php if ($messaggioErrore): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($messaggioErrore); ?></div>
                <?php endif; ?>

                <form action="/auth/registrazione.php" method="POST">
                    <div class="row mb-2">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cognome" class="form-label">Cognome</label>
                            <input type="text" class="form-control" id="cognome" name="cognome" required>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="email" class="form-label">Indirizzo Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-md-2 mb-3 mb-md-0">
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
                            <input type="text" class="form-control" id="codice_fiscale" name="codice_fiscale" required>
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

                    <div id="campiAzienda" class="border p-3 rounded mb-3 bg-light d-none">
                        <h6 class="text-muted mb-3"><b>Dati Centro Medico</b></h6>
                        <div class="row mb-2">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="partita_iva" class="form-label">Partita IVA</label>
                                <input type="text" class="form-control" id="partita_iva" name="partita_iva"
                                    maxlength="11">
                            </div>
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="ragione_sociale" class="form-label">Ragione Sociale</label>
                                <input type="text" class="form-control" id="ragione_sociale" name="ragione_sociale">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="cap" class="form-label">CAP</label>
                                <input type="number" class="form-control" id="cap" name="cap" maxlength="5">
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label for="regione" class="form-label">Regione</label>
                                <input type="text" class="form-control" id="regione" name="regione">
                            </div>
                            <div class="col-md-2 mb-3 mb-md-0">
                                <label for="provincia" class="form-label">Prov.</label>
                                <input type="text" class="form-control" id="provincia" name="provincia">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="comune" class="form-label">Comune</label>
                                <input type="text" class="form-control" id="comune" name="comune">
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-9 mb-3 mb-md-0">
                                <label for="via" class="form-label">Via</label>
                                <input type="text" class="form-control" id="via" name="via">
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0">
                                <label for="civico" class="form-label">Civico</label>
                                <input type="text" class="form-control" id="civico" name="civico">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Registrati</button>
                    <div class="d-flex justify-content-center align-items-center mt-3">
                        <p class="mb-0">Hai un account? <a class="link-opacity-75-hover" href="/auth/login.php">Esegui
                                l'accesso</a></p>
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

    document.getElementById('cap').addEventListener('input', function () {
        let capValue = this.value;
        let regioneInput = document.getElementById('regione');
        let provinciaInput = document.getElementById('provincia');
        let comuneInput = document.getElementById('comune');

        if (capValue.length === 5) {
            fetch(`/api/get_cap.php?cap=${capValue}`)
                .then(response => response.json())
                .then(data => {
                    if (data.found) {
                        regioneInput.value = data.data.regione;
                        provinciaInput.value = data.data.provincia;
                        comuneInput.value = data.data.comune;

                        regioneInput.readOnly = true;
                        provinciaInput.readOnly = true;
                        comuneInput.readOnly = true;

                        regioneInput.classList.add('bg-white');
                        provinciaInput.classList.add('bg-white');
                        comuneInput.classList.add('bg-white');
                    } else {
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
                })
                .catch(error => console.error('Error fetching CAP:', error));
        } else {
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