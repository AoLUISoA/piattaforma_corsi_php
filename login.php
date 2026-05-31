<?php
// Avviamo la sessione per memorizzare lo stato dell'utente loggato
session_start();

// Se l'utente è già loggato, lo reindirizziamo direttamente alla sua dashboard
if (isset($_SESSION['ruolo'])) {
    if ($_SESSION['ruolo'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: teacher_dashboard.php");
    }
    exit();
}

$errore = "";

// LOGICA DI AUTENTICAZIONE ALL'INVIO DEL FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configurazione parametri XAMPP in locale
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

    // Recuperiamo e puliamo i dati inseriti dall'utente
    $username_inserito = trim($_POST['username']);
    $password_inserita = trim($_POST['password']);
    $ruolo_selezionato = $_POST['role']; // 'student' oppure 'teacher'

    // 1. Eseguiamo la query sulla tabella corretta in base al ruolo selezionato
    if ($ruolo_selezionato === 'student') {
        $stmt = $pdo->prepare("SELECT id, username, password FROM studenti WHERE username = :username");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM docenti WHERE username = :username");
    }

    $stmt->execute([':username' => $username_inserito]);
    $utente = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. CONTROLLO RIGOROSO DELLA PASSWORD
    if ($utente) {
        // Controlliamo se corrisponde con la cifratura password_verify() (Utenti registrati dal sito)
        // OPPURE se corrisponde come testo semplice (Per i docenti di test inseriti a mano nel DB)
        if (password_verify($password_inserita, $utente['password']) || $password_inserita === $utente['password']) {
            
            // Credenziali corrette: Salviamo i dati fondamentali nella sessione del browser
            $_SESSION['utente_id'] = $utente['id'];
            $_SESSION['username'] = $utente['username'];
            $_SESSION['ruolo'] = $ruolo_selezionato;

            // Reindirizzamento differenziato in base al ruolo verificato
            if ($ruolo_selezionato === 'student') {
                header("Location: student_dashboard.php");
            } else {
                header("Location: teacher_dashboard.php");
            }
            exit();
        } else {
            $errore = "Password errata. Riprova.";
        }
    } else {
        $errore = "Username non trovato per il profilo selezionato.";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cloudflare.com">
    <!-- Usiamo il file CSS dedicato che abbiamo isolato prima -->
    <link rel="stylesheet" href="loginstyle.css">
</head>
<body class="auth-body">

<div class="auth-container">
    <div class="auth-header">
        <a href="index.php" class="back-home"><i class="fa-solid fa-arrow-left"></i> Torna alla home</a>
        <h2>Bentornato!</h2>
        <p>Seleziona il tuo profilo e inserisci le tue credenziali.</p>
    </div>

    <!-- Feedback visivo in caso di errore di login -->
    <?php if (!empty($errore)): ?>
        <div style="background-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; padding: 12px; border-radius: 8px; font-size: 0.88rem; text-align: center; margin-bottom: 20px; font-weight: 500;">
            <?php echo htmlspecialchars($errore); ?>
        </div>
    <?php endif; ?>

    <!-- Form aggiornato con i parametri name e il metodo POST per PHP -->
    <form action="login.php" method="POST">
        
        <!-- Selettore di Ruolo Visivo -->
        <label class="section-label">Chi sei?</label>
        <div class="role-selector">
            <label class="role-option">
                <input type="radio" name="role" value="student" checked>
                <div class="role-card">
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Allievo</span>
                </div>
            </label>

            <label class="role-option">
                <input type="radio" name="role" value="teacher">
                <div class="role-card">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Docente</span>
                </div>
            </label>
        </div>

        <!-- Campi di Input -->
        <div class="input-group">
            <label for="username"><i class="fa-regular fa-user"></i> Username</label>
            <input type="text" id="username" name="username" placeholder="Inserisci il tuo username" required>
        </div>

        <div class="input-group">
            <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="form-actions">
            <a href="#" class="forgot-password">Password dimenticata?</a>
        </div>

        <button type="submit" class="btn btn-block">
            Accedi <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </button>
    </form>

    <div class="auth-footer">
        <p>Non hai un account? <a href="register.php">Registrati ora</a></p>
    </div>
</div>

</body>
</html>
