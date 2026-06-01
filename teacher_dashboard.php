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


// --- API AJAX (NUOVA): RECUPERO DETTAGLI CORSO E SLIDE IN FORMATO JSON ---
// Questa sezione deve intercettare la richiesta JavaScript PRIMA di generare qualsiasi HTML
if (isset($_GET['action']) && $_GET['action'] === 'get_course_details' && isset($_GET['corso_id'])) {
    header('Content-Type: application/json');
    
    $c_id = intval($_GET['corso_id']);
    
    // Recupera corso verificando la proprietà del docente per sicurezza
    $st1 = $pdo->prepare("SELECT * FROM corsi WHERE id = :id AND docente_id = :d_id");
    $st1->execute([':id' => $c_id, ':d_id' => $docente_id]);
    $corso = $st1->fetch(PDO::FETCH_ASSOC);
    
    if (!$corso) {
        echo json_encode(['errore' => 'Corso non trovato o non autorizzato.']); 
        exit();
    }
    
    // Recupera le slide associate ordinandole correttamente per numero di pagina
    $st2 = $pdo->prepare("SELECT titolo, contenuto FROM slide WHERE corso_id = :corso_id ORDER BY ordine ASC");
    $st2->execute([':corso_id' => $c_id]);
    $slides = $st2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['corso' => $corso, 'slide' => $slides]);
    exit(); // Blocca l'esecuzione per inviare solo il JSON pulito al JavaScript
}


// --- AZIONE 1: CREAZIONE DI UN NUOVO CORSO E DELLE SUE SLIDE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    $titolo = trim($_POST['titolo']);
    $categoria = $_POST['categoria'];
    $livello = $_POST['livello'];
    $descrizione = trim($_POST['descrizione']);
    $durata = intval($_POST['durata']);

    // Recupero degli array dinamici delle slide dal form
    $slide_titoli = isset($_POST['slide_titoli']) ? $_POST['slide_titoli'] : [];
    $slide_testi = isset($_POST['slide_testi']) ? $_POST['slide_testi'] : [];

    if (!empty($titolo) && !empty($descrizione) && $durata > 0 && !empty($slide_titoli)) {
        if (count($slide_titoli) > 10) {
            $messaggio = "Errore: Puoi inserire al massimo 10 slide.";
            $tipo_messaggio = "error";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Inserimento dei dati principali del corso
                $stmt_add = $pdo->prepare("INSERT INTO corsi (docente_id, titolo, categoria, livello, descrizione, durata) VALUES (:docente_id, :titolo, :categoria, :livello, :descrizione, :durata)");
                $stmt_add->execute([
                    ':docente_id' => $docente_id,
                    ':titolo' => $titolo,
                    ':categoria' => $categoria,
                    ':livello' => $livello,
                    ':descrizione' => $descrizione,
                    ':durata' => $durata
                ]);

                $corso_id = $pdo->lastInsertId();

                // 2. Inserimento ciclico delle slide
                $stmt_slide = $pdo->prepare("INSERT INTO slide (corso_id, ordine, titolo, contenuto) VALUES (:corso_id, :ordine, :titolo, :contenuto)");

                for ($i = 0; $i < count($slide_titoli); $i++) {
                    $ordine = $i + 1;
                    $t_slide = trim($slide_titoli[$i]);
                    $c_slide = trim($slide_testi[$i]);

                    if (empty($t_slide) && empty($c_slide)) {
                        continue;
                    }

                    $stmt_slide->execute([
                        ':corso_id' => $corso_id,
                        ':ordine' => $ordine,
                        ':titolo' => $t_slide,
                        ':contenuto' => $c_slide
                    ]);
                }

                $pdo->commit();
                $messaggio = "Nuovo corso e le relative slide creati con successo! 🚀";
                $tipo_messaggio = "success";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $messaggio = "Errore durante la creazione del corso: " . $e->getMessage();
                $tipo_messaggio = "error";
            }
        }
    } else {
        $messaggio = "Tutti i campi del modulo e almeno una slide sono obbligatori.";
        $tipo_messaggio = "warning";
    }
}


