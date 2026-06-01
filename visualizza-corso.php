<?php
session_start();

// 1. Controllo di sicurezza: accesso consentito solo agli studenti loggati
if (!isset($_SESSION['utente_id']) || $_SESSION['ruolo'] !== 'student') { 
    header("Location: login.php");
    exit();
}

// PARAMETRI DI CONNESSIONE AL DATABASE
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

// --- API AJAX LATO SERVER: AGGIORNAMENTO PROGRESSO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    header('Content-Type: application/json');
    $corso_id = intval($_POST['corso_id']);
    $progresso = intval($_POST['progresso']);

    try {
        $stmt_up = $pdo->prepare("UPDATE iscrizioni_corsi SET progresso = :progresso WHERE studente_id = :studente_id AND corso_id = :corso_id");
        $stmt_up->execute([
            ':progresso' => $progresso,
            ':studente_id' => $studente_id,
            ':corso_id' => $corso_id
        ]);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// --- LOGICA DI CARICAMENTO PAGINA ---
if (!isset($_GET['corso_id'])) {
    die("Errore: Corso non specificato.");
}

$corso_id = intval($_GET['corso_id']);

try {
    // 2. Verifica che lo studente sia realmente iscritto al corso e recupera i dati del corso
    $stmt_corso = $pdo->prepare("
        SELECT corsi.*, iscrizioni_corsi.progresso 
        FROM corsi 
        INNER JOIN iscrizioni_corsi ON corsi.id = iscrizioni_corsi.corso_id 
        WHERE iscrizioni_corsi.studente_id = :studente_id AND corsi.id = :corso_id
    ");
    $stmt_corso->execute([':studente_id' => $studente_id, ':corso_id' => $corso_id]);
    $dati_corso = $stmt_corso->fetch(PDO::FETCH_ASSOC);

    if (!$dati_corso) {
        die("Errore: Non sei iscritto a questo corso o il corso non esiste.");
    }

    // 3. Recupera tutte le slide del corso ordinate in sequenza corretta
    $stmt_slides = $pdo->prepare("SELECT * FROM slide WHERE corso_id = :corso_id ORDER BY ordine ASC");
    $stmt_slides->execute([':corso_id' => $corso_id]);
    $slides = $stmt_slides->fetchAll(PDO::FETCH_ASSOC);
    $totale_slides = count($slides);

    if ($totale_slides === 0) {
        die("Questo corso non contiene ancora nessuna slide. Torna più tardi!");
    }

    // 4. Determina da quale slide far partire lo studente in base al progresso salvato
    $index_partenza = 0;
    if ($dati_corso['progresso'] > 0) {
        $slide_salvata = round(($dati_corso['progresso'] / 100) * $totale_slides);
        $index_partenza = max(0, $slide_salvata - 1);
    }

} catch (PDOException $e) {
    die("Errore nel caricamento dei dati del corso: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($dati_corso['titolo']); ?> - Area di Studio</title>
    <style>
    /* --- REGISTRAZIONE FONT AD ALTA LEGGIBILITÀ --- */
@import url('https://googleapis.com');

/* --- STRUTTURA DI BASE E MOVIMENTO SFONDO --- */
:root {
    --fluid-gradient: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 50%, #fae8ff 100%);
    --shadow-premium: 0 30px 60px -15px rgba(15, 23, 42, 0.08), 0 15px 20px -10px rgba(15, 23, 42, 0.03);
    --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
    --font-serif: 'Lora', Georgia, serif; /* Font letterario ottimizzato per testi lunghi e spiegazioni */
}

body {
    background: var(--fluid-gradient);
    background-size: 200% 200%;
    animation: gradientMovement 15s ease infinite; /* Lo sfondo si muove leggermente nel tempo */
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: var(--font-sans);
    margin: 0;
    padding: 20px;
    box-sizing: border-box;
}

@keyframes gradientMovement {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.study-container {
    width: 100%;
    max-width: 840px;
    margin: 20px auto;
}

/* --- PULSANTE DI RITORNO FLUIDO --- */
.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: var(--text-muted, #64748b);
    font-weight: 700;
    font-size: 0.88rem;
    text-decoration: none;
    margin-bottom: 25px;
    padding: 10px 20px;
    border-radius: 30px;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.btn-back:hover {
    color: var(--primary, #6366f1);
    background: #ffffff;
    transform: translateX(-6px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
}

/* --- BARRA DI AVANZAMENTO IN STILE GLASSMORPHISM --- */
.progress-wrapper {
    background: rgba(255, 255, 255, 0.45);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    padding: 22px 28px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.6);
    margin-bottom: 30px;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.03);
}

.progress-text-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    font-size: 0.92rem;
    margin-bottom: 14px;
}

#course-title-label {
    color: var(--text-dark, #0f172a);
    letter-spacing: -0.02em;
    font-size: 1.05rem;
}

#progress-percentage-label {
    color: #ffffff;
    background: linear-gradient(135deg, var(--primary, #6366f1) 0%, #4f46e5 100%);
    padding: 5px 14px;
    border-radius: 9999px;
    font-size: 0.78rem;
    font-weight: 800;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}

.progress-bar-bg {
    width: 100%;
    height: 12px;
    background-color: rgba(15, 23, 42, 0.04);
    border-radius: 9999px;
    padding: 2px;
    box-sizing: border-box;
}

.progress-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #6366f1 0%, #a855f7 50%, #ec4899 100%); /* Sfumatura cromatica premium */
    border-radius: 9999px;
    box-shadow: 0 0 15px rgba(168, 85, 247, 0.4);
    transition: width 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.1); /* Transizione magnetica ultra-ammortizzata */
}

/* --- LA CARD CINEMATICA E RILASSANTE PER IL TESTO --- */
.slide-card {
    background: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    padding: 50px 60px;
    box-shadow: var(--shadow-premium);
    min-height: 380px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), box-shadow 0.4s cubic-bezier(0.25, 1, 0.5, 1);
}

.slide-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 40px 80px -20px rgba(15, 23, 42, 0.12);
}

/* --- TRATTAMENTO AVANZATO DELLA TIPOGRAFIA --- */
.slide-header {
    display: flex;
    flex-direction: column;
    gap: 12px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    padding-bottom: 24px;
}

.slide-counter {
    font-size: 0.72rem;
    color: #4f46e5;
    background: #f0eefc;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    padding: 6px 14px;
    border-radius: 8px;
    width: fit-content;
}

#slide-title-display {
    margin: 0;
    font-family: var(--font-sans);
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.04em;
    color: #0f172a;
    line-height: 1.15;
}

/* Ottimizzazione del blocco di testo principale per evitare l'affaticamento degli occhi */
.slide-body {
    font-family: var(--font-serif); /* Cambiato a Serif letterario per elevare la qualità di lettura */
    font-size: 1.22rem; /* Leggermente più grande per una scansione visiva immediata */
    line-height: 1.85; /* Spaziatura generosa tra le righe anti-intreccio */
    color: #1e293b; /* Grigio antracite profondo, meno aggressivo del nero assoluto sul bianco */
    margin: 40px 0;
    white-space: pre-line;
    font-weight: 400;
    letter-spacing: 0.01em;
}

/* Enfasi elegante per eventuali parole importanti inserite dai docenti */
.slide-body strong {
    color: #4f46e5;
    font-family: var(--font-sans);
    font-weight: 700;
    padding: 0 4px;
}

/* --- PULSANTI DI NAVIGAZIONE DESIGN REATTIVI --- */
.navigation-actions {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}

.btn-nav {
    flex: 1;
    padding: 16px 32px;
    border-radius: 14px;
    font-family: var(--font-sans);
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    box-sizing: border-box;
    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: none;
}

/* Tasto Precedente (Monocromatico Tecnico) */
.btn-prev {
    background: #f1f5f9;
    color: #334155;
}

.btn-prev:hover:not(:disabled) {
    background: #e2e8f0;
    color: #0f172a;
    transform: translateX(-3px);
}

/* Tasto Avanti / Completa (Effetto olografico scuro/luminoso) */
.btn-next {
    background: #0f172a; /* Nero profondo elegante in contrasto con le sfumature */
    color: #ffffff;
    box-shadow: 0 10px 20px -5px rgba(15, 23, 42, 0.3);
}

.btn-next:hover:not(:disabled) {
    background: #4f46e5; /* Diventa viola brillante al passaggio del mouse */
    transform: translateX(3px);
    box-shadow: 0 12px 24px -5px rgba(79, 70, 229, 0.4);
}

/* Stato disabilitato coerente */
.btn-nav:disabled {
    opacity: 0.35;
    background: #e2e8f0 !important;
    color: #94a3b8 !important;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

/* --- EFFETTO DI INGRESSO CINEMATICO --- */
@keyframes premiumEntrance {
    0% {
        opacity: 0;
        transform: translateY(25px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.slide-card {
    animation: premiumEntrance 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

/* --- OTTIMIZZAZIONE MOBILE SOTTILE --- */
@media (max-width: 650px) {
    .slide-card {
        padding: 30px 25px;
        min-height: 320px;
    }
    #slide-title-display {
        font-size: 1.5rem;
    }
    .slide-body {
        font-size: 1.08rem;
        margin: 30px 0;
        line-height: 1.75;
    }
    .btn-nav {
        padding: 14px 20px;
        font-size: 0.9rem;
        gap: 6px;
    }
}

    </style>
</head>
<body>

<h1 id="slide-title-display" style="padding:10px"><?php echo htmlspecialchars($corso['titolo']); ?></h1>
<!-- Aggiungi gli attributi data-slides, data-corso-id e data-partenza al tag esistente -->
<article class="slide-card" 
         id="slide-card-player"
         data-slides="<?php echo htmlspecialchars(json_encode($slides), ENT_QUOTES, 'UTF-8'); ?>"
         data-corso-id="<?php echo $corso_id; ?>"
         data-partenza="<?php echo $index_partenza; ?>">
    <div>
        <div class="slide-header">
            <span id="slide-number-indicator" class="slide-counter">Slide 1 di 1</span>
            <h2 id="slide-title-display">Titolo Slide</h2>
        </div>
        <div id="slide-body-display" class="slide-body">
            Contenuto testuale...
        </div>
    </div>

    <!-- Bottoni Avanti e Indietro -->
    <div class="navigation-actions">
        <button type="button" id="btn-prev" class="btn-nav btn-prev">⬅️ Precedente</button>
        <button type="button" id="btn-next" class="btn-nav btn-next">Prossima Slide ➡️</button>
    </div>
</article>

<!-- Includi il file JavaScript esterno subito dopo -->
<script src="studio_corsi.js"></script>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Iniezione diretta delle variabili PHP all'interno di JavaScript
    const slides = <?php echo json_encode($slides); ?>;
    const corsoId = <?php echo $corso_id; ?>;
    const totaleSlides = slides.length;
    
    // Recupero dell'indice della slide da cui ripartire (calcolato da PHP)
    let currentIndex = <?php echo $index_partenza; ?>;

    // Mappatura degli elementi grafici dell'interfaccia (DOM)
    const numIndicator = document.getElementById('slide-number-indicator');
    const titleDisplay = document.getElementById('slide-title-display');
    const bodyDisplay = document.getElementById('slide-body-display');
    const progressBar = document.getElementById('progress-bar');
    const percentageLabel = document.getElementById('progress-percentage-label');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');

    // Funzione per aggiornare i testi e la barra a schermo
    function renderSlide() {
        const currentSlide = slides[currentIndex];

        // Aggiornamento dei testi della card
        if (numIndicator) numIndicator.textContent = `Slide ${currentIndex + 1} di ${totaleSlides}`;
        if (titleDisplay) titleDisplay.textContent = currentSlide.titolo;
        if (bodyDisplay) bodyDisplay.textContent = currentSlide.contenuto;

        // Calcolo matematico della percentuale di avanzamento attuale
        let percentualeProgresso = Math.round(((currentIndex + 1) / totaleSlides) * 100);
        
        // Aggiornamento grafico della barra e della label testuale
        if (progressBar) progressBar.style.width = `${percentualeProgresso}%`;
        if (percentageLabel) percentageLabel.textContent = `${percentualeProgresso}% Completato`;

        // Gestione dell'attivazione dei pulsanti
        if (btnPrev) btnPrev.disabled = (currentIndex === 0);
        
        if (btnNext) {
            if (currentIndex === totaleSlides - 1) {
                btnNext.textContent = "🏁 Completa Corso";
				btnNext.style.backgroundColor = "#4CAF50";
            } else {
                btnNext.textContent = "Prossima Slide ➡️";
				btnNext.style.backgroundColor = "#481570";
            }
        }

        // Salva il progresso solo se non siamo all'ultima slide (l'ultima viene gestita dal tasto Completa Corso)
        if (currentIndex < totaleSlides - 1) {
            salvaProgressoNelDatabase(percentualeProgresso, false);
        }
    }

    // NUOVA FUNZIONE: Invia i dati al database e gestisce l'alert singolo alla fine
    function salvaProgressoNelDatabase(progressoCalcolato, isDefinitivo = false) {
        const formData = new FormData();
        formData.append('action', 'update_progress');
        formData.append('corso_id', corsoId);
        formData.append('progresso', progressoCalcolato);

        fetch('visualizza-corso.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'error') {
                console.error("Errore salvataggio progresso nel DB:", data.message);
                return;
            }
            
            // Se è il salvataggio finale del 100%, mostra l'alert una sola volta e reindirizza
            if (isDefinitivo) {
                alert("Complimenti! Hai completato tutte le slide di questo corso. Il tuo progresso è stato aggiornato al 100%! 🎉");
                window.location.href = 'student_dashboard.php';
            }
        })
        .catch(err => console.error("Errore di rete nell'aggiornamento avanzamento:", err));
    }

    // NUOVA GESTIONE PULSANTE AVANTI / COMPLETA
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            if (currentIndex < totaleSlides - 1) {
                currentIndex++;
                renderSlide();
            } else {
                // Passiamo true per attivare il reindirizzamento e l'alert singolo al termine del fetch
                salvaProgressoNelDatabase(100, true);
            }
        });
    }

    if (btnPrev) {
        btnPrev.addEventListener('click', function() {
            if (currentIndex > 0) {
                currentIndex--;
                renderSlide();
            }
        });
    }

    // Esecuzione iniziale al caricamento della pagina
    renderSlide();
});
</script>

</html>