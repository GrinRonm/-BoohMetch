<?php
/**
 * API контроллер для скачивания файлов
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

spl_autoload_register(function ($class) {
    $paths = [
        APP_ROOT . 'src/classes/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$db = Database::getInstance();
$auth = new Auth($db);

if (!$auth->isAuthenticated()) {
    http_response_code(401);
    exit('Не авторизованы');
}

$userId = $auth->getUserId();

// Парсим URL для ID файла
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $request);
$fileId = isset($parts[3]) ? (int)$parts[3] : 0;

if (!$fileId) {
    http_response_code(404);
    exit('Файл не найден');
}

// Получаем информацию о файле
$file = $db->fetchOne(
    'SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0',
    [$fileId, $userId]
);

if (!$file) {
    http_response_code(404);
    exit('Файл не найден');
}

// Если файл хранится локально
if (file_exists(UPLOAD_TMP_DIR . $file['stored_name'])) {
    $filePath = UPLOAD_TMP_DIR . $file['stored_name'];
    
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
    header('Content-Length: ' . $file['size']);
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    
    readfile($filePath);
    exit;
}

// Если файл в Telegram
if ($file['telegram_file_id']) {
    $telegramManager = new TelegramManager($db, $userId);
    $downloadUrl = $telegramManager->downloadFileFromTelegram($file['telegram_file_id']);
    
    if ($downloadUrl) {
        // Перенаправляем на Telegram URL
        header('Location: ' . $downloadUrl);
        exit;
    }
}

http_response_code(410);
exit('Файл больше не доступен');
?>
