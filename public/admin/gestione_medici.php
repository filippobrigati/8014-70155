<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

$pageTitle = "Gestione Medici - Admin";
require __DIR__ . '/../_layout/header.php';

// LEggo il numero di pagina (serve per impostare la paginazione dei dati)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
// Se non c'e' o e' minore di 1 allora lo setto a 1
if ($page < 1)
    $page = 1;
// Calcolo limite e offset per la select
$limit = 10;
$offset = ($page - 1) * $limit;

$db = \config\Database::getInstance()->getMySQL();
$id_admin = $utenteLoggato['id'];

// Recupero sempre il centro medico
$stmtCentro = $db->prepare("
    SELECT c.partita_iva 
    FROM Centro_Medico c
    JOIN Admin_Centro ac ON c.partita_iva = ac.partita_iva_centro
    WHERE ac.id_admin = :id_admin
");
$stmtCentro->execute([':id_admin' => $id_admin]);
$centro = $stmtCentro->fetch(PDO::FETCH_ASSOC);

// Variabili per costruire la pagina html
$medici = [];
$total = 0;
$totalPages = 0;

if ($centro) {
    $partita_iva_centro = $centro['partita_iva'];

    // Seleziono il numero di di utenti tramite la tabella ponte Medico_Centro
    $countQuery = "SELECT COUNT(*) FROM Utente u JOIN Medico_Centro mc ON u.id = mc.id_medico WHERE mc.partita_iva_centro = :piva";
    $stmtCount = $db->prepare($countQuery);
    $stmtCount->execute([':piva' => $partita_iva_centro]);
    $total = $stmtCount->fetchColumn();
    $totalPages = ceil($total / $limit);
    // Seleziono i dati effettivi dei medici, paginando per limit (numero di righe) e offset (punto da cui parto)
    $query = "
        SELECT u.* 
        FROM Utente u
        JOIN Medico_Centro mc ON u.id = mc.id_medico
        WHERE mc.partita_iva_centro = :piva
        ORDER BY u.cognome ASC, u.nome ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':piva', $partita_iva_centro, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    // Restituisco tutto trasformando in un array di righe chiave valore
    $medici = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="d-flex" style="min-height: 100vh;">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-2">Gestione medici</h3>
                <p class="text-muted mb-0">Ecco i medici censiti per il tuo centro</p>
            </div>
            <a href="/admin/crea_medico.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-plus-lg me-2" viewBox="0 0 16 16">
                    <path fill-rule="evenodd"
                        d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z" />
                </svg>
                Nuovo Medico
            </a>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" class="ps-4">Nome e Cognome</th>
                                <th scope="col">Codice Fiscale</th>
                                <th scope="col">Email</th>
                                <th scope="col">Telefono</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$centro): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Nessun centro medico associato a
                                        questo admin.</td>
                                </tr>
                            <?php elseif (count($medici) > 0): ?>
                                <?php foreach ($medici as $medico): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <strong><?php echo htmlspecialchars($medico['cognome'] . ' ' . $medico['nome']); ?></strong>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-secondary rounded-pill"><?php echo htmlspecialchars($medico['codice_fiscale']); ?></span>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($medico['email']); ?>"
                                                class="text-decoration-none">
                                                <?php echo htmlspecialchars($medico['email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($medico['telefono'] ?? 'N/D'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Nessun medico trovato per questo
                                        centro.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white border-0 py-3">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
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

<?php require __DIR__ . '/../_layout/footer.php'; ?>