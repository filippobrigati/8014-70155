<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/Utils/check_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';

// Recupero come nelle altre pagine l'istanza di mysql e l'id dell'admin loggato (per recuperare il centro medico)
$db = \config\Database::getInstance()->getMySQL();
$id_admin = $utenteLoggato['id'];

// recupero il centro medico (solo piva) tramite la tabella admin -> centro
$stmtCentro = $db->prepare("
    SELECT c.partita_iva 
    FROM Centro_Medico c
    JOIN Admin_Centro ac ON c.partita_iva = ac.partita_iva_centro
    WHERE ac.id_admin = :id_admin
");
$stmtCentro->execute([':id_admin' => $id_admin]);
$centro = $stmtCentro->fetch(PDO::FETCH_ASSOC);
// Mi fermo se non trovo il centro
if (!$centro) {
    die("Non hai un centro medico associato. Non puoi creare specializzazioni.");
}
$partita_iva_centro = $centro['partita_iva'];

$messaggio = '';
$errore = '';

// Eseguito solo dopo aver inviato il form tramite html
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero info
    $nome = trim($_POST['nome'] ?? '');
    $id_padre = $_POST['id_padre'] ?? '';

    // Controllo se e' una stringa vuota, questo serve perche' violerebbe i vincoli nel db
    if (empty($id_padre)) {
        $id_padre = null;
    }

    // Controllo sul nome
    if (empty($nome)) {
        $errore = "Il nome della specializzazione è obbligatorio.";
    } else {
        try {
            // Aggiungo la partita iva alla query di inserimento, in questo modo
            // la specializzazione viene inserita solamente per quel centro medico
            $stmtInsert = $db->prepare("
                INSERT INTO Specializzazione (nome, id_padre, partita_iva_centro) 
                VALUES (:nome, :id_padre, :piva)
            ");
            $stmtInsert->execute([
                ':nome' => $nome,
                ':id_padre' => $id_padre,
                ':piva' => $partita_iva_centro
            ]);
            $messaggio = "Specializzazione '$nome' creata con successo!";
        } catch (\PDOException $e) {
            $errore = "Errore durante la creazione: " . $e->getMessage();
        }
    }
}

// Seleziono le specializzazioni in base alla piva del centro
$stmt = $db->prepare("
    SELECT id, nome 
    FROM Specializzazione 
    WHERE partita_iva_centro = :piva 
    ORDER BY nome ASC
");
$stmt->execute([':piva' => $partita_iva_centro]);
$specializzazioni = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Crea Specializzazione - Admin";
require __DIR__ . '/../_layout/header.php';
?>

<div class="d-flex min-vh-100">
    <?php require __DIR__ . '/../_layout/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3>Nuova Specializzazione</h3>
                <p>Aggiungi una nuova specializzazione medica all'elenco di sistema</p>
            </div>
            <a href="/admin/gestione_specializzazioni.php" class="btn btn-secondary">
                Torna alla lista
            </a>
        </div>
        <!-- Faccio vedere un eventuale messaggio di errore, tipo inserimento fallito -->
        <?php if ($messaggio): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($messaggio); ?></div>
        <?php endif; ?>
        <?php if ($errore): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errore); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <form action="/admin/crea_specializzazione.php" method="POST">

                            <h5 class="mb-4">Dettagli Specializzazione</h5>

                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Specializzazione</label>
                                <input type="text" class="form-control" id="nome" name="nome"
                                    placeholder="Es. Cardiologia Pediatrica" required>
                            </div>

                            <div class="mb-4">
                                <label for="id_padre" class="form-label">Sottocategoria di (Opzionale)</label>
                                <select class="form-select" id="id_padre" name="id_padre">
                                    <option value="" selected>Nessuna (Categoria Principale)</option>
                                    <!-- Mostro tutte le specializzazioni selezionate in precedenza -->
                                    <?php foreach ($specializzazioni as $spec): ?>
                                        <option value="<?php echo htmlspecialchars($spec['id']); ?>">
                                            <!-- Aggiungo sempre htmlspecialchars per evitare errori di formattazione e sicurezza -->
                                            <?php echo htmlspecialchars($spec['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Seleziona una specializzazione padre se questa è una sua branca (es. seleziona
                                    Cardiologia se stai creando Cardiologia Pediatrica).
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    Salva
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../_layout/footer.php'; ?>