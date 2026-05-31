<?php
session_start();

// Controllo di sicurezza: accesso consentito solo agli utenti loggati
if (!isset($_SESSION['utente_id']) || $_SESSION['ruolo'] !== 'student') {
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

$studente_id = $_SESSION['utente_id'];
$username_studente = $_SESSION['username'];

// QUERY 1: ESTRAZIONE DI TUTTI I DOCENTI DELLA PIATTAFORMA (Catalogo Generale)
try {
    $query = $pdo->query("SELECT id, username, email, specializzazione, biografia, sito_web FROM docenti ORDER BY username ASC");
    $elenco_docenti = $query->fetchAll(PDO::FETCH_ASSOC);
    $totale_docenti = count($elenco_docenti);
} catch (PDOException $e) {
    die("Errore nel recupero della lista docenti: " . $e->getMessage());
}

// QUERY 2: ESTRAZIONE ESCLUSIVA DEI DOCENTI DELLO STUDENTE LOGGATO
try {
    $stmt_miei = $pdo->prepare("
        SELECT DISTINCT docenti.id, docenti.username, docenti.email, docenti.specializzazione, docenti.biografia, docenti.sito_web
        FROM iscrizioni_corsi
        JOIN corsi ON iscrizioni_corsi.corso_id = corsi.id
        JOIN docenti ON corsi.docente_id = docenti.id
        WHERE iscrizioni_corsi.studente_id = :studente_id
        ORDER BY docenti.username ASC
    ");
    $stmt_miei->execute([':studente_id' => $studente_id]);
    $miei_docenti = $stmt_miei->fetchAll(PDO::FETCH_ASSOC);
    $totale_miei_docenti = count($miei_docenti);
} catch (PDOException $e) {
    die("Errore nel recupero dei tuoi docenti: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corpo Docenti - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studentistyle.css">
</head>
<body>

<!-- NAVBAR COERENTE -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.html" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
		<a href="#" class="active">Docenti</a>
            <a href="manage-courses.php">Gestione corsi</a>
			<a href="student_dashboard.php">Home</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <!-- Banner Introduttivo Corpo Docenti -->
    <section class="profile-banner" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
        <h1>Incontra i nostri Docenti</h1>
        <p class="profile-role">Formati con i migliori professionisti del settore | Docenti in piattaforma: <?php echo $totale_docenti; ?></p>
    </section>

    <!-- SEZIONE 1: TUTTI I DOCENTI (CATALOGO GENERALE) -->
    <section class="section-block">
        <h2 class="section-title">Elenco completo dei Professori</h2>
        <div class="courses-grid">
            
            <?php if ($totale_docenti > 0): ?>
                <?php foreach ($elenco_docenti as $docente): ?>
                    
                    <article class="course-card" style="border-left: 4px solid #10b981;">
                        <div class="card-body">
                            <span class="badge" style="background: #d1fae5; color: #065f46;">
                                🎓 <?php echo htmlspecialchars($docente['specializzazione'] ? $docente['specializzazione'] : 'Docente Learnify'); ?>
                            </span>
                            
                            <h3 style="margin-top: 14px; margin-bottom: 4px;">Prof. <?php echo htmlspecialchars(ucfirst($docente['username'])); ?></h3>
                            <p class="course-info" style="margin-bottom: 12px;">✉️ <?php echo htmlspecialchars($docente['email']); ?></p>
                            
                            <p class="course-info" style="margin-bottom: 15px; font-size: 0.85rem;">
                                🌐 Sito: 
                                <?php if (!empty($docente['sito_web'])): ?>
                                    <a href="<?php echo htmlspecialchars($docente['sito_web']); ?>" target="_blank" style="color:var(--primary); text-decoration:none; font-weight:600;"><?php echo htmlspecialchars($docente['sito_web']); ?></a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-style:italic;">Non specificato</span>
                                <?php endif; ?>
                            </p>
                            
                            <p class="course-description" style="font-size: 0.85rem; line-height: 1.4;">
                                <?php 
                                    $bio = trim($docente['biografia']);
                                    if (empty($bio) || $bio === 'Nessuna biografia inserita.') {
                                        echo '<span style="color:#94a3b8; font-style:italic;">Presentazione personale non ancora compilata dal docente.</span>';
                                    } else {
                                        echo (strlen($bio) > 110) ? substr($bio, 0, 110) . '...' : $bio; 
                                    }
                                ?>
                            </p>
                        </div>
                    </article>

                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 2; text-align: center; color: var(--text-muted); padding: 20px;">
                    Nessun docente presente nel database in questo momento.
                </div>
            <?php endif; ?>
            
        </div>
    </section>

    <!-- SEZIONE 2: I TUOI DOCENTI (FILTRATI SULL'UTENTE LOGGATO) -->
    <section class="section-block" style="margin-top: 50px;">
        <h2 class="section-title" style="color: var(--primary);">👨‍🏫 I tuoi docenti di riferimento</h2>
        <p class="section-subtitle" style="margin: -10px 0 20px 0; font-size: 0.88rem; color: var(--text-muted);">
            Ecco i professori che tengono i corsi a cui ti sei iscritto.
        </p>
        
        <div class="courses-grid">
            <?php if ($totale_miei_docenti > 0): ?>
                <?php foreach ($miei_docenti as $mio_docente): ?>
                    
                    <article class="course-card" style="border-left: 4px solid var(--primary); background: #fdfdfd;">
                        <div class="card-body">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                                📚 Mio Professore
                            </span>
                            
                            <h3 style="margin-top: 14px; margin-bottom: 4px;">Prof. <?php echo htmlspecialchars(ucfirst($mio_docente['username'])); ?></h3>
                            <p class="course-info" style="margin-bottom: 8px; font-size: 0.85rem;">🎯 Area: <?php echo htmlspecialchars($mio_docente['specializzazione']); ?></p>
                            <p class="course-info" style="margin-bottom: 12px;">✉️ <a href="mailto:<?php echo htmlspecialchars($mio_docente['email']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($mio_docente['email']); ?></a></p>
                        </div>
                    </article>

                <?php endforeach; ?>
            <?php else: ?>
                <!-- Messaggio se lo studente non è ancora iscritto a nessun corso -->
                <div style="grid-column: span 2; text-align: center; color: #94a3b8; padding: 30px; background: #ffffff; border: 2px dashed #e2e8f0; border-radius: 12px; font-style: italic; font-size: 0.9rem;">
                    Non hai ancora docenti di riferimento perché non sei iscritto a nessun corso attivo. 
                    <a href="manage-courses.php" style="color: var(--primary); font-weight: 600; text-decoration: none; font-style: normal;">Scegli un corso ora ➔</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

</body>
</html>
