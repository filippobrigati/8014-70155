<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

$id_admin = $utenteLoggato['id'];
$db = \config\Database::getInstance()->getMySQL();

// Recupero la piva del centro medico come in tutte le pagine della sezione admin
$stmtCentro = $db->prepare("
    SELECT c.partita_iva 
    FROM Centro_Medico c
    JOIN Admin_Centro ac ON c.partita_iva = ac.partita_iva_centro
    WHERE ac.id_admin = :id_admin
");
$stmtCentro->execute([':id_admin' => $id_admin]);
$centro = $stmtCentro->fetch(PDO::FETCH_ASSOC);

if (!$centro) {
    die("Non hai un centro medico associato. Non puoi visualizzare le specializzazioni.");
}
$partita_iva_centro = $centro['partita_iva'];

// Recupero la paginazione come nella sezione dei medici, se < 1 setto a 1 (o anche se non presente)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$limit = 10;
$offset = ($page - 1) * $limit;

// Conto il totale di specializzazioni filtrando sempre per partita iva
$countQuery = "SELECT COUNT(*) FROM Specializzazione WHERE partita_iva_centro = :piva";
$stmtCount = $db->prepare($countQuery);
$stmtCount->bindValue(':piva', $partita_iva_centro, PDO::PARAM_STR);
$stmtCount->execute();
$total = $stmtCount->fetchColumn();
$totalPages = ceil($total / $limit);

// Tramite questa query recupero tutte le specializzazioni.
// Utilizzo una self join e imposto come "valore comune" il nome del padre
// in questo modo posso rappresentare graficamente nella pagina di html
// i vari valori raggruppati
$query = "
    SELECT s1.id, s1.nome, s2.nome AS nome_padre 
    FROM Specializzazione s1
    LEFT JOIN Specializzazione s2 ON s1.id_padre = s2.id
    WHERE s1.partita_iva_centro = :piva
    ORDER BY s1.nome ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $db->prepare($query);
$stmt->bindValue(':piva', $partita_iva_centro, PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$specializzazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Gestione Specializzazioni - Admin";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Gestione Specializzazioni</h3>
                <p>Visualizza l'elenco di tutte le specializzazioni mediche del sistema</p>
            </div>
            <a href="/admin/crea_specializzazione.php" class="btn btn-primary">
                Nuova Specializzazione
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Nome Specializzazione</th>
                                <th scope="col">Categoria Padre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($specializzazioni) > 0): ?>
                                <!-- scorro tutte le specializzazioni -->
                                <?php foreach ($specializzazioni as $spec): ?>
                                    <tr>
                                        <td>
                                            #<?php echo htmlspecialchars($spec['id']); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($spec['nome']); ?></strong>
                                        </td>
                                        <td>
                                            <!-- se e' una sotto-categoria lo faccio vedere mostrando il nome del padre -->
                                            <?php if ($spec['nome_padre']): ?>
                                                <span class="badge bg-info text-dark">
                                                    Sottocategoria di: <?php echo htmlspecialchars($spec['nome_padre']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span>Nessuna</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Nessuna specializzazione trovata.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- gestione della paginazione solamente se ci sono piu pagine -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <!-- disabilitato per evitare valori negativi -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <!-- chiamata alla stessa pagina con numero di pagina aggiornato, in negativo ma vale allo stesso modo per il positivo -->
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