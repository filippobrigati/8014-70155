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

    $controller = new \Controllers\AuthController();
    $risultato = $controller->login($email, $password);

    if (is_string($risultato)) {
        $messaggioErrore = $risultato;
    }
}

$pageTitle = "Login - Accesso Piattaforma";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex justify-content-center align-items-center" style="min-height: 100vh">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Accedi</h3>

                <form action="/auth/login.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Indirizzo Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <?php if ($messaggioErrore): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($messaggioErrore); ?>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100">Entra</button>
                    <div class="d-flex justify-content-center align-items-center mt-3">
                        <p>Non hai un account? <a class="link-opacity-75-hover"
                                href="/auth/registrazione.php">Registrati</a></p>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>