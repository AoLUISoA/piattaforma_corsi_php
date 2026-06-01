<?php
// Avviamo la sessione per identificare lo studente loggato
session_start();

// Controllo di sicurezza: se l'utente non è loggato o non è uno studente, lo rimandiamo al login
if (!isset($_SESSION['utente_id']) || $_SESSION['ruolo'] !== 'student') {
    header("Location: login.php");
    exit();
}

// PARAMETRI DI CONNESSIONE AL TUO DATABASE IN LOCALE SU XAMPP
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

// Recuperiamo l'ID e lo Username dello studente dalla sessione attiva
$studente_id = $_SESSION['utente_id'];
$username_studente = $_SESSION['username'];

// --- AZIONE: CONFERMA LETTURA ANNUNCIO ---
if (isset($_GET['action']) && $_GET['action'] === 'read_announcement' && isset($_GET['annuncio_id'])) {
    $annuncio_id = intval($_GET['annuncio_id']);
    
    try {
        $stmt_read = $pdo->prepare("INSERT IGNORE INTO annunci_letti (studente_id, annuncio_id) VALUES (:studente_id, :annuncio_id)");
        $stmt_read->execute([':studente_id' => $studente_id, ':annuncio_id' => $annuncio_id]);
        
        // Rinfresca la pagina per far sparire la notifica letta
        header("Location: student_dashboard.php");
        exit();
    } catch (PDOException $e) {
        // Errore silenzioso, la pagina ricarica comunque
    }
}

