<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/Utils/check_auth.php';
require_once __DIR__ . '/../src/Config/Database.php';

$pageTitle = "Dashboard - Centro Medico";
require __DIR__ . '/_layout/header.php';

$db = \config\Database::getInstance()->getMySQL();
$ruolo = $utenteLoggato['ruolo'];
$id_utente = $utenteLoggato['id'];
$datiDashboard = [];

// Recupero i dati dalla vista corrispondente in base al ruolo
try {
    if ($ruolo === 'cliente') {
        $stmt = $db->prepare("SELECT * FROM vista_dashboard_cliente WHERE id_cliente = :id");
        $stmt->execute([':id' => $id_utente]);
        $datiDashboard = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($ruolo === 'medico') {
        $stmt = $db->prepare("SELECT * FROM vista_dashboard_medico WHERE id_medico = :id");
        $stmt->execute([':id' => $id_utente]);
        $datiDashboard = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($ruolo === 'admin') {
        $stmt = $db->prepare("SELECT * FROM vista_dashboard_admin WHERE id_admin = :id");
        $stmt->execute([':id' => $id_utente]);
        $datiDashboard = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Gestione errore DB
    $datiDashboard = false;
}
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">

        <!-- Header Dashboard -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h2 class="mb-1">
                    Ciao, <?php echo htmlspecialchars($utenteLoggato['nome']); ?>!
                </h2>
                <p class="mb-0">Ecco il riepilogo della tua area
                    <span class="badge bg-primary text-uppercase"><?php echo htmlspecialchars($ruolo); ?></span>
                </p>
            </div>
            <a href="logout.php" class="btn btn-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Esci
            </a>
        </div>

        <?php if ($datiDashboard === false): ?>
            <div class="alert alert-warning">
                Impossibile caricare i dati della dashboard. Se sei un admin, assicurati di aver creato almeno un Centro
                Medico.
            </div>
        <?php else: ?>

            <?php if ($ruolo === 'cliente'): ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="text-primary me-3">
                                    <i class="bi bi-calendar-event fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Appuntamenti Attivi</h6>
                                    <h2 class="mb-0"><?php echo $datiDashboard['visite_attive'] ?? 0; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="text-success me-3">
                                    <i class="bi bi-check2-circle fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Visite Completate</h6>
                                    <h2 class="mb-0"><?php echo $datiDashboard['visite_completate'] ?? 0; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="/cliente/prenota.php" class="btn btn-primary">Prenota nuova visita</a>
                    <a href="/cliente/appuntamenti.php" class="btn btn-secondary">Vai in Agenda</a>
                </div>

            <?php elseif ($ruolo === 'medico'): ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="text-warning me-3">
                                    <i class="bi bi-calendar-day fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Visite di Oggi</h6>
                                    <h2 class="mb-0"><?php echo $datiDashboard['visite_oggi'] ?? 0; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="text-info me-3">
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Pazienti Unici</h6>
                                    <h2 class="mb-0"><?php echo $datiDashboard['pazienti_unici'] ?? 0; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center">
                                <div class="text-warning me-3">
                                    <i class="bi bi-star-fill fs-1"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Valutazione</h6>
                                    <h2 class="mb-0">
                                        <?php echo $datiDashboard['rating_medio'] > 0 ? $datiDashboard['rating_medio'] . ' / 5' : 'N/D'; ?>
                                    </h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="/medico/prossimi_appuntamenti.php" class="btn btn-primary">Appuntamenti e Referti</a>
                    <a href="/medico/orari.php" class="btn btn-secondary">Gestisci orari di
                        lavoro</a>
                </div>

            <?php elseif ($ruolo === 'admin'): ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3">Medici in Struttura</h6>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="mb-0"><?php echo $datiDashboard['totale_medici'] ?? 0; ?></h2>
                                    <i class="bi bi-hospital fs-2 text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3">Visite questo mese</h6>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="mb-0"><?php echo $datiDashboard['visite_mese'] ?? 0; ?></h2>
                                    <i class="bi bi-bar-chart-line fs-2 text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-3">Valore Visite Generato</h6>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h2 class="mb-0">
                                        €<?php echo number_format($datiDashboard['fatturato_totale'] ?? 0, 0, ',', '.'); ?></h2>
                                    <i class="bi bi-currency-euro fs-2 text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/_layout/footer.php'; ?>