// --- AZIONE 2: RIMOZIONE DI UN CORSO ESISTENTE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_course' && isset($_GET['corso_id'])) {
    $corso_id = intval($_GET['corso_id']);
    
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM corsi WHERE id = :corso_id AND docente_id = :docente_id");
        $stmt_check->execute([':corso_id' => $corso_id, ':docente_id' => $docente_id]);
        
        if ($stmt_check->fetch()) {
            // Nota: ON DELETE CASCADE cancella in automatico anche le slide dal DB
            $stmt_del = $pdo->prepare("DELETE FROM corsi WHERE id = :corso_id");
            $stmt_del->execute([':corso_id' => $corso_id]);
            $messaggio = "Corso e relative slide rimossi definitivamente.";
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


// --- AZIONE 3 (NUOVA): SALVATAGGIO MODIFICHE CORSO E DELLE SUE SLIDE MODIFICATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_course') {
    $id_corso = intval($_POST['id_corso']);
    $titolo = trim($_POST['titolo']);
    $categoria = $_POST['categoria'];
    $livello = $_POST['livello'];
    $descrizione = trim($_POST['descrizione']);
    $durata = intval($_POST['durata']);
    
    $slide_titoli = isset($_POST['slide_titoli']) ? $_POST['slide_titoli'] : [];
    $slide_testi = isset($_POST['slide_testi']) ? $_POST['slide_testi'] : [];

    if (!empty($id_corso) && !empty($titolo) && !empty($descrizione) && $durata > 0 && !empty($slide_titoli)) {
        if (count($slide_titoli) > 10) {
            $messaggio = "Errore: Massimo 10 slide consentite."; 
            $tipo_messaggio = "error";
        } else {
            try {
                $pdo->beginTransaction();

                // Verifica sicurezza: il docente possiede davvero questo corso?
                $stmt_check = $pdo->prepare("SELECT id FROM corsi WHERE id = :id AND docente_id = :d_id");
                $stmt_check->execute([':id' => $id_corso, ':d_id' => $docente_id]);
                
                if ($stmt_check->fetch()) {
                    // 1. Aggiorna i dati testuali stabili nella tabella CORSI
                    $stmt_up = $pdo->prepare("UPDATE corsi SET titolo = :titolo, categoria = :categoria, livello = :livello, descrizione = :descrizione, durata = :durata WHERE id = :id");
                    $stmt_up->execute([
                        ':titolo' => $titolo, ':categoria' => $categoria, ':livello' => $livello,
                        ':descrizione' => $descrizione, ':durata' => $durata, ':id' => $id_corso
                    ]);

                    // 2. Cancella in blocco le vecchie slide per fare spazio alla nuova sequenza modificata
                    $stmt_del_slides = $pdo->prepare("DELETE FROM slide WHERE corso_id = :corso_id");
                    $stmt_del_slides->execute([':corso_id' => $id_corso]);

                    // 3. Inserisce nuovamente la lista di slide aggiornata
                    $stmt_ins_slide = $pdo->prepare("INSERT INTO slide (corso_id, ordine, titolo, contenido) VALUES (:corso_id, :ordine, :titolo, :contenuto)");
                    
                    // Nota: se la tua tabella usa la colonna "contenuto" (come da schema SQL), mantieni ':contenuto'
                    $stmt_ins_slide = $pdo->prepare("INSERT INTO slide (corso_id, ordine, titolo, contenuto) VALUES (:corso_id, :ordine, :titolo, :contenuto)");

                    for ($i = 0; $i < count($slide_titoli); $i++) {
                        $ordine = $i + 1;
                        $t_slide = trim($slide_titoli[$i]);
                        $c_slide = trim($slide_testi[$i]);

                        if (empty($t_slide) && empty($c_slide)) continue;

                        $stmt_ins_slide->execute([
                            ':corso_id' => $id_corso, 
                            ':ordine' => $ordine,
                            ':titolo' => $t_slide, 
                            ':contenuto' => $c_slide
                        ]);
                    }

                    $pdo->commit();
                    $messaggio = "Corso e slide modificati con successo! 💾"; 
                    $tipo_messaggio = "success";
                } else {
                    $pdo->rollBack(); 
                    $messaggio = "Azione non autorizzata."; 
                    $tipo_messaggio = "error";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $messaggio = "Errore durante l'aggiornamento del corso: " . $e->getMessage(); 
                $tipo_messaggio = "error";
            }
        }
    } else {
        $messaggio = "Tutti i campi sono obbligatori e serve almeno una slide."; 
        $tipo_messaggio = "warning";
    }
}
try {
    // Definizione della query SQL con una formattazione chiara e leggibile
    $query_corsi = "
        SELECT 
            corsi.*, 
            COUNT(iscrizioni_corsi.id) AS totale_alunni 
        FROM corsi 
        LEFT JOIN iscrizioni_corsi 
            ON corsi.id = iscrizioni_corsi.corso_id 
        WHERE corsi.docente_id = :docente_id 
        GROUP BY corsi.id 
        ORDER BY corsi.data_creazione DESC
    ";
    
    // Preparazione ed esecuzione della query
    $stmt_corsi = $pdo->prepare($query_corsi);
    $stmt_corsi->execute([
        ':docente_id' => $docente_id
    ]);
    
    // Estrazione dei dati e conteggio dei risultati
    $corsi_presieduti = $stmt_corsi->fetchAll(PDO::FETCH_ASSOC);
    $totale_corsi     = count($corsi_presieduti);

} catch (PDOException $e) {
    // Blocco di gestione degli errori in caso di fallimento del database
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
                        <h3 class="course-card-title-fixed"><?php echo htmlspecialchars($corso['titolo']); ?></h3>
                        <p class="course-description"><?php echo htmlspecialchars($corso['descrizione']); ?></p>
                        
                        <!-- INFO ALUNNI E META INFORMAZIONI -->
                        <div class="teacher-stats-info">
                            <span class="student-count">👨‍🎓 Alunni iscritti: <strong><?php echo $corso['totale_alunni']; ?></strong></span>
                            <span class="course-meta">⏱️ <?php echo $corso['durata']; ?> ore | Livello: <?php echo $corso['livello']; ?></span>
                        </div>
                    </div>
                    
                    <!-- MODIFICATO: Nuove classi per bottoni affiancati, moderni e fluidi -->
                    <div class="card-actions-teacher-grid">
                        <button type="button" class="btn-course btn-edit-teacher" onclick="apriModifica(<?php echo $corso['id']; ?>)">
                            📝 Modifica
                        </button>
                        <a href="teacher_dashboard.php?action=delete_course&corso_id=<?php echo $corso['id']; ?>" 
                           class="btn-course btn-delete-teacher" 
                           onclick="return confirm('Sei sicuro di voler eliminare definitivamente questo corso e tutte le iscrizioni degli studenti? L\'azione è irreversibile.');">
                            ❌ Rimuovi
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-dashboard-state">
                <p>Non hai ancora pubblicato nessun corso. Compila il modulo in basso per creare il tuo primo percorso formativo.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- FINESTRA MODALE PER LA MODIFICA -->
<div id="modal-modifica" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 20px 10px;">
    <div style="background: white; max-width: 700px; margin: 30px auto; padding: 25px; border-radius: 8px; position: relative;">
        <span onclick="chiudiModifica()" style="position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer;">&times;</span>
        <h3 style="margin-bottom: 20px;">Modifica Corso e Slide</h3>
        
        <form action="teacher_dashboard.php" method="POST">
            <input type="hidden" name="action" value="update_course">
            <input type="hidden" name="id_corso" id="mod-id-corso">

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Titolo del Corso</label>
                <input type="text" id="mod-titolo" name="titolo" style="width:100%; padding:8px; margin-top:5px;" required>
            </div>

            <div style="display:flex; gap:15px; margin-bottom: 15px;">
                <div style="flex:1;">
                    <label>Categoria</label>
                    <select id="mod-categoria" name="categoria" style="width:100%; padding:8px; margin-top:5px;" required>
                        <option value="tech">Tech</option>
                        <option value="business">Business</option>
                        <option value="design">Design</option>
                        <option value="data-ai">Data & AI</option>
                        <option value="finance">Finance</option>
                        <option value="photography">Photography</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label>Livello</label>
                    <select id="mod-livello" name="livello" style="width:100%; padding:8px; margin-top:5px;" required>
                        <option value="Base">Base</option>
                        <option value="Intermedio">Intermedio</option>
                        <option value="Avanzato">Avanzato</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:15px; margin-bottom: 15px;">
                <div style="flex:1;">
                    <label>Durata (ore)</label>
                    <input type="number" id="mod-durata" name="durata" min="1" style="width:100%; padding:8px; margin-top:5px;" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Descrizione</label>
                <textarea id="mod-descrizione" name="descrizione" rows="3" style="width:100%; padding:8px; margin-top:5px;" required></textarea>
            </div>

            <!-- Contenitore Slide Dinamiche in Modifica -->
            <div style="margin-top: 25px; border-top: 2px dashed #ccc; padding-top: 15px;">
                <h4>Slide del Corso (Max 10)</h4>
                <div id="mod-slides-container"></div>
                
                <div style="margin: 15px 0; display: flex; gap: 10px;">
                    <button type="button" id="mod-btn-add" style="background:#28a745; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;"><b>➕ Aggiungi Slide</button>
                    <button type="button" id="mod-btn-remove" style="background:#dc3545; color:white; border:none; padding:8px 12px; border-radius:4px; cursor:pointer;"><b>➖ Rimuovi Ultima</button>
                </div>
            </div>

            <button type="submit" style="width:100%; background:#007bff; color:white; border:none; padding:12px; font-size:16px; border-radius:5px; cursor:pointer; margin-top:15px;">
                <b>Salva Modifiche 
            </button>
        </form>
    </div>
</div>

    <!-- SEZIONE 2: CREAZIONE NUOVO CORSO -->
    <section class="course-creation-section">
    <h2 class="section-title">Crea e pubblica un nuovo corso</h2>
    <p class="section-subtitle">Inserisci i dettagli e crea le tue slide (massimo 10) per distribuire la tua materia.</p>

    <form class="creation-form" action="teacher_dashboard.php" method="POST">
        <input type="hidden" name="action" value="create_course">

        <!-- Dati Generali del Corso -->
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
            <textarea id="descrizione" name="descrizione" rows="3" placeholder="Descrivi brevemente gli argomenti trattati..." required></textarea>
        </div>

        <!-- Sezione Gestione Slide Dinamiche -->
        <div class="slides-section" style="margin-top: 30px; border-top: 2px dashed #ccc; padding-top: 20px;">
            <h3>Contenuto delle Slide (Max 10)</h3>
            
            <div id="slides-container">
                <!-- Prima slide obbligatoria -->
                <div class="slide-group" id="slide-box-1" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h4>Slide 1</h4>
                    <div class="form-group">
                        <label>Titolo Slide</label>
                        <input type="text" name="slide_titoli[]" placeholder="Titolo della slide 1" required>
                    </div>
                    <div class="form-group" style="margin-top: 10px;">
                        <label>Testo Contenuto</label>
                        <textarea name="slide_testi[]" rows="4" placeholder="Contenuto testuale della slide 1..." required></textarea>
                    </div>
                </div>
            </div>

            <!-- Controlli per il docente -->
            <div class="slide-controls" style="margin-bottom: 20px; display: flex; gap: 10px;">
                <button type="button" id="btn-add-slide" style="background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">
                    <b>➕ Aggiungi Slide
                </button>
                <button type="button" id="btn-remove-slide" style="background-color: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">
                    <b>➖ Rimuovi Ultima Slide
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit-form" style="width: 100%;">
            Pubblica Corso e Slide nel Catalogo 🚀
        </button>
    </form>
</section>
</main>

<footer>
    <p>&copy; 2026 Learnify. Tutti i diritti riservati.</p>
</footer>

<script>
// 1. FUNZIONI GLOBALI (Devono stare FUORI da DOMContentLoaded per essere lette dagli attributi onclick="...")
function apriModifica(corsoId) {
    fetch('teacher_dashboard.php?action=get_course_details&corso_id=' + corsoId)
        .then(response => response.json())
        .then(data => {
            if(data.errore) {
                alert(data.errore);
                return;
            }
            
            // Popola i campi della finestra modale
            document.getElementById('mod-id-corso').value = data.corso.id;
            document.getElementById('mod-titolo').value = data.corso.titolo;
            document.getElementById('mod-categoria').value = data.corso.categoria;
            document.getElementById('mod-livello').value = data.corso.livello;
            document.getElementById('mod-durata').value = data.corso.durata;
            document.getElementById('mod-descrizione').value = data.corso.descrizione;
            
            // Rigenera i blocchi delle slide salvate
            const container = document.getElementById('mod-slides-container');
            container.innerHTML = '';
            
            data.slide.forEach((s, index) => {
                const n = index + 1;
                container.insertAdjacentHTML('beforeend', generaHtmlSlideModifica(n, s.titolo, s.contenuto));
            });
            
            // Mostra la finestra modale
            document.getElementById('modal-modifica').style.display = 'block';
        })
        .catch(err => alert("Errore nel recupero dei dati del corso."));
}

function chiudiModifica() {
    document.getElementById('modal-modifica').style.display = 'none';
}

function generaHtmlSlideModifica(index, titolo = '', contenuto = '') {
    // Sanatizzazione minima per evitare che le virgolette rompano l'attributo value
    const titoloSanificato = titolo.replace(/"/g, '&quot;');
    return `
        <div class="mod-slide-group" id="mod-slide-box-${index}" style="margin-bottom:15px; padding:12px; border:1px solid #ddd; border-radius:4px; background:#f9f9f9;">
            <h5>Slide ${index}</h5>
            <div class="form-group" style="margin-bottom: 8px;">
                <label>Titolo Slide</label>
                <input type="text" name="slide_titoli[]" value="${titoloSanificato}" placeholder="Titolo slide" style="width:100%; padding:6px;" required>
            </div>
            <div class="form-group">
                <label>Contenuto Slide</label>
                <textarea name="slide_testi[]" rows="3" placeholder="Contenuto slide" style="width:100%; padding:6px;" required>${contenuto}</textarea>
            </div>
        </div>
    `;
}


// 2. GESTIONE EVENTI SUI BOTTONI (Viene attivata solo quando la pagina è pronta)
document.addEventListener('DOMContentLoaded', function() {
    const maxSlides = 10;

    // --- CONTROLLI SLIDE DENTRO LA MODALE DI MODIFICA ---
    document.getElementById('mod-btn-add').addEventListener('click', function() {
        const container = document.getElementById('mod-slides-container');
        const current = container.getElementsByClassName('mod-slide-group').length;
        if (current >= maxSlides) { return alert('Massimo 10 slide!'); }
        container.insertAdjacentHTML('beforeend', generaHtmlSlideModifica(current + 1));
    });

    document.getElementById('mod-btn-remove').addEventListener('click', function() {
        const container = document.getElementById('mod-slides-container');
        const current = container.getElementsByClassName('mod-slide-group').length;
        if (current <= 1) { return alert('Almeno una slide richiesta!'); }
        const lastSlide = document.getElementById(`mod-slide-box-${current}`);
        if (lastSlide) lastSlide.remove();
    });

    // --- CONTROLLI SLIDE DENTRO IL FORM DI CREAZIONE NUOVO CORSO ---
    const containerCreazione = document.getElementById('slides-container');
    const addBtnCreazione = document.getElementById('btn-add-slide');
    const removeBtnCreazione = document.getElementById('btn-remove-slide');

    addBtnCreazione.addEventListener('click', function() {
        const currentSlides = containerCreazione.getElementsByClassName('slide-group').length;
        if (currentSlides >= maxSlides) {
            alert('Hai raggiunto il limite massimo di 10 slide!');
            return;
        }
        const nextIndex = currentSlides + 1;
        const slideHtml = `
            <div class="slide-group" id="slide-box-${nextIndex}" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background:#fafafa;">
                <h4>Slide ${nextIndex}</h4>
                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Titolo Slide</label>
                    <input type="text" name="slide_titoli[]" placeholder="Titolo della slide ${nextIndex}" style="width:100%; padding:6px;" required>
                </div>
                <div class="form-group">
                    <label>Testo Contenuto</label>
                    <textarea name="slide_testi[]" rows="4" placeholder="Contenuto testuale della slide ${nextIndex}..." style="width:100%; padding:6px;" required></textarea>
                </div>
            </div>
        `;
        containerCreazione.insertAdjacentHTML('beforeend', slideHtml);
    });

    removeBtnCreazione.addEventListener('click', function() {
        const currentSlides = containerCreazione.getElementsByClassName('slide-group').length;
        if (currentSlides <= 1) {
            alert('Il corso deve contenere almeno una slide!');
            return;
        }
        const lastSlide = document.getElementById(`slide-box-${currentSlides}`);
        if (lastSlide) lastSlide.remove();
    });
});
</script>

</body>
</html>