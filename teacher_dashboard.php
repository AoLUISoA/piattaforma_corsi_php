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
$username_docente = $_SESSION['username'];
$messaggio = "";
$tipo_messaggio = "";

// --- AZIONE 1: CREAZIONE DI UN NUOVO CORSO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    $titolo = trim($_POST['titolo']);
    $categoria = $_POST['categoria'];
    $livello = $_POST['livello'];
    $descrizione = trim($_POST['descrizione']);
    $durata = intval($_POST['durata']);

    if (!empty($titolo) && !empty($descrizione) && $durata > 0) {
        try {
            $stmt_add = $pdo->prepare("INSERT INTO corsi (docente_id, titolo, categoria, livello, descrizione, durata) VALUES (:docente_id, :titolo, :categoria, :livello, :descrizione, :durata)");
            $stmt_add->execute([
                ':docente_id' => $docente_id,
                ':titolo' => $titolo,
                ':categoria' => $categoria,
                ':livello' => $livello,
                ':descrizione' => $descrizione,
                ':durata' => $durata
            ]);
            $messaggio = "Nuovo corso creato e pubblicato con successo! 🚀";
            $tipo_messaggio = "success";
        } catch (PDOException $e) {
            $messaggio = "Errore durante la creazione del corso.";
            $tipo_messaggio = "error";
        }
    } else {
        $messaggio = "Tutti i campi del modulo sono obbligatori.";
        $tipo_messaggio = "warning";
    }
}

// --- AZIONE 2: RIMOZIONE DI UN CORSO ESISTENTE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_course' && isset($_GET['corso_id'])) {
    $corso_id = intval($_GET['corso_id']);
    
    try {
        // Verifica di sicurezza: il docente possiede davvero questo corso?
        $stmt_check = $pdo->prepare("SELECT id FROM corsi WHERE id = :corso_id AND docente_id = :docente_id");
        $stmt_check->execute([':corso_id' => $corso_id, ':docente_id' => $docente_id]);
        
        if ($stmt_check->fetch()) {
            $stmt_del = $pdo->prepare("DELETE FROM corsi WHERE id = :corso_id");
            $stmt_del->execute([':corso_id' => $corso_id]);
            $messaggio = "Corso rimosso definitivamente dalla piattaforma.";
            $tipo_messaggio = "success";
        } else {
            $messaggio = "Azione non autorizzata.";
            $tipo_messaggio = "error";
        }
    } catch (PDOException $e) {
        $messaggio = "Errore durante la rimozione del corso.";
        $tipo_messaggio = "error";
    }
}

