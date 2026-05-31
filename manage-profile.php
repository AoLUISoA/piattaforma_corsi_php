<?php
session_start();

// Controllo di sicurezza: accesso consentito solo ai docenti loggati
if (!isset($_SESSION['utente_id']) || $_SESSION['ruolo'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// PARAMETRI DI CONNESSIONE AL DATABASE SU XAMPP
$host = '127.0.0.1';
$dbname = 'learnify_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

$docente_id = $_SESSION['utente_id'];
$messaggio = "";
$tipo_messaggio = "";

// --- AZIONE 1: AGGIORNAMENTO DATI PROFILO DOCENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $specializzazione = trim($_POST['specializzazione']);
    $biografia = trim($_POST['biografia']);
    $sito_web = trim($_POST['sito_web']);

    if (!empty($specializzazione)) {
        try {
            $stmt_prof = $pdo->prepare("UPDATE docenti SET specializzazione = :spec, biografia = :bio, sito_web = :sito WHERE id = :id");
            $stmt_prof->execute([
                ':spec' => $specializzazione,
                ':bio' => $biografia,
                ':sito' => !empty($sito_web) ? $sito_web : NULL,
                ':id' => $docente_id
            ]);
            $messaggio = "Profilo professionale aggiornato con successo! 💼";
            $tipo_messaggio = "success";
        } catch (PDOException $e) {
            $messaggio = "Errore durante l'aggiornamento del profilo.";
            $tipo_messaggio = "error";
        }
    } else {
        $messaggio = "Il campo Specializzazione è obbligatorio.";
        $tipo_messaggio = "warning";
    }
}

// --- AZIONE 2: PUBBLICAZIONE NUOVO ANNUNCIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_announcement') {
    $corso_id = intval($_POST['corso_id_annuncio']);
    $titolo_annuncio = trim($_POST['titolo_annuncio']);
    $contenuto_annuncio = trim($_POST['contenuto_annuncio']);

    if (!empty($titolo_annuncio) && !empty($contenuto_annuncio) && $corso_id > 0) {
        try {
            $stmt_ann = $pdo->prepare("INSERT INTO annunci (corso_id, docente_id, titolo, contenuto) VALUES (:corso_id, :docente_id, :titolo, :contenuto)");
            $stmt_ann->execute([
                ':corso_id' => $corso_id,
                ':docente_id' => $docente_id,
                ':titolo' => $titolo_annuncio,
                ':contenuto' => $contenuto_annuncio
            ]);
            $messaggio = "Annuncio pubblicato con successo in bacheca! 📢";
            $tipo_messaggio = "success";
        } catch (PDOException $e) {
            $messaggio = "Errore durante la pubblicazione dell'annuncio.";
            $tipo_messaggio = "error";
        }
    } else {
        $messaggio = "Tutti i campi dell'annuncio sono obbligatori.";
        $tipo_messaggio = "warning";
    }
}

// --- RECUPERO DATI CORRENTI DEL DOCENTE ---
$stmt_doc = $pdo->prepare("SELECT * FROM docenti WHERE id = :id");
$stmt_doc->execute([':id' => $docente_id]);
$dati_docente = $stmt_doc->fetch(PDO::FETCH_ASSOC);

// Recupero dei corsi presieduti per il menu a tendina degli annunci
$stmt_corsi = $pdo->prepare("SELECT id, titolo FROM corsi WHERE docente_id = :docente_id ORDER BY titolo ASC");
$stmt_corsi->execute([':docente_id' => $docente_id]);
$corsi_docente = $stmt_corsi->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestisci Profilo - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="teacherdash.css">
</head>
<body>

<!-- NAVBAR AGGIORNATA CON TUTTI I COLLEGAMENTI DOCENTE -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="teacher_dashboard.php">Pannello Docente</a>
            <a href="view-students.php">I tuoi Allievi</a>
            <a href="#" class="active">Gestisci Profilo</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <section class="profile-banner">
        <h1>Area Personale del Docente</h1>
        <p class="profile-role">Aggiorna le tue info pubbliche o comunica con le tue classi</p>
    </section>

    <!-- Feedback operazioni -->
    <?php if (!empty($messaggio)): ?>
        <div class="alert-box <?php echo $tipo_messaggio; ?>">
            <?php echo htmlspecialchars($messaggio); ?>
        </div>
    <?php endif; ?>

    <!-- MODULO 1: AGGIORNAMENTO INFORMAZIONI PROFILO -->
    <section class="course-creation-section" style="margin-bottom: 35px;">
        <h2 class="section-title">Modifica le informazioni del tuo profilo</h2>
        <p class="section-subtitle">Questi dettagli saranno visibili agli studenti che frequentano o cercano i tuoi corsi.</p>

        <form class="creation-form" action="manage-profile.php" method="POST">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="specializzazione">La tua Specializzazione Professionale</label>
                <input type="text" id="specializzazione" name="specializzazione" 
                       value="<?php echo htmlspecialchars($dati_docente['specializzazione']); ?>" required>
            </div>

            <div class="form-group">
                <label for="sito_web">Sito Web Personale / Portfolio (Opzionale)</label>
                <input type="url" id="sito_web" name="sito_web" 
                       value="<?php echo htmlspecialchars($dati_docente['sito_web'] ?? ''); ?>" placeholder="https://tuosito.com">
            </div>

            <div class="form-group">
                <label for="biografia">La tua Biografia / Presentazione</label>
                <textarea id="biografia" name="biografia" rows="4" required><?php echo htmlspecialchars($dati_docente['biografia']); ?></textarea>
            </div>

            <button type="submit" class="btn-submit-form" style="background: #059669;">
                Salva Modifiche Profilo 💾
            </button>
        </form>
    </section>

    <!-- MODULO 2: PUBBLICAZIONE AVVISI IN BACHECA -->
    <section class="course-creation-section" style="border-color: rgba(16, 185, 129, 0.25);">
        <h2 class="section-title">📢 Pubblica un nuovo annuncio in bacheca</h2>
        <p class="section-subtitle">Invia una notifica o comunicazione importante direttamente nella dashboard dei tuoi iscritti.</p>

        <form class="creation-form" action="manage-profile.php" method="POST">
            <input type="hidden" name="action" value="publish_announcement">

            <div class="form-group">
                <label for="corso_id_annuncio">Seleziona il Corso di Riferimento</label>
                <select id="corso_id_annuncio" name="corso_id_annuncio" required>
                    <?php if (count($corsi_docente) > 0): ?>
                        <?php foreach ($corsi_docente as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['titolo']); ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Non hai ancora corsi attivi a cui associare annunci</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="titolo_annuncio">Titolo dell'Avviso</label>
                <input type="text" id="titolo_annuncio" name="titolo_annuncio" placeholder="Es: Spostamento orario lezione del lunedì" required>
            </div>

            <div class="form-group">
                <label for="contenuto_annuncio">Testo del Messaggio</label>
                <textarea id="contenuto_annuncio" name="contenuto_annuncio" rows="4" placeholder="Scrivi qui la comunicazione per gli studenti..." required></textarea>
            </div>

            <button type="submit" class="btn-submit-form" <?php echo (count($corsi_docente) === 0) ? 'disabled style="background:#cbd5e1; cursor:not-allowed; box-shadow:none;"' : ''; ?>>
                Invia Avviso Ufficiale 📢
            </button>
        </form>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

</body>
</html>
