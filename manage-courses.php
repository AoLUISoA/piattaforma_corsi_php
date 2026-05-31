<?php
session_start();

// Controllo di sicurezza: accesso consentito solo agli studenti loggati
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
$messaggio = "";
$tipo_messaggio = "";

// --- AZIONE 1: GESTIONE DISISCRIZIONE (CANCELLAZIONE) ---
if (isset($_GET['action']) && $_GET['action'] === 'unsubscribe' && isset($_GET['corso_id'])) {
    $corso_id = intval($_GET['corso_id']);
    try {
        $stmt_del = $pdo->prepare("DELETE FROM iscrizioni_corsi WHERE studente_id = :studente_id AND corso_id = :corso_id");
        $stmt_del->execute([':studente_id' => $studente_id, ':corso_id' => $corso_id]);
        
        $messaggio = "Cancellazione dal corso effettuata con successo.";
        $tipo_messaggio = "success";
    } catch (PDOException $e) {
        $messaggio = "Errore durante la disiscrizione dal corso.";
        $tipo_messaggio = "error";
    }
}

// --- AZIONE 2: GESTIONE NUOVA ISCRIZIONE ---
if (isset($_GET['action']) && $_GET['action'] === 'subscribe' && isset($_GET['corso_id'])) {
    $corso_id = intval($_GET['corso_id']);
    
    // Controlliamo prima quanti corsi ha attivi l'utente in questo momento
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM iscrizioni_corsi WHERE studente_id = :studente_id");
    $stmt_count->execute([':studente_id' => $studente_id]);
    $corsi_attivi = $stmt_count->fetchColumn();

    if ($corsi_attivi >= 3) {
        $messaggio = "⚠️ Limite raggiunto! Non puoi seguire più di 3 corsi contemporaneamente. Disiscriviti da un corso per poterti iscrivere a questo.";
        $tipo_messaggio = "warning";
    } else {
        try {
            $stmt_ins = $pdo->prepare("INSERT IGNORE INTO iscrizioni_corsi (studente_id, corso_id, progresso) VALUES (:studente_id, :corso_id, 0)");
            $stmt_ins->execute([':studente_id' => $studente_id, ':corso_id' => $corso_id]);
            
            $messaggio = "Iscrizione al nuovo corso completata! 🚀";
            $tipo_messaggio = "success";
        } catch (PDOException $e) {
            $messaggio = "Errore durante l'iscrizione al corso.";
            $tipo_messaggio = "error";
        }
    }
}