// --- RECUPERO DEI CORSI GESTITI CON CONTEGGIO ALUNNI ISCRITTI ---
try {
    $stmt_corsi = $pdo->prepare("
        SELECT corsi.*, COUNT(iscrizioni_corsi.id) AS totale_alunni 
        FROM corsi 
        LEFT JOIN iscrizioni_corsi ON corsi.id = iscrizioni_corsi.corso_id 
        WHERE corsi.docente_id = :docente_id 
        GROUP BY corsi.id 
        ORDER BY corsi.data_creazione DESC
    ");
    $stmt_corsi->execute([':docente_id' => $docente_id]);
    $corsi_presieduti = $stmt_corsi->fetchAll(PDO::FETCH_ASSOC);
    $totale_corsi = count($corsi_presieduti);
} catch (PDOException $e) {
    die("Errore nel recupero dei corsi: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Docente - Learnify</title>
    <!-- Collegamento corretto a Google Fonts -->
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Foglio di stile dedicato per il pannello del professore -->
    <link rel="stylesheet" href="teacherdash.css">
</head>
<body>

<!-- NAVBAR COORDINATA -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
		<a href="manage-profile.php">Gestisci Profilo</a>
            <a href="view-students.php">I tuoi Allievi</a>
            <a href="#" class="active">Home</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <!-- Banner Profilo Docente -->
    <section class="profile-banner">
        <h1>Benvenuto, Prof. <?php echo htmlspecialchars(ucfirst($username_docente)); ?></h1>
        <p class="profile-role">Ruolo: Personale Docente | Corsi attivi: <?php echo $totale_corsi; ?></p>
    </section>

    <!-- Notifiche operazioni (Successo / Errore) -->
    <?php if (!empty($messaggio)): ?>
        <div class="alert-box <?php echo $tipo_messaggio; ?>">
            <?php echo htmlspecialchars($messaggio); ?>
        </div>
    <?php endif; ?>

    <!-- SEZIONE 1: CORSI PRESIEDUTI DAL DOCENTE -->
    <section class="section-block">
        <h2 class="section-title">I tuoi corsi presieduti</h2>
        <div class="courses-grid">
            <?php if ($totale_corsi > 0): ?>
                <?php foreach ($corsi_presieduti as $corso): ?>
                    <article class="course-card">
                        <div class="card-body">
                            <span class="badge tag-<?php echo strtolower(htmlspecialchars($corso['categoria'])); ?>">
                                <?php echo htmlspecialchars(str_replace('-', ' & ', ucwords($corso['categoria']))); ?>
                            </span>
                            <h3 style="margin-top:12px;"><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                            <p class="course-description"><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                            
                            <!-- INFO ALUNNI E META INFORMAZIONI -->
                            <div class="teacher-stats-info">
                                <span class="student-count">👨‍🎓 Alunni iscritti: <strong><?php echo $corso['totale_alunni']; ?></strong></span>
                                <span class="course-meta">⏱️ <?php echo $corso['durata']; ?> ore | Livello: <?php echo $corso['livello']; ?></span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="teacher_dashboard.php?action=delete_course&corso_id=<?php echo $corso['id']; ?>" 
                               class="btn-course btn-delete" 
                               onclick="return confirm('Sei sicuro di voler eliminare definitivamente questo corso e tutte le iscrizioni degli studenti? L\'action è irreversibile.');">
                                Rimuovi Corso
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-dashboard-state" style="grid-column: span 2; text-align: center; color: var(--text-muted); padding: 30px 10px;">
                    <p>Non hai ancora pubblicato nessun corso. Compila il modulo in basso per creare il tuo primo percorso formativo.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SEZIONE 2: CREAZIONE NUOVO CORSO -->
    <section class="course-creation-section">
        <h2 class="section-title">Crea e pubblica un nuovo corso</h2>
        <p class="section-subtitle">Inserisci i dettagli per distribuire istantaneamente la tua materia nel catalogo degli allievi.</p>

        <form class="creation-form" action="teacher_dashboard.php" method="POST">
            <input type="hidden" name="action" value="create_course">

            <div class="form-group">
                <label for="titolo">Titolo del Corso</label>
                <input type="text" id="titolo" name="titolo" placeholder="Es: Introduzione a JavaScript Avanzato" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="categoria">Categoria Macro</label>
                    <select id="categoria" name="categoria" required>
                        <option value="tech">Tech</option>
                        <option value="business">Business</option>
                        <option value="design">Design</option>
                        <option value="data-ai">Data & AI</option>
                        <option value="finance">Finance</option>
                        <option value="photography">Photography</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="livello">Livello di Difficoltà</label>
                    <select id="livello" name="livello" required>
                        <option value="Base">Base</option>
                        <option value="Intermedio">Intermedio</option>
                        <option value="Avanzato">Avanzato</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="durata">Durata Totale (in ore)</label>
                <input type="number" id="durata" name="durata" min="1" placeholder="Es: 30" required>
            </div>

            <div class="form-group">
                <label for="descrizione">Descrizione del Programma</label>
                <textarea id="descrizione" name="descrizione" rows="4" placeholder="Descrivi brevemente gli argomenti trattati e gli obiettivi del corso..." required></textarea>
            </div>

            <button type="submit" class="btn-submit-form">
                Pubblica Corso nel Catalogo 🚀
            </button>
        </form>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

</body>
</html>