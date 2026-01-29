<?php
header('Content-Type: application/json');

$email = $_GET['email'] ?? 'test@example.com';
$resultado = "Esto es una prueba de conexión desde el servidor PHP a n8n.";

$webhookUrl = 'https://n8n.iacomfasucre.com/webhook/whisper';
$data = [
    'email' => $email,
    'resultado' => $resultado
];

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Add this to bypass SSL issues if any

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'webhook_url' => $webhookUrl,
    'http_code' => $httpCode,
    'response' => $response,
    'curl_error' => $error,
    'data_sent' => $data
]);