// --- RECUPERO DATI PER LE GRIGLIE ---
// 1. Corsi a cui lo studente È ATTUALMENTE ISCRITTO
$stmt_iscritti = $pdo->prepare("
    SELECT corsi.* FROM iscrizioni_corsi 
    JOIN corsi ON iscrizioni_corsi.corso_id = corsi.id 
    WHERE iscrizioni_corsi.studente_id = :studente_id
");
$stmt_iscritti->execute([':studente_id' => $studente_id]);
$corsi_attivi_elenco = $stmt_iscritti->fetchAll(PDO::FETCH_ASSOC);
$conteggio_attivi = count($corsi_attivi_elenco);

// IMPAGINAZIONE CORSI ATTIVI: Max 2 corsi per blocco pagina
$attivi_per_pagina = 2;
$pagine_attivi = array_chunk($corsi_attivi_elenco, $attivi_per_pagina);
$totale_pagine_attivi = count($pagine_attivi);

// 2. Corsi disponibili a cui lo studente NON È ANCORA ISCRITTO
$stmt_disponibili = $pdo->prepare("
    SELECT * FROM corsi 
    WHERE id NOT IN (SELECT corso_id FROM iscrizioni_corsi WHERE studente_id = :studente_id)
    ORDER BY titolo ASC
");
$stmt_disponibili->execute([':studente_id' => $studente_id]);
$corsi_disponibili_elenco = $stmt_disponibili->fetchAll(PDO::FETCH_ASSOC);

// IMPAGINAZIONE CORSI DISPONIBILI: Max 4 corsi per blocco pagina
$corsi_per_pagina = 4;
$pagine_disponibili = array_chunk($corsi_disponibili_elenco, $corsi_per_pagina);
$totale_pagine_disponibili = count($pagine_disponibili);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Corsi - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studentistyle.css">
</head>
<body>

<!-- NAVBAR STRUTTURATA -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.html" class="nav-brand">Learn<span class="brand-accent">ify</span></a>
        <div class="nav-links">
            <a href="#" class="active">Gestione corsi</a>
			<a href="student_dashboard.php">Home</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <section class="profile-banner" style="background: linear-gradient(135deg, #312e81 0%, #1e1b4b 100%);">
        <h1>Centro Gestione Piano di Studi</h1>
        <p class="profile-role">Slot occupati: <?php echo $conteggio_attivi; ?> / 3 corsi massimi</p>
    </section>

    <!-- Banner di notifica operazioni -->
    <?php if (!empty($messaggio)): ?>
        <div class="alert-box <?php echo $tipo_messaggio; ?>">
            <?php echo htmlspecialchars($messaggio); ?>
        </div>
    <?php endif; ?>

    <!-- BLOCCO 1: CORSI ATTIVI (MAX 2 PER PAGINA) -->
    <section class="section-block">
        <h2 class="section-title">I tuoi corsi attuali (Clicca per abbandonare)</h2>
        
        <div class="selection-carousel-wrapper">
            <div class="selection-carousel-track" id="activeTrack">
                
                <?php if ($conteggio_attivi > 0): ?>
                    <?php foreach ($pagine_attivi as $indice_pagina => $gruppo_corsi): ?>
                        
                        <div class="selection-carousel-page">
                            <div class="courses-grid">
                                <?php foreach ($gruppo_corsi as $corso): ?>
                                    <article class="course-card" style="border-color: rgba(99, 102, 241, 0.2);">
                                        <div class="card-body">
                                            <span class="badge tag-<?php echo strtolower(htmlspecialchars($corso['categoria'])); ?>">
                                                <?php echo htmlspecialchars($corso['categoria']); ?>
                                            </span>
                                            <h3 style="margin-top:12px;"><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                                            <p class="course-description"><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <a href="manage-courses.php?action=unsubscribe&corso_id=<?php echo $corso['id']; ?>" 
                                               class="btn-course" 
                                               style="background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5;"
                                               onclick="return confirm('Sei sicuro di voler cancellare la tua iscrizione da questo corso? I progressi andranno persi.');">
                                                ❌ Disiscriviti dal corso
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 20px; width:100%;">
                        Al momento non hai nessun corso attivo nel tuo piano di studi.
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- PUNTINI CORSI ATTIVI -->
        <?php if ($totale_pagine_attivi > 1): ?>
            <div class="selection-carousel-dots" id="activeDots">
                <?php for ($i = 0; $i < $totale_pagine_attivi; $i++): ?>
                    <span class="select-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"></span>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- BLOCCO 2: CATALOGO AGGIUNTIVO DISPONIBILE (MAX 4 PER PAGINA) -->
    <section class="section-block" style="margin-top: 40px;">
        <h2 class="section-title">Aggiungi altri corsi disponibili nel catalogo</h2>
        
        <div class="selection-carousel-wrapper">
            <div class="selection-carousel-track" id="availableTrack">
                
                <?php if ($totale_pagine_disponibili > 0): ?>
                    <?php foreach ($pagine_disponibili as $indice_pagina => $gruppo_corsi): ?>
                        
                        <div class="selection-carousel-page">
                            <div class="courses-grid">
                                <?php foreach ($gruppo_corsi as $corso): ?>
                                    <article class="course-card">
                                        <div class="card-body">
                                            <span class="badge tag-<?php echo strtolower(htmlspecialchars($corso['categoria'])); ?>">
                                                <?php echo htmlspecialchars($corso['categoria']); ?>
                                            </span>
                                            <h3 style="margin-top:12px;"><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                                            <p class="course-description"><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                                            <p class="course-info" style="margin: 10px 0 0 0;">⏱️ Durata: <?php echo $corso['durata']; ?> ore | Livello: <?php echo $corso['livello']; ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <a href="manage-courses.php?action=subscribe&corso_id=<?php echo $corso['id']; ?>" 
                                               class="btn-course btn-join" 
                                               style="text-align: center;">
                                                ➕ Iscriviti a questo corso
                                            </a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 20px; width:100%;">
                        Complimenti! Sei iscritto a tutti i corsi attualmente disponibili sulla piattaforma.
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- PUNTINI CORSI DISPONIBILI -->
        <?php if ($totale_pagine_disponibili > 1): ?>
            <div class="selection-carousel-dots" id="availableDots">
                <?php for ($i = 0; $i < $totale_pagine_disponibili; $i++): ?>
                    <span class="select-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"></span>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

<script>
// --- ENGINE DI SCORRIMENTO UNIFICATO PER I DUE CAROSELLI INDIPENDENTI ---

document.addEventListener('DOMContentLoaded', () => {
    
    // Funzione interna riutilizzabile per inizializzare un carosello autonomo
    function initCarousel(trackId, dotsContainerId) {
        const track = document.getElementById(trackId);
        const dots = document.querySelectorAll(`#${dotsContainerId} .select-dot`);

        if (!track || dots.length === 0) return;

        function updatePage(pageIndex) {
            const firstPage = track.querySelector('.selection-carousel-page');
            if (!firstPage) return;
            
            const pageWidth = firstPage.getBoundingClientRect().width;
            
            // Esegue lo slittamento orizzontale della traccia passata come parametro
            track.style.transform = `translateX(-${pageIndex * pageWidth}px)`;
            
            // Aggiorna lo stato visivo (allungamento) del pallino corrente di quel carosello
            dots.forEach(dot => dot.classList.remove('active'));
            if (dots[pageIndex]) dots[pageIndex].classList.add('active');
        }

        // Assegna il listener di clic a ciascun pallino specifico
        dots.forEach(dot => {
            dot.addEventListener('click', (e) => {
                const targetPage = parseInt(e.target.getAttribute('data-page'), 10);
                updatePage(targetPage);
            });
        });

        // Corregge e adatta la visualizzazione se l'utente ridimensiona lo schermo
        window.addEventListener('resize', () => {
            const activeDot = document.querySelector(`#${dotsContainerId} .select-dot.active`);
            if (activeDot) {
                const currentPage = parseInt(activeDot.getAttribute('data-page'), 10);
                updatePage(currentPage);
            }
        });
    }

    // ACCENDIAMO I DUE CAROSELLI IN MODO SEPARATO E AUTONOMO
    initCarousel('activeTrack', 'activeDots');       // Carosello 1: Corsi attivi (Max 2 per pagina)
    initCarousel('availableTrack', 'availableDots'); // Carosello 2: Corsi disponibili (Max 4 per pagina)
});

</script>

</body>
</html>