try {
    // CORRETTO: JOIN basata su corsi.docente_id = docenti.id e recupero di docenti.username
    $stmt = $pdo->prepare("
        SELECT 
            corsi.id AS id_corso, 
            corsi.titolo, 
            corsi.categoria, 
            corsi.livello, 
            corsi.descrizione, 
            iscrizioni_corsi.progresso,
            docenti.username AS username_docente
        FROM iscrizioni_corsi 
        JOIN corsi ON iscrizioni_corsi.corso_id = corsi.id 
        LEFT JOIN docenti ON corsi.docente_id = docenti.id
        WHERE iscrizioni_corsi.studente_id = :studente_id 
        ORDER BY iscrizioni_corsi.data_iscrizione DESC
    ");
    $stmt->execute([':studente_id' => $studente_id]);
    $corsi_iscritti = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
} catch (PDOException $e) {
    die("Errore nel recupero dei tuoi corsi: " . $e->getMessage());
}

// --- ESTRAZIONE DEGLI ANNUNCI NON ANCORA LETTI DALL'UTENTE ---
try {
    // CORRETTO: Recuperiamo anche lo username del docente che ha pubblicato l'annuncio
    $stmt_annunci = $pdo->prepare("
        SELECT 
            annunci.id, 
            annunci.titolo, 
            annunci.contenuto, 
            annunci.data_pubblicazione, 
            corsi.titolo AS nome_corso,
            docenti.username AS username_docente
        FROM annunci
        JOIN corsi ON annunci.corso_id = corsi.id
        JOIN iscrizioni_corsi ON corsi.id = iscrizioni_corsi.corso_id
        LEFT JOIN docenti ON annunci.docente_id = docenti.id
        WHERE iscrizioni_corsi.studente_id = :studente_id
          AND annunci.id NOT IN (
              SELECT annuncio_id FROM annunci_letti WHERE studente_id = :studente_id
          )
        ORDER BY annunci.data_pubblicazione DESC
        LIMIT 5
    ");
    $stmt_annunci->execute([':studente_id' => $studente_id]);
    $annunci_studente = $stmt_annunci->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Errore nel recupero degli annunci dei docenti: " . $e->getMessage());
}

$corsi_per_pagina = 2; 
$pagine_attivi = array_chunk($corsi_iscritti, $corsi_per_pagina);
$conteggio_attivi = count($corsi_iscritti);

// Definiamo la variabile mancante contando quante pagine sono state create
$totale_pagine_attivi = count($pagine_attivi);
?>


<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Studente - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="studentistyle.css">
</head>
<body>

<!-- NAVBAR IN STILE BOOTSTRAP PREMIUM -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-brand">
            Learn<span class="brand-accent">ify</span>
        </a>
        <div class="nav-links">
		 <a href="view-teachers.php">Docenti</a>
			<a href="manage-courses.php">Gestione corsi</a>
			<a href="#" class="active">Home</a>
            <a href="logout.php" class="nav-logout-btn">Esci</a>
        </div>
    </div>
</nav>

<main>
    <!-- Profilo Studente con Nome Utente Dinamico -->
    <section class="profile-banner">
        <h1>Benvenuto, <?php echo htmlspecialchars(ucfirst($username_studente)); ?></h1>
        <p class="profile-role">Profilo: Studente Universitario</p>
    </section>
	<?php if (count($annunci_studente) > 0): ?>
    <section class="section-block announcement-section">
        <h2 class="section-title text-danger">📢 Comunicazioni importanti dai tuoi Docenti</h2>
        <div class="announcements-container">
            <?php foreach ($annunci_studente as $annuncio): ?>
                <div class="announcement-card" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px;">
                    <div style="flex-grow: 1;">
                        <div class="announcement-badge-wrapper">
                            <span class="announcement-course-tag">Materia: <?php echo htmlspecialchars($annuncio['nome_corso']); ?></span>
                        </div>
                        <h4><?php echo htmlspecialchars($annuncio['titolo']); ?></h4>
                        <p><?php echo htmlspecialchars($annuncio['contenuto']); ?></p>
                        <small class="announcement-date">
                            🗓️ Pubblicato il: <?php echo date('d/m/Y H:i', strtotime($annuncio['data_pubblicazione'])); ?>
                        </small>
                    </div>
                    
                    <!-- Pulsante per confermare la lettura e nasconderlo -->
                    <div style="flex-shrink: 0; margin-top: 5px;">
                        <a href="student_dashboard.php?action=read_announcement&annuncio_id=<?php echo $annuncio['id']; ?>" 
                           class="btn-course" 
                           style="background: #ffffff; color: #166534; border: 1px solid #bbf7d0; padding: 6px 12px; font-size: 0.8rem; white-space: nowrap; display: inline-flex;"
                           title="Nascondi questa notifica">
                           ✓ Ho letto
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <!-- Sezione I Miei Corsi Selezionati Dinamicamente -->
    <section class="section-block">
    <div style="margin-bottom: 20px;">
        <h2 class="section-title" style="margin: 0;">I miei percorsi di studio attivi</h2>
    </div>
    
    <div class="selection-carousel-wrapper">
        <div class="selection-carousel-track" id="activeTrack">
            
            <?php 
            $ha_corsi_completati = false; 
            if ($conteggio_attivi > 0): 
            ?>
                <?php foreach ($pagine_attivi as $indice_pagina => $gruppo_corsi): ?>
                    
                    <div class="selection-carousel-page">
                        <div class="courses-grid">
                            <?php foreach ($gruppo_corsi as $corso): 
                                if (intval($corso['progresso']) === 100) { 
                                    $ha_corsi_completati = true; 
                                }
                            ?>
                                
                                <article class="course-card">
                                    <div class="card-body">
                                        <span class="badge tag-<?php echo strtolower(htmlspecialchars($corso['categoria'])); ?>">
                                            <?php echo htmlspecialchars(str_replace('-', ' & ', ucwords($corso['categoria']))); ?>
                                        </span>
                                        <p class="badge-tag">Docente:<strong> <?php echo htmlspecialchars($corso['username_docente'] ?? 'Non assegnato'); ?></strong></p>

                                        <h3 style="margin-top: 12px;"><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                                        <p class="course-description"><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                                        
                                        <p class="course-info">Progresso attuale dello studio:</p>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill" style="width: <?php echo intval($corso['progresso']); ?>%;"></div>
                                        </div>
                                        <span class="progress-text"><?php echo intval($corso['progresso']); ?>% Completato</span>
                                    </div>
                                    <div class="card-actions">
                                        <?php if (intval($corso['progresso']) === 100): ?>
                                            <button type="button" class="btn-course" disabled 
                                                    style="background: #cbd5e1; color: #64748b; border: 1px solid #cbd5e1; cursor: not-allowed; box-shadow: none; text-align: center; width: 100%; display: block;">
                                                🏆 Corso Completato
                                            </button>
                                        <?php else: ?>
                                            <a href="visualizza-corso.php?corso_id=<?php echo $corso['id_corso']; ?>" class="btn-course">Continua corso</a>
                                        <?php endif; ?>
                                    </div>
                                </article>

                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-dashboard-state" style="text-align: center; padding: 40px 20px; background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 12px; color: #64748b; width: 100%; box-sizing: border-box;">
                    <div style="font-size: 2.5rem; margin-bottom: 10px;">📚</div>
                    <h3 style="margin: 0 0 6px 0; color: #0f172a;">Nessun corso attivo</h3>
                    <p style="margin: 0 0 20px 0; font-size: 0.9rem;">Non ti sei ancora iscritto a nessun corso. Inizia subito a personalizzare il tuo catalogo.</p>
                    <a href="selectcourse.php" class="btn-course btn-resume" style="display: inline-flex; width: auto; padding: 10px 24px; text-decoration: none;">Scegli i tuoi corsi ora</a>
                </div>
            <?php endif; ?>
            
        </div>
    </div>

   <?php if ($totale_pagine_attivi > 1): ?>
        <div class="selection-carousel-dots" id="activeDots" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 25px;">
            <?php for ($i = 0; $i < $totale_pagine_attivi; $i++): ?>
                <span class="select-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"></span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>



    <!-- Sezione Lascia un Feedback Dinamico -->
    <section class="feedback-section">
        <h2 class="section-title">Lascia un feedback sulla tua esperienza</h2>
        <p class="section-subtitle">La tua opinione è fondamentale per migliorare la qualità dei nostri percorsi formativi.</p>

        <form class="feedback-form" action="save-feedback.php" method="POST">
            <div class="form-group">
                <label>Seleziona il Corso da Recensire</label>
                <select name="corso_id" required>
                    <?php if ($ha_corsi_completati): ?>
                        <?php foreach ($corsi_iscritti as $corso): ?>
                            <?php if (intval($corso['progresso']) === 100): ?>
                                <option value="<?php echo $corso['id_corso']; ?>"><?php echo htmlspecialchars($corso['titolo']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Nessun corso completato al 100% disponibile per il feedback</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Valutazione del percorso</label>
                <select name="valutazione" required>
                    <option value="5">⭐ 5 - Eccellente</option>
                    <option value="4">⭐ 4 - Molto Buono</option>
                    <option value="3">⭐ 3 - Soddisfacente</option>
                    <option value="2">⭐ 2 - Insufficiente</option>
                    <option value="1">⭐ 1 - Scarso</option>
                </select>
            </div>
			
			

            <div class="form-group">
                <label>Inserisci un tuo commento personale</label>
                <textarea name="commento" rows="4" placeholder="Scrivi qui cosa ne pensi del corso, i punti di forza e cosa miglioreresti..." required></textarea>
            </div>

            <!-- Il bottone si disattiva visivamente e logicamente se non ci sono corsi completati al 100% -->
            <button type="submit" class="btn-submit-form" <?php echo (!$ha_corsi_completati) ? 'disabled style="background:#cbd5e1; cursor:not-allowed; box-shadow:none;"' : ''; ?>>
                Invia Feedback Ufficiale
            </button>
        </form>
    </section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const track = document.getElementById("activeTrack");
    const dots = document.querySelectorAll("#activeDots .select-dot");
    
    if (!track || dots.length === 0) return;

    function updateCarousel(pageIndex) {
        // Sposta il carosello del 100% per ogni pagina
        track.style.transform = `translateX(-${pageIndex * 100}%)`;
        
        // Rimuove la classe attiva da tutti e la assegna al pallino corrente
        dots.forEach(dot => dot.classList.remove("active"));
        dots[pageIndex].classList.add("active");
    }

    // Associa l'evento click a ogni pallino
    dots.forEach(dot => {
        dot.addEventListener("click", function() {
            const pageIndex = parseInt(this.getAttribute("data-page"));
            updateCarousel(pageIndex);
        });
    });
});
</script>
</body>

</html>
