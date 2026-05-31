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
    
    if (isset($_POST['corsi_selezionati']) && !empty($_POST['corsi_selezionati'])) {
        $corsi_scelti = $_POST['corsi_selezionati']; // Array contenente gli ID dei corsi selezionati

        // CONTROLLO DI SICUREZZA LATO SERVER: Massimo 3 corsi consentiti
        if (count($corsi_scelti) > 3) {
            $messaggio = "Errore di sicurezza: Non puoi iscriverti a più di 3 corsi contemporaneamente.";
            $tipo_messaggio = "error";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO iscrizioni_corsi (studente_id, corso_id, progresso) VALUES (:studente_id, :corso_id, 0)");
                
                foreach ($corsi_scelti as $corso_id) {
                    $stmt->execute([
                        ':studente_id' => $studente_id,
                        ':corso_id' => intval($corso_id)
                    ]);
                }
                
                header("Location: student_dashboard.php");
                exit();
            } catch (PDOException $e) {
                $messaggio = "Si è verificato un errore durante l'iscrizione nel database.";
                $tipo_messaggio = "error";
            }
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

// LOGICA DI SUDDIVISIONE DINAMICA: Max 4 corsi per blocco pagina
$corsi_per_pagina = 4;
$pagine_corsi = array_chunk($elenco_corsi, $corsi_per_pagina);
$totale_pagine = count($pagine_corsi);
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
    <link rel="stylesheet" href="courseselection.css">
</head>
<body class="selection-body">

<div class="selection-container">
    <div class="selection-header">
        <h2>Registrazione completata! 🎉</h2>
        <p>Personalizza la tua esperienza su <strong>Learnify</strong>. Seleziona i percorsi di studio che desideri frequentare (<strong>Massimo 3 corsi</strong>).</p>
        
        <!-- FUNZIONALITÀ EXTRA: Contatore dinamico a schermo per l'utente -->
        <div class="selection-counter" id="selectionCounter">Corsi selezionati: <span id="countNum">0</span> / 3</div>
    </div>

    <!-- Alert per eventuali messaggi di errore o avviso -->
    <?php if (!empty($messaggio)): ?>
        <div class="alert alert-<?php echo $tipo_messaggio; ?>">
            <?php echo htmlspecialchars($messaggio); ?>
        </div>
    <?php endif; ?>

    <!-- Contenitore per notifica JavaScript istantanea -->
    <div id="jsAlert" class="alert alert-warning" style="display: none;"></div>

    <form action="selectcourse.php" method="POST" id="courseForm">
        
        <!-- Binario scorrevole per l'impaginazione a blocchi di 4 -->
        <div class="selection-carousel-wrapper">
            <div class="selection-carousel-track" id="selectionTrack">
                
                <?php if ($totale_pagine > 0): ?>
                    <?php foreach ($pagine_corsi as $indice_pagina => $gruppo_corsi): ?>
                        
                        <!-- PAGINA CON MAX 4 CORSI -->
                        <div class="selection-carousel-page">
                            <div class="courses-list-grid">
                                <?php foreach ($gruppo_corsi as $corso): ?>
                                    <!-- Card interattiva con checkbox nascosta -->
                                    <label class="course-selectable-option">
                                        <input type="checkbox" name="corsi_selezionati[]" value="<?php echo $corso['id']; ?>" class="course-checkbox">
                                        <div class="course-selection-card">
                                            <div class="card-top-info">
                                                <span class="badge-tag tag-<?php echo strtolower(htmlspecialchars($corso['categoria'])); ?>">
                                                    <?php echo htmlspecialchars(str_replace('-', ' & ', ucwords($corso['categoria']))); ?>
                                                </span>
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
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-db-state">
                        <p>Al momento non ci sono corsi caricati nel database della piattaforma.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- PUNTINI DI NAVIGAZIONE DINAMICI (Generati in base alle pagine da 4 corsi) -->
        <?php if ($totale_pagine > 1): ?>
            <div class="selection-carousel-dots" id="selectionDots">
                <?php for ($i = 0; $i < $totale_pagine; $i++): ?>
                    <span class="select-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"></span>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit-selection" style="margin-top: 25px;">
            Conferma Scelte e Vai alla Dashboard 🚀
        </button>
    </form>
</div>

<!-- SCRIPT LOGICO: GESTISCE BLOCCO A 3 CORSI E SCORRIMENTO PUNTINI -->
<script>
    // --- ENGINE LOGICO COMPLETO: SELEZIONE CORSI ---

const track = document.getElementById('selectionTrack');
const dots = document.querySelectorAll('.select-dot');
const checkboxes = document.querySelectorAll('.course-checkbox');
const countNum = document.getElementById('countNum');
const jsAlert = document.getElementById('jsAlert');
const counterBox = document.getElementById('selectionCounter');

const maxSelection = 3;

// --- FUNZIONALITÀ 1: GESTIONE E BLOCCO A MASSIMO 3 CORSI ---
checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', () => {
        // Calcola quante caselle sono state effettivamente spuntate
        const currentChecked = document.querySelectorAll('.course-checkbox:not(:checked)').length;
        const checkedCount = checkboxes.length - currentChecked;

        // Se lo studente tenta di selezionare un 4° corso
        if (checkedCount > maxSelection) {
            checkbox.checked = false; // Rifiuta l'azione e rimuove la spunta appena messa
            
            // Mostra il banner di avviso a schermo
            jsAlert.innerText = "⚠️ Non puoi selezionare più di 3 corsi contemporaneamente.";
            jsAlert.style.display = "block";
            
            // Dissolve l'avviso automaticamente dopo 3 secondi
            setTimeout(() => { 
                jsAlert.style.display = "none"; 
            }, 3000);
        } else {
            // Se la selezione è valida, aggiorna il numeretto visivo nel badge
            countNum.innerText = checkedCount;
            
            // Attiva il colore di accento se viene raggiunto il limite massimo di 3
            if (checkedCount === maxSelection) {
                counterBox.classList.add('counter-max');
            } else {
                counterBox.classList.remove('counter-max');
            }
        }
    });
});

// --- FUNZIONALITÀ 2: SCORRIMENTO PAGINE TRAMITE PUNTINI INDICATORI ---
function updateSelectionPage(pageIndex) {
    const firstPage = track.querySelector('.selection-carousel-page');
    if (!firstPage) return;
    
    // Calcola la larghezza esatta di una pagina per uno spostamento millimetrico
    const pageWidth = firstPage.getBoundingClientRect().width;
    track.style.transform = `translateX(-${pageIndex * pageWidth}px)`;
    
    // Aggiorna lo stato visivo (allungamento a pillola) del pallino attivo
    dots.forEach(dot => dot.classList.remove('active'));
    if (dots[pageIndex]) dots[pageIndex].classList.add('active');
}

// Collega l'evento di clic a ciascun pallino generato dal database
dots.forEach(dot => {
    dot.addEventListener('click', (e) => {
        const targetPage = parseInt(e.target.getAttribute('data-page'), 10);
        updateSelectionPage(targetPage);
    });
});

// Ricalcola la posizione geometrica se l'utente ridimensiona la finestra del browser
window.addEventListener('resize', () => {
    const activeDot = document.querySelector('.select-dot.active');
    if (activeDot) {
        const currentPage = parseInt(activeDot.getAttribute('data-page'), 10);
        updateSelectionPage(currentPage);
    }
});
</script>
</body>
</html>
        
