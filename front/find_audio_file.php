<?php
// find_audio_file.php
if (isset($_GET['original_filename'])) {
    $original_filename = basename($_GET['original_filename']);
    $buffer_dir = '/opt/swr-light/buffer/';
    
    // Сначала ищем файл с оригинальным именем
    $filepath = $buffer_dir . $original_filename;
    
    if (file_exists($filepath)) {
        serve_audio_file($filepath, $original_filename);
        exit;
    }
    
    // Ищем файл с UUID в начале
    $files = scandir($buffer_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            // Проверяем, заканчивается ли файл на оригинальное имя
            if (strpos($file, $original_filename) !== false) {
                $filepath = $buffer_dir . $file;
                serve_audio_file($filepath, $file);
                exit;
            }
        }
    }
    
    http_response_code(404);
    echo "Файл не найден: " . $original_filename;
} else {
    http_response_code(400);
    echo "Не указано имя файла";
}

function serve_audio_file($filepath, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4'
    ];
    
    $content_type = $mime_types[$ext] ?? 'audio/mpeg';
    
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache');
    
    readfile($filepath);
}
?>
