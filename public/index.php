<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Utils/check_auth.php';

$pageTitle = "Dashboard - Centro Medico";
require __DIR__ . '/_layout/header.php';
?>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="alert alert-success">
            <h1>Benvenuto, <?php echo htmlspecialchars($utenteLoggato['nome'] . ' ' . $utenteLoggato['cognome']); ?>!
            </h1>
            <p class="mb-0">Ruolo nel sistema: <strong><?php echo htmlspecialchars($utenteLoggato['ruolo']); ?></strong>
            </p>
        </div>
        <a href="logout.php" class="btn btn-danger">Esci (Logout)</a>
    </div>
</div>

<?php require __DIR__ . '/_layout/footer.php'; ?>