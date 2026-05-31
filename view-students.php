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

/* 
   QUERY RELAZIONALE: Recupera gli studenti iscritti SOLO ai corsi 
   che appartengono al docente attualmente loggato
*/
try {
    $stmt = $pdo->prepare("
        SELECT studenti.username AS nome_allievo, studenti.email AS email_allievo, 
               corsi.titolo AS nome_corso, iscrizioni_corsi.progresso 
        FROM iscrizioni_corsi
        JOIN studenti ON iscrizioni_corsi.studente_id = studenti.id
        JOIN corsi ON iscrizioni_corsi.corso_id = corsi.id
        WHERE corsi.docente_id = :docente_id
        ORDER BY corsi.titolo ASC, studenti.username ASC
    ");
    $stmt->execute([':docente_id' => $docente_id]);
    $studenti_iscritti = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totale_studenti = count($studenti_iscritti);
} catch (PDOException $e) {
    die("Errore nel recupero degli allievi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elenco Allievi - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="teacherdash.css">
</head>
<body>

<!-- NAVBAR COORDINATA CON IL NUOVO LINK -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
            <a href="manage-profile.php">Gestisci Profilo</a>
		    <a href="#" class="active">I tuoi Allievi</a>
            <a href="teacher_dashboard.php">Home</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <!-- Banner Riepilogativo Allievi -->
    <section class="profile-banner">
        <h1>Registro Allievi Iscritti</h1>
        <p class="profile-role">Totale studenti unici nei tuoi corsi: <?php echo $totale_studenti; ?></p>
    </section>

    <!-- TABELLA ALLIEVI STILE BOOTSTRAP PREMIUM -->
    <section class="section-block">
        <h2 class="section-title">Elenco dettagliato delle iscrizioni</h2>
        
        <?php if ($totale_studenti > 0): ?>
            <div class="table-responsive">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Corso Presieduto</th>
                            <th>Nome Allievo</th>
                            <th>Indirizzo Email</th>
                            <th style="text-align: center;">Progresso Studio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studenti_iscritti as $allievo): ?>
                            <tr>
                                <td class="td-course-title"><?php echo htmlspecialchars($allievo['nome_corso']); ?></td>
                                <td class="td-student-name">👤 <?php echo htmlspecialchars($allievo['nome_allievo']); ?></td>
                                <td class="td-student-email"><?php echo htmlspecialchars($allievo['email_allievo']); ?></td>
                                <td>
                                    <!-- Mini barra di avanzamento grafica all'interno della cella -->
                                    <div class="table-progress-container">
                                        <div class="table-progress-bar" style="width: <?php echo intval($allievo['progresso']); ?>%;"></div>
                                    </div>
                                    <span class="table-progress-text"><?php echo intval($allievo['progresso']); ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Stato vuoto se nessuno studente si è ancora registrato a un corso del docente -->
            <div class="empty-dashboard-state" style="text-align: center; padding: 40px 20px; background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b;">
                <div style="font-size: 2.5rem; margin-bottom: 10px;">👨‍🎓</div>
                <h3 style="margin: 0 0 6px 0; color: #0f172a;">Nessun allievo iscritto</h3>
                <p style="margin: 0; font-size: 0.9rem;">Attualmente nessun utente si è ancora registrato ai corsi da te pubblicati.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

</body>
</html>
