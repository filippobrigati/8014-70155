<?php
// Recupero il percorso del server
$currentPath = $_SERVER['PHP_SELF'];

// Questa parte serve per cambiare lo stile degli elementi nella lista
function isActive($path)
{
    // Accedo alla variabile globale $currentPath e la confronto con il path passato alla funzione
    global $currentPath;
    return $currentPath === $path ? 'active' : 'link-dark';
}
?>

<div class="d-flex flex-column p-3 bg-light border-end" style="width: 280px;">
    <a href="/" class="d-flex align-items-center mb-3 text-dark text-decoration-none">
        <span class="fs-4">Centro Medico</span>
    </a>
    <hr>
    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-item">
            <a href="/index.php" class="nav-link <?php echo isActive('/index.php'); ?>" <?php echo ($currentPath === '/index.php') ? 'aria-current="page"' : ''; ?>>
                Home
            </a>
        </li>

        <?php if (isset($utenteLoggato['ruolo']) && $utenteLoggato['ruolo'] === 'admin'): ?>
            <li class="nav-item">
                <a href="/admin/gestione_medici.php" class="nav-link <?php echo isActive('/admin/gestione_medici.php'); ?>">
                    Gestione Medici
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/gestione_specializzazioni.php"
                    class="nav-link <?php echo isActive('/admin/gestione_specializzazioni.php'); ?>">
                    Gestione Specializzazioni
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/orari_medici.php" class="nav-link <?php echo isActive('/admin/orari_medici.php'); ?>">
                    Visualizzazione orari
                </a>
            </li>

        <?php elseif (isset($utenteLoggato['ruolo']) && $utenteLoggato['ruolo'] === 'medico'): ?>
            <li class="nav-item">
                <a href="/medico/orari.php" class="nav-link <?php echo isActive('/medico/orari.php'); ?>">
                    I miei orari
                </a>
            </li>
            <li class="nav-item">
                <a href="/medico/prossimi_appuntamenti.php"
                    class="nav-link <?php echo isActive('/medico/prossimi_appuntamenti.php'); ?>">
                    Prossimi Appuntamenti
                </a>
            </li>
        <?php elseif (isset($utenteLoggato['ruolo']) && $utenteLoggato['ruolo'] === 'cliente'): ?>
            <li class="nav-item">
                <a href="/cliente/prenota.php" class="nav-link <?php echo isActive('/cliente/prenota.php'); ?>">
                    Prenota Visita
                </a>
            </li>
            <li class="nav-item">
                <a href="/cliente/appuntamenti.php" class="nav-link <?php echo isActive('/cliente/appuntamenti.php'); ?>">
                    I Miei Appuntamenti
                </a>
            </li>
            <li class="nav-item">
                <a href="/cliente/storico.php" class="nav-link <?php echo isActive('/cliente/storico.php'); ?>">
                    Storico appuntamenti
                </a>
            </li>
            <li class="nav-item">
                <a href="/cliente/fatture.php" class="nav-link <?php echo isActive('/cliente/fatture.php'); ?>">
                    Storico fatture
                </a>
            </li>
            <li class="nav-item">
                <a href="/cliente/recensioni.php" class="nav-link <?php echo isActive('/cliente/recensioni.php'); ?>">
                    Recensioni
                </a>
            </li>
        <?php endif; ?>
    </ul>
    <hr>
    <div class="dropdown">
        <strong>
            <?php echo htmlspecialchars($utenteLoggato['nome'] . ' ' . $utenteLoggato['cognome']); ?>
        </strong>
    </div>
</div>