document.addEventListener('DOMContentLoaded', function() {
    // 1. Recupero del player e dei dati dinamici passati dall'HTML tramite gli attributi data-
    const player = document.getElementById('slide-card-player');
    if (!player) return; // Blocca lo script se non siamo nella pagina di studio

    // Conversione della stringa JSON stampata da PHP in un array utilizzabile
    const slides = JSON.parse(player.getAttribute('data-slides'));
    const corsoId = parseInt(player.getAttribute('data-corso-id'), 10);
    const totaleSlides = slides.length;
    
    // Recupero dell'indice della slide da cui ripartire
    let currentIndex = parseInt(player.getAttribute('data-partenza'), 10);

    // 2. Mappatura degli elementi grafici dell'interfaccia (DOM)
    const numIndicator = document.getElementById('slide-number-indicator');
    const titleDisplay = document.getElementById('slide-title-display');
    const bodyDisplay = document.getElementById('slide-body-display');
    const progressBar = document.getElementById('progress-bar');
    const percentageLabel = document.getElementById('progress-percentage-label');
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');

    // 3. Funzione per aggiornare i testi e la barra a schermo
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
            } else {
                btnNext.textContent = "Prossima Slide ➡️";
            }
        }

        // Salvataggio asincrono (AJAX) nel database ad ogni cambio slide
        salvaProgressoNelDatabase(percentualeProgresso);
    }

    // 4. Funzione AJAX Fetch per salvare i dati in background nel database
    function salvaProgressoNelDatabase(progressoCalcolato) {
        const formData = new FormData();
        formData.append('action', 'update_progress');
        formData.append('corso_id', corsoId);
        formData.append('progresso', progressoCalcolato);

        fetch('visualizza_corso.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'error') {
                console.error("Errore salvataggio progresso nel DB:", data.message);
            }
        })
        .catch(err => console.error("Errore di rete nell'aggiornamento avanzamento:", err));
    }

    // 5. Gestione dei click sui pulsanti di navigazione
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            if (currentIndex < totaleSlides - 1) {
                currentIndex++;
                renderSlide();
            } else {
                // Se l'utente clicca su "Completa Corso", forza l'invio del 100% prima di uscire
                salvaProgressoNelDatabase(100);
                
                setTimeout(function() {
                    alert("Complimenti! Hai completato tutte le slide di questo corso. Il tuo progresso è stato aggiornato al 100%! 🎉");
                    window.location.href = 'student_dashboard.php';
                }, 200);
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