<?php
// play_decrypted.php
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $buffer_dir = '/opt/swr-light/buffer/';
    
    // Сначала ищем файл с точным именем
    $filepath = $buffer_dir . $filename;
    
    if (file_exists($filepath)) {
        serve_audio_file($filepath, $filename);
        exit;
    }
    
    // Если не нашли файл с UUID, ищем оригинальный файл (без UUID)
    // Если имя файла содержит UUID в формате UUID_original, пробуем найти оригинальный
    if (strpos($filename, '_') !== false) {
        $parts = explode('_', $filename, 2);
        if (count($parts) > 1) {
            $original_filename = $parts[1];
            $original_filepath = $buffer_dir . $original_filename;
            
            if (file_exists($original_filepath)) {
                // Переименовываем файл, чтобы в следующий раз найти его по полному имени
                rename($original_filepath, $filepath);
                serve_audio_file($filepath, $filename);
                exit;
            }
        }
    }
    
    // Если все еще не нашли, ищем любой файл, который содержит оригинальное имя
    if (isset($original_filename)) {
        $files = scandir($buffer_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && strpos($file, $original_filename) !== false) {
                $filepath = $buffer_dir . $file;
                serve_audio_file($filepath, $file);
                exit;
            }
        }
    }
    
    http_response_code(404);
    echo "Файл не найден: " . $filename;
} else {
    http_response_code(400);
    echo "Не указан файл";
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
