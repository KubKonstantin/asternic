<?php
// check_file_exists.php
if (isset($_GET['original_filename'])) {
    $original_filename = basename($_GET['original_filename']);
    $buffer_dir = '/opt/swr-light/buffer/';
    
    // Сначала ищем файл с оригинальным именем
    $filepath = $buffer_dir . $original_filename;
    
    if (file_exists($filepath)) {
        echo json_encode(['exists' => true, 'file' => $original_filename]);
        exit;
    }
    
    // Ищем файл с UUID в начале
    $files = scandir($buffer_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            // Проверяем, заканчивается ли файл на оригинальное имя
            if (strpos($file, $original_filename) !== false) {
                echo json_encode(['exists' => true, 'file' => $file]);
                exit;
            }
        }
    }
    
    echo json_encode(['exists' => false]);
} else {
    echo json_encode(['exists' => false]);
}
?>
