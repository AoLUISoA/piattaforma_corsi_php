const API_BASE_URL = "http://localhost:8080/api";
window.API_BASE_URL = API_BASE_URL;

async function apiCall(endpoint, method = "GET", data = null) {
    const options = { method, headers: { "Content-Type": "application/json" } };
    if (data) options.body = JSON.stringify(data);
    let url = `${API_BASE_URL}${endpoint}`;
    if (method === "GET") {
        const separator = url.includes('?') ? '&' : '?';
        url = `${url}${separator}_t=${Date.now()}`;
    }
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000);
        const response = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(timeoutId);
        if (!response.ok) throw new Error(`API errore ${response.status}: ${await response.text()}`);
        return await response.json();
    } catch (error) {
        console.error(`Chiamata API fallita (${endpoint}):`, error);
        throw error;
    }
}

function getCurrentUser() {
    const userStr = sessionStorage.getItem('currentUser');
    return userStr ? JSON.parse(userStr) : null;
}

function setCurrentUser(user) {
    sessionStorage.setItem('currentUser', JSON.stringify(user));
}

function logout() {
    sessionStorage.removeItem('currentUser');
    window.location.href = 'index.html';
}

async function requireAuth(ruoloRichiesto = null) {
    const user = getCurrentUser();
    if (!user) {
        window.location.href = 'index.html';
        return null;
    }
    if (ruoloRichiesto && user.ruolo !== ruoloRichiesto) {
        window.location.href = user.ruolo === 'studente' ? 'home_studente.html' : 'home_docente.html';
        return null;
    }
    return user;
}

async function registerUser(username, password, ruolo, email) {
    try {
        const result = await apiCall("/register", "POST", {
            username: username,
            password: password,
            ruolo: ruolo,
            email: email
        });
        
        if (result.success) {
            return { success: true, user: result.user };
        } else {
            return { success: false, error: result.error || "Errore registrazione" };
        }
    } catch (error) {
        console.error("Register errore:", error);
        return { success: false, error: "Errore di connessione al server" };
    }
}

async function loginUser(username, password, ruoloAtteso) {
    try {
        const result = await apiCall("/login", "POST", {
            username: username,
            password: password,
            ruolo: ruoloAtteso
        });
        
        if (result.success) {
            return { success: true, user: result.user };
        } else {
            return { success: false, error: result.error || "Credenziali errate" };
        }
    } catch (error) {
        console.error("Login error:", error);
        return { success: false, error: "Errore di connessione al server" };
    }
}

async function getAllCorsi() {
    try {
        const result = await apiCall("/corsi");
        if (result && result.success && Array.isArray(result.corsi)) {
            return result.corsi;
        }
        return [];
    } catch (error) {
        console.error("Get corsi errore:", error);
        return [];
    }
}

async function getCorsiByDocente(docenteId) {
    try {
        const result = await apiCall("/corsi/docente", "POST", {
            docente_id: docenteId
        });
        if (result && result.success && Array.isArray(result.corsi)) {
            return result.corsi;
        }
        return [];
    } catch (error) {
        console.error("Get corsi by docente errore:", error);
        return [];
    }
}

async function getCorsoById(corsoId) {
    try {
        const result = await apiCall(`/corsi/${corsoId}`);
        return result.corso || null;
    } catch (error) {
        console.error("Get corso by id errore:", error);
        return null;
    }
}

async function createCorso(titolo, descrizione, docenteId, difficolta, contenuto) {
    try {
        const result = await apiCall("/corsi", "POST", {
            titolo: titolo,
            descrizione: descrizione,
            docente_id: docenteId,
            difficolta: difficolta,
            contenuto: contenuto || ""
        });
        
        return { success: result.success === true };
    } catch (error) {
        console.error("Create corso error:", error);
        return { success: false, error: "Errore di connessione al server" };
    }
}

async function updateCorso(corsoId, titolo, descrizione, difficolta, contenuto) {
    try {
        const result = await apiCall(`/corsi/${corsoId}`, "PUT", {
            titolo: titolo,
            descrizione: descrizione,
            difficolta: difficolta,
            contenuto: contenuto || ""
        });
        
        return { success: result.success === true };
    } catch (error) {
        console.error("Update corso error:", error);
        return { success: false };
    }
}

