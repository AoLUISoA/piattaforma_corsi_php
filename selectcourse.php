<?php
// Avviamo la sessione per recuperare l'ID dell'utente appena registrato
session_start();

// Controllo di sicurezza: se l'utente non si è registrato/loggato, lo rimandiamo al login
if (!isset($_SESSION['utente_id'])) {
    header("Location: login.php");
    exit();
}

// PARAMETRI DI CONNESSIONE AL TUO DATABASE IN LOCALE SU XAMPP
$host = '127.0.0.1';
$dbname = 'learnify_db';
$db_user = 'root';
$db_pass = '';

try {
    // Connessione sicura tramite PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

$messaggio = "";
$tipo_messaggio = "";

// LOGICA: Quando l'utente preme il pulsante per confermare la scelta dei corsi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studente_id = $_SESSION['utente_id'];
    
    // Verifichiamo se l'utente ha effettivamente selezionato almeno una casella
    if (isset($_POST['corsi_selezionati']) && !empty($_POST['corsi_selezionati'])) {
        $corsi_scelti = $_POST['corsi_selezionati']; // Array contenente gli ID dei corsi selezionati

        try {
            /* 
               CORREZIONE CHIAVE: Cambiato 'utente_id' in 'studente_id' 
               per combaciare perfettamente con la nuova tabella 'iscrizioni_corsi'
            */
            $stmt = $pdo->prepare("INSERT IGNORE INTO iscrizioni_corsi (studente_id, corso_id, progresso) VALUES (:studente_id, :corso_id, 0)");
            
            foreach ($corsi_scelti as $corso_id) {
                $stmt->execute([
                    ':studente_id' => $studente_id,
                    ':corso_id' => intval($corso_id)
                ]);
            }
            
            // CORREZIONE REINDIRIZZAMENTO: Reindirizziamo l'utente alla pagina esatta del layout studente
            header("Location: student_dashboard.php");
            exit();
        } catch (PDOException $e) {
            $messaggio = "Si è verificato un errore durante l'iscrizione nel database.";
            $tipo_messaggio = "error";
        }
    } else {
        $messaggio = "Per favore, seleziona almeno un corso prima di confermare.";
        $tipo_messaggio = "warning";
    }
}

// Recuperiamo la lista dei corsi effettivamente presenti sul database da mostrare all'utente
try {
    $query_corsi = $pdo->query("SELECT * FROM corsi ORDER BY titolo ASC");
    $elenco_corsi = $query_corsi->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Errore nel recupero dei corsi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scegli i tuoi Corsi - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="selectstyle.css">
</head>
<body class="selection-body">

<div class="selection-container">
    <div class="selection-header">
        <h2>Registrazione completata! 🎉</h2>
        <p>Personalizza la tua esperienza su <strong>Learnify</strong>. Seleziona i percorsi di studio che desideri frequentare fin da subito.</p>
    </div>

    <!-- Alert per eventuali messaggi di errore o avviso -->
    <?php if (!empty($messaggio)): ?>
        <div class="alert alert-<?php echo $tipo_messaggio; ?>">
            <?php echo htmlspecialchars($messaggio); ?>
        </div>
    <?php endif; ?>

    <form action="select-course.php" method="POST">
        <div class="courses-list-grid">
            <?php if (count($elenco_corsi) > 0): ?>
                <?php foreach ($elenco_corsi as $corso): ?>
                    <!-- Card interattiva con checkbox nascosta -->
                    <label class="course-selectable-option">
                        <input type="checkbox" name="corsi_selezionati[]" value="<?php echo $corso['id']; ?>">
                        <div class="course-selection-card">
                            <div class="card-top-info">
                                <span class="badge-tag"><?php echo htmlspecialchars($corso['categoria']); ?></span>
                                <span class="level-indicator"><?php echo htmlspecialchars($corso['livello']); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                            <p><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                            <div class="card-bottom-info">
                                <span>⏱️ <?php echo htmlspecialchars($corso['durata']); ?> ore di lezione</span>
                                <div class="checkbox-visual-status"></div>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-db-state">
                    <p>Al momento non ci sono corsi caricati nel database della piattaforma.</p>
                </div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit-selection">
            Conferma Scelte e Vai alla Dashboard 🚀
        </button>
    </form>
</div>

</body>
</html>
