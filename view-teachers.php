<?php
session_start();

// Controllo di sicurezza: accesso consentito solo agli utenti loggati
if (!isset($_SESSION['utente_id'])) {
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

// ESTRAZIONE DI TUTTI I DOCENTI DALLA TABELLA DEL DATABASE
try {
    $query = $pdo->query("SELECT id, username, email, specializzazione, biografia, sito_web FROM docenti ORDER BY username ASC");
    $elenco_docenti = $query->fetchAll(PDO::FETCH_ASSOC);
    $totale_docenti = count($elenco_docenti);
} catch (PDOException $e) {
    die("Errore nel recupero della lista docenti: " . $e->getMessage());
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
        <a href="index.php" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="student_dashboard.php">Dashboard</a>
            <a href="#" class="active">Docenti</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <!-- Banner Introduttivo Corpo Docenti -->
    <section class="profile-banner" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
        <h1>Incontra i nostri Docenti</h1>
        <p class="profile-role">Formati con i migliori professionisti del settore | Docenti attivi: <?php echo $totale_docenti; ?></p>
    </section>

    <!-- GRIGLIA DEI DOCENTI -->
    <section class="section-block">
        <h2 class="section-title">Elenco dei Professori della piattaforma</h2>
        <div class="courses-grid">
            
            <?php if ($totale_docenti > 0): ?>
                <?php foreach ($elenco_docenti as $docente): ?>
                    
                    <!-- CARD DOCENTE STILE LEARNIFY -->
                    <article class="course-card" style="border-left: 4px solid #10b981;">
                        <div class="card-body">
                            <!-- CONTROLLO SPECIALIZZAZIONE VUOTA -->
                            <span class="badge" style="background: #d1fae5; color: #065f46;">
                                🎓 <?php echo htmlspecialchars($docente['specializzazione'] ? $docente['specializzazione'] : 'Docente Learnify'); ?>
                            </span>
                            
                            <h3 style="margin-top: 14px; margin-bottom: 4px;">Prof. <?php echo htmlspecialchars(ucfirst($docente['username'])); ?></h3>
                            <p class="course-info" style="margin-bottom: 12px;">✉️ <?php echo htmlspecialchars($docente['email']); ?></p>
                            
                            <!-- CONTROLLO SITO WEB (Se c'è lo mostra, altrimenti mette un testo neutro) -->
                            <p class="course-info" style="margin-bottom: 15px; font-size: 0.85rem;">
                                🌐 Sito: 
                                <?php if (!empty($docente['sito_web'])): ?>
                                    <a href="<?php echo htmlspecialchars($docente['sito_web']); ?>" target="_blank" style="color:var(--primary); text-decoration:none; font-weight:600;"><?php echo htmlspecialchars($docente['sito_web']); ?></a>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-style:italic;">Non specificato</span>
                                <?php endif; ?>
                            </p>
                            
                            <!-- CONTROLLO BIOGRAFIA VUOTA O STANDARD -->
                            <p class="course-description" style="font-size: 0.85rem; line-height: 1.4;">
                                <?php 
                                    $bio = trim($docente['biografia']);
                                    // Se la bio è vuota o ha la frase di default della registrazione
                                    if (empty($bio) || $bio === 'Nessuna biografia inserita.') {
                                        echo '<span style="color:#94a3b8; font-style:italic;">Presentazione personale non ancora compilata dal docente.</span>';
                                    } else {
                                        // Mostra un estratto se il testo è molto lungo
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
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

</body>
</html>