async function deleteCorso(corsoId, docenteId) {
    try {
        const result = await apiCall(`/corsi/${corsoId}`, "DELETE", {
            docente_id: docenteId
        });
        return { success: result.success === true };
    } catch (error) {
        console.error("Delete corso errore:", error);
        return { success: false };
    }
}

async function isIscritto(studenteId, corsoId) {
    try {
        const result = await apiCall(`/iscrizioni/check/${studenteId}/${corsoId}?_t=${Date.now()}`);
        return result.iscritto === true;
    } catch (error) {
        console.error("Check iscrizione errore:", error);
        return false;
    }
}

async function getStatiIscrizione(studenteId) {
    try {
        const result = await apiCall(`/iscrizioni/stati?id_utente=${studenteId}`);
        if (result && result.success && Array.isArray(result.iscritto)) {
            return result.iscritto;
        }
        return [];
    } catch (error) {
        console.error("Get stati iscrizione errore:", error);
        return [];
    }
}

async function iscriviStudente(studenteId, corsoId) {
    try {
        const result = await apiCall("/iscrizioni", "POST", {
            id_utente: studenteId,
            id_corso: corsoId
        });
        
        console.log("iscriviStudente - Risposta dal server:", result);
        
        if (result && result.success === true) {
            return { success: true };
        } else if (result && result.already_iscritto === true) {
            return { success: false, already_iscritto: true, error: "Già iscritto" };
        } else {
            return { success: false, error: result?.error || "Errore sconosciuto" };
        }
    } catch (error) {
        console.error("Iscrizione errore:", error);
        return { success: false, error: "Errore di connessione al server" };
    }
}

async function rimuoviIscrizione(studenteId, corsoId) {
    try {
        const result = await apiCall(`/iscrizioni/${studenteId}/${corsoId}`, "DELETE");
        return { success: result.success === true };
    } catch (error) {
        console.error("Errore nella rimozione iscrizione :", error);
        return { success: false };
    }
}

async function getAvanzamento(studenteId, corsoId) {
    try {
        const result = await apiCall(`/avanzamento/${studenteId}/${corsoId}`);
        return result.avanzamento || 0;
    } catch (error) {
        console.error("Get avanzamento error:", error);
        return 0;
    }
}

async function aggiornaAvanzamento(studenteId, corsoId, nuovoAvanzamento) {
    try {
        const result = await apiCall(`/avanzamento/${studenteId}/${corsoId}`, "PUT", {
            avanzamento: nuovoAvanzamento
        });
        
        return { success: result.success === true };
    } catch (error) {
        console.error("Update avanzamento error:", error);
        return { success: false };
    }
}

async function addFeedback(studenteId, corsoId, voto, commento) {
    try {
        const result = await apiCall("/feedbacks", "POST", {
            id_utente: studenteId,
            id_corso: corsoId,
            voto: voto,
            commento: commento
        });
        
        return { success: result.success === true };
    } catch (error) {
        console.error("Add feedback error:", error);
        return { success: false };
    }
}

async function getStudentiIscrittiAlCorso(corsoId) {
    try {
        const result = await apiCall(`/iscrizioni/corso/${corsoId}/studenti`);
        return result.studenti || [];
    } catch (error) {
        console.error("Get studenti iscritti error:", error);
        return [];
    }
}

async function getNumeroStudentiIscritti(corsoId) {
    const studenti = await getStudentiIscrittiAlCorso(corsoId);
    return studenti.length;
}

function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

function showMessage(elementId, message, isError = true) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = message;
        element.className = `message ${isError ? 'error' : 'success'}`;
        
        setTimeout(() => {
            element.className = 'message';
        }, 3000);
    }
}

async function checkBackendConnection() {
    try {
        const result = await apiCall("/health");
        return result.status === "ok";
    } catch (error) {
        console.error("Backend non raggiungibile:", error);
        return false;
    }
}

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', async () => {
        const connected = await checkBackendConnection();
        if (!connected) {
            console.warn("Backend non raggiungibile. Verifica che il server sia in esecuzione su porta 8080");
        } else {
            console.log("Backend connesso al database XAMPP");
        }
    });
}