#include <iostream>
#include <string>
#include <cstring>
#include <cstdlib>
#include <sstream>
#include <fstream>
#include <vector>
#include <regex>
#include <map>

#ifdef _WIN32
#include <winsock2.h>
#include <ws2tcpip.h>
#include <windows.h>
#pragma comment(lib, "ws2_32.lib")
#else
#include <sys/socket.h>
#include <netinet/in.h>
#include <unistd.h>
#include <arpa/inet.h>
#endif

#include <mysql.h>

using namespace std;

const int SERVER_PORT = 8080;

MYSQL* conn = nullptr;

bool connectDatabase() {
    conn = mysql_init(NULL);
    if (conn == NULL) {
        cerr << "Errore inizializzazione MySQL" << endl;
        return false;
    }
    
    if (!mysql_real_connect(conn, "localhost", "root", "", "piattaforma_corsi", 3306, NULL, 0)) {
        cerr << "Errore connessione: " << mysql_error(conn) << endl;
        return false;
    }
    
    cout << "Connesso al database" << endl;
    return true;
}

void disconnectDatabase() {
    if (conn) {
        mysql_close(conn);
        conn = nullptr;
    }
}

bool executeModify(const string& query) {
    if (mysql_query(conn, query.c_str())) {
        cerr << "Errore query: " << mysql_error(conn) << endl;
        return false;
    }
    mysql_query(conn, "COMMIT");
    return true;
}

