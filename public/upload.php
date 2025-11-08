<?php
// webhook.php

// Répondre toujours en JSON
header('Content-Type: application/json; charset=utf-8');

// 1. Récupération de l'URL et des autres paramètres
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// URL
if (isset($data['url'])) {
    $url = $data['url'];
} elseif (!empty($_POST['url'])) {
    $url = $_POST['url'];
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Paramètre "url" manquant.'
    ]);
    exit;
}

// Prompt et autres paramètres facultatifs
$prompt       = $data['Prompt']       ?? $_POST['Prompt']       ?? '';
$imageUrl     = $data['ImageUrl']     ?? $_POST['ImageUrl']     ?? '';
$videoPrompt  = $data['VideoPrompt']  ?? $_POST['VideoPrompt']  ?? '';
$videoFileUrl = $data['VideoFileUrl'] ?? $_POST['VideoFileUrl'] ?? '';

// 2. Validation basique de l'URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'URL invalide.'
    ]);
    exit;
}

// Vérifie que l'extension est bien .webp
$pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
if (strtolower($pathInfo['extension'] ?? '') !== 'webp') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Le fichier doit être au format .webp.'
    ]);
    exit;
}

// 3. Préparation du dossier de destination
$destDir = __DIR__ . '/pictures';
if (!is_dir($destDir)) {
    if (!mkdir($destDir, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Impossible de créer le dossier de destination.'
        ]);
        exit;
    }
}

// 4. Génération d’un nom de fichier unique
$filename = time() . '_' . bin2hex(random_bytes(4)) . '.webp';
$destPath = $destDir . '/' . $filename;

// 5. Téléchargement du fichier
$fp = fopen($destPath, 'w');
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_FAILONERROR, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if (curl_exec($ch) === false) {
    $err = curl_error($ch);
    curl_close($ch);
    fclose($fp);
    unlink($destPath);
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => "Erreur curl : $err"
    ]);
    exit;
}

curl_close($ch);
fclose($fp);

// 6. Sauvegarde des paramètres dans un CSV
$csvFile = __DIR__ . '/data.csv';
$isNew   = !file_exists($csvFile);
if ($csv = fopen($csvFile, 'a')) {
    // Entête si nouveau fichier
    if ($isNew) {
        fputcsv($csv, [
            'timestamp', 'url', 'prompt', 'imageUrl', 'videoPrompt', 'videoFileUrl', 'fileName', 'path'
        ]);
    }
    fputcsv($csv, [
        date('c'),
        $url,
        $prompt,
        $imageUrl,
        $videoPrompt,
        $videoFileUrl,
        $filename,
        "pictures/$filename"
    ]);
    fclose($csv);
} else {
    // Échec de l'ouverture du CSV (warning sans bloquer)
    error_log("Impossible d'ouvrir le fichier CSV pour écriture : $csvFile");
}

// 7. Réponse au webhook
http_response_code(200);
echo json_encode([
    'status'   => 'success',
    'fileName' => $filename,
    'path'     => "pictures/$filename"
]);
