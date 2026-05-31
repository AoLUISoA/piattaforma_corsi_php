<?php
// Avviamo la sessione per passare i dati dell'utente alla pagina successiva
session_start();

// Se l'utente è già loggato, lo mandiamo via
if (isset($_SESSION['ruolo'])) {
    header("Location: index.html");
    exit();
}

$errore = "";
$successo = "";

// LOGICA DI REGISTRAZIONE ALL'INVIO DEL FORM
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

    // Recuperiamo e puliamo i dati dal form
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $ruolo = $_POST['role']; // 'student' oppure 'teacher'

    // Scegliamo la tabella corretta in base al ruolo selezionato per i controlli sui duplicati
    $tabella = ($ruolo === 'student') ? 'studenti' : 'docenti';

    // 1. Verifichiamo se lo username o l'email sono già occupati
    $stmt_check = $pdo->prepare("SELECT id FROM $tabella WHERE username = :username OR email = :email");
    $stmt_check->execute([':username' => $username, ':email' => $email]);
    
    if ($stmt_check->fetch()) {
        $errore = "Username o Email già registrati per questo profilo.";
    } else {
        // 2. Criptiamo la password in modo sicuro (Standard professionale)
        $password_criptata = password_hash($password, PASSWORD_BCRYPT);

        try {
            // 3. Eseguiamo l'inserimento nella tabella corretta
            if ($ruolo === 'student') {
                $stmt_insert = $pdo->prepare("INSERT INTO studenti (username, email, password) VALUES (:username, :email, :password)");
                $stmt_insert->execute([':username' => $username, ':email' => $email, ':password' => $password_criptata]);
            } else {
                // Se è un docente, impostiamo valori di default per i campi professionali (modificabili dopo nella sua dashboard)
                $stmt_insert = $pdo->prepare("INSERT INTO docenti (username, email, password, specializzazione, biografia) VALUES (:username, :email, :password, 'Nuovo Docente', 'Nessuna biografia inserita.')");
                $stmt_insert->execute([':username' => $username, ':email' => $email, ':password' => $password_criptata]);
            }

            // 4. Recuperiamo l'ID dell'utente appena inserito nel DB
            $nuovo_id = $pdo->lastInsertId();

            // 5. Salviamo i dati in sessione così l'utente risulta già loggato
            $_SESSION['utente_id'] = $nuovo_id;
            $_SESSION['username'] = $username;
            $_SESSION['ruolo'] = $ruolo;

            // 6. REINDIRIZZAMENTO FLUIDO: Prosegue alla pagina di selezione corsi
            header("Location: selectcourse.php");
            exit();

        } catch (PDOException $e) {
            $errore = "Si è verificato un errore durante la registrazione. Riprova.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - Learnify</title>
    <link rel="preconnect" href="https://googleapis.com">
    <link rel="preconnect" href="https://gstatic.com" crossorigin>
    <link href="https://googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cloudflare.com">
    <!-- Sfrutta lo stesso identico foglio di stile login.css per coerenza visiva -->
    <link rel="stylesheet" href="registerstyle.css">
</head>
<body class="auth-body">

<div class="auth-container">
    <div class="auth-header">
        <a href="index.html" class="back-home"><i class="fa-solid fa-arrow-left"></i> Torna alla home</a>
        <h2>Crea Account</h2>
        <p>Unisciti alla piattaforma ed espandi le tue conoscenze.</p>
    </div>

    <!-- Alert di errore -->
    <?php if (!empty($errore)): ?>
        <div style="background-color: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; padding: 12px; border-radius: 8px; font-size: 0.88rem; text-align: center; margin-bottom: 20px; font-weight: 500;">
            <?php echo htmlspecialchars($errore); ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        
        <!-- Selettore Ruolo Visivo -->
        <label class="section-label">Registrati come</label>
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

        <!-- Campi Form -->
        <div class="input-group">
            <label for="username"><i class="fa-regular fa-user"></i> Scegli Username</label>
            <input type="text" id="username" name="username" placeholder="Es: luigi_verde" required>
        </div>

        <div class="input-group">
            <label for="email"><i class="fa-regular fa-envelope"></i> Indirizzo Email</label>
            <input type="email" id="email" name="email" placeholder="Es: nome@email.it" required>
        </div>

        <div class="input-group">
            <label for="password"><i class="fa-solid fa-lock"></i> Password di Sicurezza</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-block" style="margin-top: 15px;">
            Registrati e Continua <i class="fa-solid fa-arrow-right"></i>
        </button>
    </form>

    <div class="auth-footer">
        <p>Hai già un account? <a href="login.php">Accedi qui</a></p>
    </div>
</div>

</body>
</html>