string handleLogin(const string& username, const string& password, const string& ruolo) {
    char query[500];
    snprintf(query, sizeof(query),
        "SELECT id, username, ruolo, email FROM utenti WHERE username='%s' AND password='%s' AND ruolo='%s'",
        username.c_str(), password.c_str(), ruolo.c_str());
    
    if (mysql_query(conn, query)) {
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":false,\"error\":\"Credenziali errate\"}";
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    if (!row) {
        mysql_free_result(result);
        return "{\"success\":false,\"error\":\"Credenziali errate\"}";
    }
    
    string id = row[0] ? row[0] : "0";
    string uname = row[1] ? row[1] : "";
    string r = row[2] ? row[2] : "";
    string email = row[3] ? row[3] : "";
    
    mysql_free_result(result);
    
    string json = "{\"id\":" + id + ",\"username\":\"" + uname + "\",\"ruolo\":\"" + r + "\",\"email\":\"" + email + "\"}";
    
    return "{\"success\":true,\"user\":" + json + "}";
}

string handleRegister(const string& username, const string& password, const string& ruolo, const string& email) {
    char checkQuery[300];
    snprintf(checkQuery, sizeof(checkQuery), "SELECT COUNT(*) FROM utenti WHERE username='%s'", username.c_str());
    
    if (mysql_query(conn, checkQuery)) {
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (result) {
        MYSQL_ROW row = mysql_fetch_row(result);
        if (row && row[0] && atoi(row[0]) > 0) {
            mysql_free_result(result);
            return "{\"success\":false,\"error\":\"Username già esistente\"}";
        }
        mysql_free_result(result);
    }
    
    char query[500];
    snprintf(query, sizeof(query),
        "INSERT INTO utenti (username, password, ruolo, email) VALUES ('%s', '%s', '%s', '%s')",
        username.c_str(), password.c_str(), ruolo.c_str(), email.c_str());
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    
    return "{\"success\":false,\"error\":\"Errore registrazione\"}";
}

string handleGetAllCorsi() {
    string query = "SELECT id_corso, titolo, descrizione, docente_id, difficolta, IFNULL(contenuto, '') FROM corsi";
    
    if (mysql_query(conn, query.c_str())) {
        cerr << "Errore select: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"corsi\":[]}";
    }
    
    string json = "{\"success\":true,\"corsi\":[";
    
    MYSQL_ROW row;
    bool first = true;
    
    while ((row = mysql_fetch_row(result))) {
        if (!first) json += ",";
        
        string id = row[0] ? row[0] : "0";
        string titolo = row[1] ? row[1] : "";
        string descrizione = row[2] ? row[2] : "";
        string docente_id = row[3] ? row[3] : "0";
        string difficolta = row[4] ? row[4] : "base";
        string contenuto = row[5] ? row[5] : "";
        
        auto escape = [](string s) -> string {
            string r;
            for (char c : s) {
                if (c == '"') r += "\\\"";
                else if (c == '\n') r += " ";
                else if (c == '\r') r += " ";
                else if (c == '\t') r += " ";
                else r += c;
            }
            return r;
        };
        
        json += "{";
        json += "\"id\":" + id + ",";
        json += "\"titolo\":\"" + escape(titolo) + "\",";
        json += "\"descrizione\":\"" + escape(descrizione) + "\",";
        json += "\"docente_id\":" + docente_id + ",";
        json += "\"difficolta\":\"" + escape(difficolta) + "\",";
        json += "\"contenuto\":\"" + escape(contenuto) + "\"";
        json += "}";
        
        first = false;
    }
    
    json += "]}";
    
    mysql_free_result(result);
    
    return json;
}

string handleGetStatiIscrizione(int id_utente) {
    char query[300];
    snprintf(query, sizeof(query),
        "SELECT id_corso FROM iscrizioni WHERE id_utente=%d",
        id_utente);
    
    if (mysql_query(conn, query)) {
        cerr << "Errore query stati iscrizione: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"iscritto\":[]}";
    }
    
    string json = "{\"success\":true,\"iscritto\":[";
    
    MYSQL_ROW row;
    bool first = true;
    
    while ((row = mysql_fetch_row(result))) {
        if (!first) json += ",";
        json += row[0] ? row[0] : "0";
        first = false;
    }
    
    json += "]}";
    
    mysql_free_result(result);
    
    return json;
}

string handleGetCorsiByDocente(int docente_id) {
    char query[600];
    snprintf(query, sizeof(query),
        "SELECT c.id_corso, c.titolo, c.descrizione, c.docente_id, c.difficolta, c.contenuto, "
        "(SELECT COUNT(*) FROM iscrizioni i JOIN utenti u ON i.id_utente = u.id WHERE i.id_corso = c.id_corso AND u.ruolo='studente') AS num_studenti "
        "FROM corsi c WHERE c.docente_id = %d",
        docente_id);

    if (mysql_query(conn, query)) {
        cerr << "Errore query: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }

    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) return "{\"success\":true,\"corsi\":[]}";

    string json = "{\"success\":true,\"corsi\":[";
    MYSQL_ROW row;
    bool first = true;
    while ((row = mysql_fetch_row(result))) {
        if (!first) json += ",";
        string id = row[0] ? row[0] : "0";
        string titolo = row[1] ? row[1] : "";
        string descrizione = row[2] ? row[2] : "";
        string docente_id_str = row[3] ? row[3] : "0";
        string difficolta = row[4] ? row[4] : "base";
        string contenuto = row[5] ? row[5] : "";
        string num_studenti = row[6] ? row[6] : "0";

        auto escape = [](string s) -> string {
            string r;
            for (char c : s) {
                if (c == '"') r += "\\\"";
                else if (c == '\n') r += " ";
                else if (c == '\r') r += " ";
                else if (c == '\t') r += " ";
                else r += c;
            }
            return r;
        };

        json += "{";
        json += "\"id\":" + id + ",";
        json += "\"titolo\":\"" + escape(titolo) + "\",";
        json += "\"descrizione\":\"" + escape(descrizione) + "\",";
        json += "\"docente_id\":" + docente_id_str + ",";
        json += "\"difficolta\":\"" + escape(difficolta) + "\",";
        json += "\"contenuto\":\"" + escape(contenuto) + "\",";
        json += "\"num_studenti\":" + num_studenti;
        json += "}";
        first = false;
    }
    json += "]}";
    mysql_free_result(result);
    return json;
}

string handleGetCorsoById(int corso_id) {
    char query[200];
    snprintf(query, sizeof(query),
        "SELECT id_corso, titolo, descrizione, docente_id, difficolta, IFNULL(contenuto, '') FROM corsi WHERE id_corso=%d",
        corso_id);
    
    if (mysql_query(conn, query)) {
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":false,\"error\":\"Corso non trovato\"}";
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    if (!row) {
        mysql_free_result(result);
        return "{\"success\":false,\"error\":\"Corso non trovato\"}";
    }
    
    string id = row[0] ? row[0] : "0";
    string titolo = row[1] ? row[1] : "";
    string descrizione = row[2] ? row[2] : "";
    string docente_id = row[3] ? row[3] : "0";
    string difficolta = row[4] ? row[4] : "base";
    string contenuto = row[5] ? row[5] : "";
    
    auto escape = [](string s) -> string {
        string r;
        for (char c : s) {
            if (c == '"') r += "\\\"";
            else if (c == '\n') r += " ";
            else if (c == '\r') r += " ";
            else if (c == '\t') r += " ";
            else r += c;
        }
        return r;
    };
    
    string json = "{\"id\":" + id + ",\"titolo\":\"" + escape(titolo) + "\",\"descrizione\":\"" + escape(descrizione) + "\",\"docente_id\":" + docente_id + ",\"difficolta\":\"" + escape(difficolta) + "\",\"contenuto\":\"" + escape(contenuto) + "\"}";
    
    mysql_free_result(result);
    
    return "{\"success\":true,\"corso\":" + json + "}";
}

string handleCreateCorso(const string& titolo, const string& descrizione, int docente_id, const string& difficolta, const string& contenuto) {
    char query[2000];
    snprintf(query, sizeof(query),
        "INSERT INTO corsi (titolo, descrizione, docente_id, difficolta, contenuto) VALUES ('%s', '%s', %d, '%s', '%s')",
        titolo.c_str(), descrizione.c_str(), docente_id, difficolta.c_str(), contenuto.c_str());
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore creazione corso\"}";
}

string handleUpdateCorso(int corso_id, const string& titolo, const string& descrizione, const string& difficolta, const string& contenuto) {
    char query[2000];
    snprintf(query, sizeof(query),
        "UPDATE corsi SET titolo='%s', descrizione='%s', difficolta='%s', contenuto='%s' WHERE id_corso=%d",
        titolo.c_str(), descrizione.c_str(), difficolta.c_str(), contenuto.c_str(), corso_id);
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore modifica corso\"}";
}

string handleDeleteCorso(int corso_id, int docente_id) {
    char query[300];
    snprintf(query, sizeof(query),
        "DELETE FROM corsi WHERE id_corso=%d AND docente_id=%d",
        corso_id, docente_id);
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore eliminazione corso\"}";
}

string handleIscrizione(int id_utente, int id_corso) {
    char checkQuery[300];
    snprintf(checkQuery, sizeof(checkQuery),
        "SELECT COUNT(*) FROM iscrizioni WHERE id_utente=%d AND id_corso=%d",
        id_utente, id_corso);
    
    if (mysql_query(conn, checkQuery)) {
        cerr << "Errore verifica iscrizione: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore verifica\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (result) {
        MYSQL_ROW row = mysql_fetch_row(result);
        if (row && row[0] && atoi(row[0]) > 0) {
            mysql_free_result(result);
            return "{\"success\":false,\"already_iscritto\":true,\"error\":\"Già iscritto\"}";
        }
        mysql_free_result(result);
    }
    
    char query[300];
    snprintf(query, sizeof(query),
        "INSERT INTO iscrizioni (id_utente, id_corso, avanzamento) VALUES (%d, %d, 0)",
        id_utente, id_corso);
    
    cout << "DEBUG Iscrizione: " << query << endl;
    
    if (mysql_query(conn, query)) {
        cerr << "Errore inserimento iscrizione: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore iscrizione\"}";
    }
    
    mysql_query(conn, "COMMIT");
    
    cout << "DEBUG Iscrizione: SUCCESSO" << endl;
    
    return "{\"success\":true}";
}

string handleCheckIscritto(int id_utente, int id_corso) {
    char query[300];
    snprintf(query, sizeof(query),
        "SELECT COUNT(*) FROM iscrizioni WHERE id_utente=%d AND id_corso=%d",
        id_utente, id_corso);
    
    if (mysql_query(conn, query)) {
        cerr << "Errore query: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore query\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"iscritto\":false}";
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    int count = row ? atoi(row[0]) : 0;
    mysql_free_result(result);
    
    return "{\"success\":true,\"iscritto\":" + string(count > 0 ? "true" : "false") + "}";
}

string handleGetAvanzamento(int id_utente, int id_corso) {
    char query[300];
    snprintf(query, sizeof(query),
        "SELECT avanzamento FROM iscrizioni WHERE id_utente=%d AND id_corso=%d",
        id_utente, id_corso);
    
    if (mysql_query(conn, query)) {
        return "{\"success\":false,\"error\":\"Errore query\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"avanzamento\":0}";
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    float avanzamento = row ? atof(row[0]) : 0;
    mysql_free_result(result);
    
    char response[200];
    snprintf(response, sizeof(response), "{\"success\":true,\"avanzamento\":%.1f}", avanzamento);
    return string(response);
}

string handleUpdateAvanzamento(int id_utente, int id_corso, float avanzamento) {
    char query[300];
    snprintf(query, sizeof(query),
        "UPDATE iscrizioni SET avanzamento=%.1f WHERE id_utente=%d AND id_corso=%d",
        avanzamento, id_utente, id_corso);
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore aggiornamento avanzamento\"}";
}

string handleAddFeedback(int id_utente, int id_corso, int voto, const string& commento) {
    char query[1000];
    snprintf(query, sizeof(query),
        "INSERT INTO feedback (id_utente, id_corso, voto, commento) VALUES (%d, %d, %d, '%s')",
        id_utente, id_corso, voto, commento.c_str());
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore aggiunta feedback\"}";
}

string handleGetStudentiIscritti(int corso_id) {
    char query[600];
    snprintf(query, sizeof(query),
        "SELECT u.id, u.username, u.email, i.avanzamento FROM iscrizioni i "
        "JOIN utenti u ON i.id_utente = u.id "
        "WHERE i.id_corso=%d AND u.ruolo='studente'",
        corso_id);
    
    if (mysql_query(conn, query)) {
        cerr << "Errore database: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"studenti\":[]}";
    }
    
    string json = "{\"success\":true,\"studenti\":[";
    
    MYSQL_ROW row;
    bool first = true;
    
    while ((row = mysql_fetch_row(result))) {
        if (!first) json += ",";
        
        string id = row[0] ? row[0] : "0";
        string username = row[1] ? row[1] : "";
        string email = row[2] ? row[2] : "";
        string avanzamento = row[3] ? row[3] : "0";
        
        json += "{\"id\":" + id + ",\"username\":\"" + username + "\",\"email\":\"" + email + "\",\"avanzamento\":" + avanzamento + "}";
        first = false;
    }
    
    json += "]}";
    
    mysql_free_result(result);
    
    cout << "DEBUG: Corso " << corso_id << " ha " << (first ? 0 : mysql_num_rows(result)) << " studenti iscritti" << endl;
    
    return json;
}

string handleGetConteggioStudentiIscritti(int corso_id) {
    char query[300];
    snprintf(query, sizeof(query),
        "SELECT COUNT(*) FROM iscrizioni i "
        "JOIN utenti u ON i.id_utente = u.id "
        "WHERE i.id_corso=%d AND u.ruolo='studente'",
        corso_id);
    
    if (mysql_query(conn, query)) {
        cerr << "Errore conteggio: " << mysql_error(conn) << endl;
        return "{\"success\":false,\"error\":\"Errore database\"}";
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) {
        return "{\"success\":true,\"conteggio\":0}";
    }
    
    MYSQL_ROW row = mysql_fetch_row(result);
    int conteggio = row ? atoi(row[0]) : 0;
    mysql_free_result(result);
    
    char response[100];
    snprintf(response, sizeof(response), "{\"success\":true,\"conteggio\":%d}", conteggio);
    return string(response);
}

string handleRimuoviIscrizione(int id_utente, int id_corso) {
    char query[300];
    snprintf(query, sizeof(query),
        "DELETE FROM iscrizioni WHERE id_utente=%d AND id_corso=%d",
        id_utente, id_corso);
    
    if (executeModify(query)) {
        return "{\"success\":true}";
    }
    return "{\"success\":false,\"error\":\"Errore rimozione iscrizione\"}";
}

void handleRequest(const string& request, string& response) {
    size_t method_end = request.find(' ');
    if (method_end == string::npos) return;
    
    string method = request.substr(0, method_end);
    
    size_t path_start = method_end + 1;
    size_t path_end = request.find(' ', path_start);
    if (path_end == string::npos) return;
    
    string path = request.substr(path_start, path_end - path_start);
    
    size_t query_pos = path.find('?');
    string query_string = "";
    if (query_pos != string::npos) {
        query_string = path.substr(query_pos + 1);
        path = path.substr(0, query_pos);
    }
    
    size_t body_start = request.find("\r\n\r\n");
    string body = "";
    if (body_start != string::npos) {
        body = request.substr(body_start + 4);
    }
    
    cout << "Richiesta: " << method << " " << path << endl;
    
    auto getJsonValue = [](const string& json, const string& key) -> string {
        string searchKey = "\"" + key + "\"";
        size_t keyPos = json.find(searchKey);
        if (keyPos == string::npos) return "";
        
        size_t colonPos = json.find(":", keyPos + searchKey.length());
        if (colonPos == string::npos) return "";
        
        size_t start = json.find_first_not_of(" \t", colonPos + 1);
        if (start == string::npos) return "";
        
        if (json[start] == '"') {
            size_t end = json.find("\"", start + 1);
            if (end == string::npos) return "";
            return json.substr(start + 1, end - start - 1);
        } else {
            size_t end = json.find_first_of(",}", start);
            if (end == string::npos) return "";
            return json.substr(start, end - start);
        }
    };
    
    auto getQueryParam = [](const string& qs, const string& key) -> string {
        size_t keyPos = qs.find(key + "=");
        if (keyPos == string::npos) return "";
        size_t start = keyPos + key.length() + 1;
        size_t end = qs.find("&", start);
        if (end == string::npos) end = qs.length();
        return qs.substr(start, end - start);
    };
    
    if (method == "OPTIONS") {
        response = "HTTP/1.1 200 OK\r\n"
                   "Access-Control-Allow-Origin: *\r\n"
                   "Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS\r\n"
                   "Access-Control-Allow-Headers: Content-Type\r\n"
                   "Access-Control-Max-Age: 86400\r\n\r\n";
        return;
    }
    
    if (path == "/api/health" && method == "GET") {
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n{\"status\":\"ok\"}";
    }
    else if (path == "/api/login" && method == "POST") {
        string username = getJsonValue(body, "username");
        string password = getJsonValue(body, "password");
        string ruolo = getJsonValue(body, "ruolo");
        string json = handleLogin(username, password, ruolo);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/register" && method == "POST") {
        string username = getJsonValue(body, "username");
        string password = getJsonValue(body, "password");
        string ruolo = getJsonValue(body, "ruolo");
        string email = getJsonValue(body, "email");
        string json = handleRegister(username, password, ruolo, email);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/corsi" && method == "GET") {
        string json = handleGetAllCorsi();
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/iscrizioni/stati" && method == "GET") {
        string id_utente_str = getQueryParam(query_string, "id_utente");
        int id_utente = atoi(id_utente_str.c_str());
        string json = handleGetStatiIscrizione(id_utente);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/corsi/docente" && method == "POST") {
        string docente_id_str = getJsonValue(body, "docente_id");
        int docente_id = atoi(docente_id_str.c_str());
        string json = handleGetCorsiByDocente(docente_id);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path.find("/api/corsi/") == 0 && path.length() > 11 && method == "GET") {
        int corso_id = atoi(path.substr(11).c_str());
        string json = handleGetCorsoById(corso_id);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/corsi" && method == "POST") {
        string titolo = getJsonValue(body, "titolo");
        string descrizione = getJsonValue(body, "descrizione");
        string docente_id_str = getJsonValue(body, "docente_id");
        string difficolta = getJsonValue(body, "difficolta");
        string contenuto = getJsonValue(body, "contenuto");
        int docente_id = atoi(docente_id_str.c_str());
        string json = handleCreateCorso(titolo, descrizione, docente_id, difficolta, contenuto);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path.find("/api/corsi/") == 0 && method == "PUT") {
        int corso_id = atoi(path.substr(11).c_str());
        string titolo = getJsonValue(body, "titolo");
        string descrizione = getJsonValue(body, "descrizione");
        string difficolta = getJsonValue(body, "difficolta");
        string contenuto = getJsonValue(body, "contenuto");
        string json = handleUpdateCorso(corso_id, titolo, descrizione, difficolta, contenuto);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path.find("/api/corsi/") == 0 && method == "DELETE") {
        int corso_id = atoi(path.substr(11).c_str());
        string docente_id_str = getJsonValue(body, "docente_id");
        int docente_id = atoi(docente_id_str.c_str());
        string json = handleDeleteCorso(corso_id, docente_id);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path == "/api/iscrizioni" && method == "POST") {
        string id_utente_str = getJsonValue(body, "id_utente");
        string id_corso_str = getJsonValue(body, "id_corso");
        int id_utente = atoi(id_utente_str.c_str());
        int id_corso = atoi(id_corso_str.c_str());
        string json = handleIscrizione(id_utente, id_corso);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path.find("/api/iscrizioni/check/") == 0 && method == "GET") {
        string remaining = path.substr(21);
        size_t slash = remaining.find('/');
        if (slash != string::npos) {
            int id_utente = atoi(remaining.substr(0, slash).c_str());
            int id_corso = atoi(remaining.substr(slash + 1).c_str());
            string json = handleCheckIscritto(id_utente, id_corso);
            response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
        }
    }
    else if (path.find("/api/avanzamento/") == 0 && method == "GET") {
        string remaining = path.substr(17);
        size_t slash = remaining.find('/');
        if (slash != string::npos) {
            int id_utente = atoi(remaining.substr(0, slash).c_str());
            int id_corso = atoi(remaining.substr(slash + 1).c_str());
            string json = handleGetAvanzamento(id_utente, id_corso);
            response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
        }
    }
    else if (path.find("/api/avanzamento/") == 0 && method == "PUT") {
        string remaining = path.substr(17);
        size_t slash = remaining.find('/');
        if (slash != string::npos) {
            int id_utente = atoi(remaining.substr(0, slash).c_str());
            int id_corso = atoi(remaining.substr(slash + 1).c_str());
            float avanzamento = atof(getJsonValue(body, "avanzamento").c_str());
            string json = handleUpdateAvanzamento(id_utente, id_corso, avanzamento);
            response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
        }
    }
    else if (path == "/api/feedbacks" && method == "POST") {
        string id_utente_str = getJsonValue(body, "id_utente");
        string id_corso_str = getJsonValue(body, "id_corso");
        string voto_str = getJsonValue(body, "voto");
        string commento = getJsonValue(body, "commento");
        int id_utente = atoi(id_utente_str.c_str());
        int id_corso = atoi(id_corso_str.c_str());
        int voto = atoi(voto_str.c_str());
        string json = handleAddFeedback(id_utente, id_corso, voto, commento);
        response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
    }
    else if (path.find("/api/iscrizioni/corso/") == 0 && method == "GET") {
        // --- NUOVA GESTIONE ROBUSTA ---
        string prefix = "/api/iscrizioni/corso/";
        string after_prefix = path.substr(prefix.length());
        size_t next_slash = after_prefix.find('/');
        if (next_slash != string::npos) {
            string id_str = after_prefix.substr(0, next_slash);
            int corso_id = atoi(id_str.c_str());
            if (corso_id > 0) {
                string suffix = after_prefix.substr(next_slash);
                if (suffix == "/studenti") {
                    string json = handleGetStudentiIscritti(corso_id);
                    response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
                } else if (suffix == "/conteggio") {
                    string json = handleGetConteggioStudentiIscritti(corso_id);
                    response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
                } else {
                    response = "HTTP/1.1 404 Not Found\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n{\"error\":\"Suffix non riconosciuto\"}";
                }
            } else {
                response = "HTTP/1.1 400 Bad Request\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n{\"error\":\"ID corso non valido\"}";
            }
        } else {
            response = "HTTP/1.1 404 Not Found\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n{\"error\":\"Formato URL non valido\"}";
        }
    }
    else if (path.find("/api/iscrizioni/") == 0 && path.find("/studenti") == string::npos && method == "DELETE") {
        string remaining = path.substr(16);
        size_t slash = remaining.find('/');
        if (slash != string::npos) {
            int id_utente = atoi(remaining.substr(0, slash).c_str());
            int id_corso = atoi(remaining.substr(slash + 1).c_str());
            string json = handleRimuoviIscrizione(id_utente, id_corso);
            response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n" + json;
        }
    }
    else {
        response = "HTTP/1.1 404 Not Found\r\nContent-Type: application/json\r\nAccess-Control-Allow-Origin: *\r\n\r\n{\"error\":\"Endpoint non trovato\"}";
    }
}

int main() {
    cout << "========================================" << endl;
    cout << " Piattaforma Corsi Online " << endl;
    cout << "========================================" << endl;
    
    if (!connectDatabase()) {
        cerr << "Impossibile connettersi al database. Verifica che XAMPP sia avviato." << endl;
        return 1;
    }
    
    cout << " Avvio server su porta " << SERVER_PORT << "..." << endl;
    cout << " API disponibili su http://localhost:" << SERVER_PORT << "/api/" << endl;
    cout << "========================================" << endl;
    
    #ifdef _WIN32
        WSADATA wsaData;
        WSAStartup(MAKEWORD(2, 2), &wsaData);
    #endif
    
    int server_fd, client_fd;
    struct sockaddr_in address;
    int opt = 1;
    int addrlen = sizeof(address);
    char buffer[65536] = {0};
    
    server_fd = socket(AF_INET, SOCK_STREAM, 0);
    if (server_fd < 0) {
        cerr << "Errore creazione socket" << endl;
        return 1;
    }
    
    if (setsockopt(server_fd, SOL_SOCKET, SO_REUSEADDR, (const char*)&opt, sizeof(opt)) < 0) {
        cerr << "Errore setsockopt" << endl;
        return 1;
    }
    
    address.sin_family = AF_INET;
    address.sin_addr.s_addr = INADDR_ANY;
    address.sin_port = htons(SERVER_PORT);
    
    if (bind(server_fd, (struct sockaddr*)&address, sizeof(address)) < 0) {
        cerr << "Errore bind sulla porta " << SERVER_PORT << endl;
        return 1;
    }
    
    if (listen(server_fd, 10) < 0) {
        cerr << "Errore listen" << endl;
        return 1;
    }
    
    cout << " Server in esecuzione su http://localhost:" << SERVER_PORT << endl;
    cout << " Premi Ctrl+C per terminare" << endl;
    cout << "========================================" << endl;
    
    while (true) {
        client_fd = accept(server_fd, (struct sockaddr*)&address, (socklen_t*)&addrlen);
        if (client_fd < 0) continue;
        
        int valread = recv(client_fd, buffer, 65536, 0);
        if (valread > 0) {
            string request(buffer);
            string response;
            handleRequest(request, response);
            send(client_fd, response.c_str(), response.length(), 0);
        }
        
        #ifdef _WIN32
            closesocket(client_fd);
        #else
            close(client_fd);
        #endif
        
        memset(buffer, 0, sizeof(buffer));
    }
    
    #ifdef _WIN32
        WSACleanup();
    #endif
    
    disconnectDatabase();
    return 0;
}