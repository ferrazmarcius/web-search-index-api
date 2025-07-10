<?php
// ----------- CONFIGURAÇÃO -----------
define('SITEMAP_URL', 'https://clictresdemaio.com/sitemap.xml'); 
define('SERVICE_ACCOUNT_KEY_FILE', 'service-account-key.json');
define('HISTORY_LOG_FILE', 'submission_history.log');
define('SUBMISSION_LIMIT', 190);
define('RESUBMIT_INTERVAL_SECONDS', 86400);
// ----------- FIM DA CONFIGURAÇÃO -----------

// --- SCRIPT PRINCIPAL (VERSÃO FINAL) ---
set_time_limit(300);
date_default_timezone_set('America/Sao_Paulo');

echo "--- Iniciando processo em: " . date('Y-m-d H:i:s') . " ---\n";

// ... (A função get_access_token continua igual, não precisa de ser alterada)
function get_access_token($key_file_path) {
    if (!file_exists($key_file_path)) throw new Exception("Ficheiro da chave de serviço não encontrado: " . $key_file_path);
    $creds = json_decode(file_get_contents($key_file_path), true);
    if (!$creds) throw new Exception("Formato inválido do ficheiro da chave de serviço.");
    $private_key = $creds['private_key'];
    $client_email = $creds['client_email'];
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $now = time();
    $payload = json_encode(['iss' => $client_email, 'scope' => 'https://www.googleapis.com/auth/indexing', 'aud' => 'https://oauth2.googleapis.com/token', 'exp' => $now + 3600, 'iat' => $now]);
    $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    openssl_sign("$header.$payload", $signature, $private_key, 'sha256');
    $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = "$header.$payload.$signature";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    if (isset($data['access_token'])) return $data['access_token'];
    throw new Exception("Falha ao obter token de acesso. Resposta: " . $response);
}

function submit_to_google($url, $type, $access_token) {
    // CORREÇÃO: Garante que não há barras duplas no URL (exceto em https://)
    $url = preg_replace('~(?<!:)/{2,}~', '/', $url);

    $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    $postData = json_encode(['url' => $url, 'type' => $type]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
    $responseBody = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($statusCode == 200) {
        echo "SUCESSO: URL $url enviada para $type.\n";
        return true;
    } else {
        echo "ERRO ($statusCode): Falha ao enviar URL $url. Resposta: $responseBody\n";
        return false;
    }
}

// ... (A função fetch_sitemap_urls continua igual)
function fetch_sitemap_urls($sitemap_url) {
    $urls = [];
    $xml_string = @file_get_contents($sitemap_url);
    if ($xml_string === false) { echo "ERRO: Não foi possível aceder ao sitemap: $sitemap_url\n"; return []; }
    $xml = new SimpleXMLElement($xml_string);
    if (isset($xml->sitemap)) {
        echo "Detetado sitemap index. A processar sitemaps individuais...\n";
        foreach ($xml->sitemap as $sitemap_entry) { $urls = array_merge($urls, fetch_sitemap_urls((string)$sitemap_entry->loc)); }
    } elseif (isset($xml->url)) {
        foreach ($xml->url as $url_entry) {
            $loc = (string)$url_entry->loc;
            $lastmod = isset($url_entry->lastmod) ? strtotime((string)$url_entry->lastmod) : time();
            $urls[$loc] = $lastmod;
        }
    }
    return $urls;
}


// --- Lógica Principal ---
try {
    $history = file_exists(HISTORY_LOG_FILE) ? json_decode(file_get_contents(HISTORY_LOG_FILE), true) : [];
    echo "Histórico de submissões carregado. " . count($history) . " URLs no registo.\n";
    $sitemap_urls = fetch_sitemap_urls(SITEMAP_URL);
    echo "Sitemap(s) processado(s). " . count($sitemap_urls) . " URLs encontradas.\n";
    $urls_to_submit = [];
    foreach ($sitemap_urls as $url => $lastmod) {
        if (!isset($history[$url])) {
            $urls_to_submit[] = $url;
        } elseif ($lastmod > $history[$url] && (time() - $history[$url] > RESUBMIT_INTERVAL_SECONDS)) {
            $urls_to_submit[] = $url;
        }
    }
    echo count($urls_to_submit) . " URLs novas ou atualizadas encontradas para submissão.\n";
    if (empty($urls_to_submit)) {
        echo "Nenhuma ação necessária.\n";
        exit;
    }
    $access_token = get_access_token(SERVICE_ACCOUNT_KEY_FILE);
    echo "Token de acesso obtido com sucesso.\n";
    $batch_urls = array_slice($urls_to_submit, 0, SUBMISSION_LIMIT);
    echo "A enviar um lote de " . count($batch_urls) . " URLs...\n";
    foreach ($batch_urls as $url) {
        if (submit_to_google($url, 'URL_UPDATED', $access_token)) {
            $history[$url] = time();
        }
    }
    file_put_contents(HISTORY_LOG_FILE, json_encode($history, JSON_PRETTY_PRINT));
    echo "Histórico de submissões atualizado.\n";
} catch (Exception $e) {
    echo "ERRO FATAL NO SCRIPT: " . $e->getMessage() . "\n";
}
echo "--- Processo concluído em: " . date('Y-m-d H:i:s') . " ---\n";
?>