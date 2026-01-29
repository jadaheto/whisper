<?php
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Get tracking info
$fileName = $_POST['fileName'] ?? '';
$chunkIndex = intval($_POST['chunkIndex'] ?? 0);
$totalChunks = intval($_POST['totalChunks'] ?? 0);
$fileId = $_POST['fileId'] ?? ''; // Unique ID for this upload session

if (empty($fileName) || empty($fileId)) {
    echo json_encode(['error' => 'Missing upload parameters']);
    exit;
}

// Use fileId to avoid collisions
$tempFileName = $fileId . '_' . $fileName;
$tempFilePath = $uploadDir . $tempFileName;

$chunk = $_FILES['chunk']['tmp_name'] ?? '';

if (!$chunk || !is_uploaded_file($chunk)) {
    echo json_encode(['error' => 'No chunk uploaded']);
    exit;
}

// Append chunk to the final file
$out = fopen($tempFilePath, $chunkIndex === 0 ? 'wb' : 'ab');
if ($out) {
    $in = fopen($chunk, 'rb');
    if ($in) {
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        fclose($in);
    }
    fclose($out);
} else {
    echo json_encode(['error' => 'Failed to open file for writing']);
    exit;
}

if ($chunkIndex + 1 === $totalChunks) {
    echo json_encode(['status' => 'complete', 'file' => $tempFileName]);
} else {
    echo json_encode(['status' => 'chunk_received', 'nextChunk' => $chunkIndex + 1]);
}
