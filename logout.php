<?php
session_start();
session_unset(); // Rimuove tutte le variabili di sessione
session_destroy(); // Distrugge la sessione corrente
header("Location: index.html"); // Ti riporta al modulo di login pulito
exit();
?>