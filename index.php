<?php
// ----------- CONFIGURAÇÃO -----------
$senha_de_acesso = 'projetostart@2024';
$caminho_chave_json = 'service-account-key.json'; 

// ----------- LÓGICA DO SCRIPT (VERSÃO AUTÓNOMA) -----------
session_start();
$erro = '';
$sucesso = '';
$log_execucao = [];

// Função para criar o JWT e obter o token de acesso
function get_access_token($key_file_path) {
    if (!file_exists($key_file_path)) {
        throw new Exception("Ficheiro da chave de serviço não encontrado: " . $key_file_path);
    }
    $key_file_contents = file_get_contents($key_file_path);
    $service_account_credentials = json_decode($key_file_contents, true);
    if (!$service_account_credentials) {
        throw new Exception("Formato inválido do ficheiro da chave de serviço.");
    }

    $private_key = $service_account_credentials['private_key'];
    $client_email = $service_account_credentials['client_email'];

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

    $now = time();
    $payload = json_encode([
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/indexing',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);
    $payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

    $signature = '';
    openssl_sign("$header.$payload", $signature, $private_key, 'sha256');
    $signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = "$header.$payload.$signature";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $token_data = json_decode($response, true);
    curl_close($ch);

    if (isset($token_data['access_token'])) {
        return $token_data['access_token'];
    } else {
        throw new Exception("Falha ao obter token de acesso. Resposta: " . $response);
    }
}

// Lógica de Login
if (isset($_POST['password'])) {
    if ($_POST['password'] === $senha_de_acesso) {
        $_SESSION['logged_in'] = true;
    } else {
        $erro = 'Senha incorreta!';
    }
}

// Lógica de Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Lógica de Envio para a API (só se estiver logado)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['urls']) && !empty($_POST['urls'])) {
    $urls_para_enviar = preg_split('/\r\n|\r|\n/', $_POST['urls']);
    $urls_para_enviar = array_filter(array_map('trim', $urls_para_enviar));
    $tipo_de_acao = $_POST['action_type'];

    if (!empty($urls_para_enviar)) {
        try {
            $access_token = get_access_token($caminho_chave_json);
            $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
            
            foreach ($urls_para_enviar as $url) {
                $postData = json_encode([
                    'url' => $url,
                    'type' => $tipo_de_acao
                ]);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ]);
                $responseBody = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($statusCode == 200) {
                     $log_execucao[] = "SUCESSO ($statusCode): URL $url enviada para $tipo_de_acao.";
                } else {
                     $log_execucao[] = "ERRO ($statusCode): Falha ao enviar URL $url. Resposta: " . $responseBody;
                }
            }
            $sucesso = "Processamento concluído. Verifique o log abaixo.";

        } catch (Exception $e) {
            $erro = "Ocorreu um erro: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Gestor da API de Indexação Google</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f4; color: #333; line-height: 1.6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #555; }
        textarea { width: 100%; height: 150px; padding: 10px; border-radius: 3px; border: 1px solid #ddd; }
        input[type="password"], input[type="submit"] { width: 100%; padding: 10px; margin: 10px 0; border-radius: 3px; border: 1px solid #ddd; box-sizing: border-box; }
        input[type="submit"] { background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        input[type="submit"]:hover { background-color: #45a049; }
        .error { background-color: #ffdddd; color: #d8000c; padding: 10px; margin-bottom: 10px; border: 1px solid #d8000c; }
        .success { background-color: #d4edda; color: #155724; padding: 10px; margin-bottom: 10px; border: 1px solid #c3e6cb; }
        .log { background-color: #e2e3e5; padding: 10px; border: 1px solid #d6d8db; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; }
        a { color: #d9534f; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestor da API de Indexação Google</h1>

        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
            <h2>Acesso Restrito</h2>
            <form method="post">
                <label for="password">Senha:</label>
                <input type="password" name="password" id="password" required>
                <input type="submit" value="Entrar">
            </form>
            <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
        <?php else: ?>
            <p>Bem-vindo! <a href="?logout=true">Sair</a></p>
            <form method="post">
                <label for="urls"><h2>URLs (uma por linha)</h2></label>
                <textarea name="urls" id="urls" required></textarea>
                
                <h2>Ação</h2>
                <input type="radio" id="update" name="action_type" value="URL_UPDATED" checked>
                <label for="update">Publicar / Atualizar URL</label><br>
                <input type="radio" id="delete" name="action_type" value="URL_DELETED">
                <label for="delete">Remover URL</label><br><br>
                
                <input type="submit" value="Enviar para a API">
            </form>

            <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <?php if ($sucesso): ?><div class="success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>
            
            <?php if (!empty($log_execucao)): ?>
                <h2>Log da Execução</h2>
                <div class="log"><?= htmlspecialchars(implode("\n", $log_execucao)) ?></div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>