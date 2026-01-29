<?php
set_time_limit(0); // Large files take time
ignore_user_abort(true); // Continue processing even if user disconnects
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/../uploads/';

// --- CONFIGURACIÓN FFmpeg ---
// Si 'ffmpeg' no está en tu PATH, coloca la ruta completa aquí.
// Ejemplo Windows: 'C:\\ffmpeg\\bin\\ffmpeg.exe'
// Ejemplo Linux/Mac: '/usr/local/bin/ffmpeg'
$ffmpegPath = 'C:\\ffmpeg-2026\\bin\\ffmpeg.exe';
// ----------------------------

// Start session to get API Key
session_start();

$apiKey = $_POST['apiKey'] ?? $_SESSION['openai_api_key'] ?? '';
$fileName = $_POST['fileName'] ?? '';
$email = $_POST['email'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'API Key is required']);
    exit;
}

if (empty($fileName)) {
    echo json_encode(['error' => 'File name is required']);
    exit;
}

// Store API key in session for future use
$_SESSION['openai_api_key'] = $apiKey;

$filePath = $uploadDir . $fileName;
if (!file_exists($filePath)) {
    echo json_encode(['error' => 'File not found on server']);
    exit;
}

$fileSize = filesize($filePath);
$openaiMaxBytes = 24 * 1024 * 1024; // 24MB to be safe

// Case 1: File is small enough for OpenAI
if ($fileSize <= $openaiMaxBytes) {
    $result = transcribeWithWhisper($apiKey, $filePath);
    unlink($filePath);
    echo json_encode(['status' => 'success', 'transcription' => $result['text'] ?? '', 'error' => $result['error'] ?? null]);
    exit;
}

// Case 2: File is large, need to split with FFmpeg

// 1. Verificar si la función exec() está habilitada
if (!function_exists('exec')) {
    echo json_encode(['error' => 'La función PHP "exec()" está deshabilitada en tu servidor. Debes habilitarla en php.ini para usar FFmpeg.']);
    exit;
}

// 2. Verificar si FFmpeg existe físicamente
if ($ffmpegPath !== 'ffmpeg' && !file_exists($ffmpegPath)) {
    echo json_encode([
        'error' => "No se encontró el ejecutable de FFmpeg en la ruta: $ffmpegPath",
        'debug_path' => $ffmpegPath,
        'help' => 'Verifica que la ruta sea correcta y que el archivo ffmpeg.exe esté ahí.'
    ]);
    exit;
}

// 3. Prueba rápida: ¿FFmpeg responde?
$testCmd = (strpos($ffmpegPath, ' ') !== false) ? "\"$ffmpegPath\" -version 2>&1" : "$ffmpegPath -version 2>&1";
exec($testCmd, $testOutput, $testReturn);
if ($testReturn !== 0) {
    echo json_encode([
        'error' => 'FFmpeg está en la ruta pero no se puede ejecutar.',
        'details' => $testOutput,
        'command' => $testCmd,
        'return_code' => $testReturn,
        'suggestion' => 'Esto puede ser por permisos de Windows o porque el archivo no es un ejecutable válido.'
    ]);
    exit;
}

$segmentDir = realpath($uploadDir) . DIRECTORY_SEPARATOR . 'segments_' . time() . DIRECTORY_SEPARATOR;
if (!is_dir($segmentDir)) {
    if (!mkdir($segmentDir, 0777, true)) {
        echo json_encode(['error' => 'No se pudo crear el directorio temporal: ' . $segmentDir]);
        exit;
    }
}

// En Windows, escapeshellarg() reemplaza los '%' por espacios.
// Usaremos una ruta con slashes normales (/) que FFmpeg entiende perfectamente y evita problemas de escape.
$absSegmentDir = str_replace('\\', '/', realpath($uploadDir)) . '/segments_' . time() . '/';
if (!is_dir($absSegmentDir)) {
    mkdir($absSegmentDir, 0777, true);
}

// El pattern debe llevar un solo '%' ( %03d )
$absSegmentPattern = $absSegmentDir . 'segment_%03d.mp3';
$safeFFmpegPath = (strpos($ffmpegPath, ' ') !== false) ? "\"$ffmpegPath\"" : $ffmpegPath;

// Usamos slashes normales para el archivo de entrada también
$absInputFile = str_replace('\\', '/', realpath($filePath));

// CAMBIO CRITICO: En lugar de "-c copy", re-encodificamos a mp3 (-c:a libmp3lame)
// Esto evita errores cuando el archivo original no es mp3 (como m4a o wav).
$splitCmd = "$safeFFmpegPath -i \"$absInputFile\" -f segment -segment_time 600 -c:a libmp3lame -q:a 4 \"$absSegmentPattern\" 2>&1";
exec($splitCmd, $output, $returnCode);

if ($returnCode !== 0) {
    echo json_encode([
        'error' => 'Error crítico al dividir el audio.',
        'details' => $output,
        'command' => $splitCmd,
        'return_code' => $returnCode,
        'info' => 'Si el error persiste, verifica si FFmpeg tiene el encoder libmp3lame.'
    ]);
    exit;
}

$segments = glob($segmentDir . 'segment_*.mp3');
sort($segments);

$fullTranscription = "";

foreach ($segments as $segment) {
    $result = transcribeWithWhisper($apiKey, $segment);
    if (isset($result['error'])) {
        $fullTranscription .= "\n[Error in segment: " . $result['error']['message'] . "]\n";
    } else {
        $fullTranscription .= $result['text'] . " ";
    }
    // Clean up segment
    unlink($segment);
}

// Clean up
rmdir($segmentDir);
// Keep the original uploaded file for a bit or delete it? Let's delete it.
unlink($filePath);

echo json_encode([
    'status' => 'success',
    'transcription' => trim($fullTranscription),
    'message' => 'La transcripción se enviará por correo electrónico a: ' . $email
]);

// If using FastCGI, this sends the response to the user and continues in background
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Send to webhook
sendResultToWebhook($email, trim($fullTranscription));

function sendResultToWebhook($email, $transcription)
{
    if (empty($email))
        return;

    $webhookUrl = 'https://n8n.iacomfasucre.com/webhook/whisper';
    $data = [
        'email' => $email,
        'resultado' => $transcription
    ];

    $jsonData = json_encode($data);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    curl_exec($ch);
    curl_close($ch);
}

function transcribeWithWhisper($apiKey, $filePath)
{
    if (!file_exists($filePath))
        return ['error' => ['message' => 'Segment file not found']];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: multipart/form-data'
    ]);

    $data = [
        'file' => new CURLFile($filePath),
        'model' => 'whisper-1'
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return json_decode($response, true) ?: ['error' => ['message' => 'API Request failed with code ' . $httpCode]];
    }

    return json_decode($response, true);
}
