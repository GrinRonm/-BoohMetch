<?php
/**
 * API для просмотра файлов (preview)
 * Поддерживает: изображения, PDF, текстовые файлы
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
    echo json_encode(['success' => false, 'message' => 'Не авторизованы']);
    exit;
}

$userId = $auth->getUserId();

// Получаем ID файла из query параметра или из URL пути
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : (int)(explode('/', $_SERVER['REQUEST_URI'])[3] ?? 0);

if (!$fileId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID файла не указан']);
    exit;
}

// Получить информацию о файле
$file = $db->fetchOne(
    'SELECT * FROM files WHERE id = ? AND user_id = ?',
    [$fileId, $userId]
);

if (!$file) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Файл не найден']);
    exit;
}

$ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));

// Только эти типы файлов поддерживают просмотр
$previewableExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

if (!in_array($ext, $previewableExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Этот тип файла не поддерживает просмотр']);
    exit;
}

// MIME типы
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf'
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Если файл загружен в Телеграм - получаем напрямую
if ($file['telegram_file_id']) {
    $telegramManager = new TelegramManager($db, $userId);
    $downloadUrl = $telegramManager->downloadFileFromTelegram($file['telegram_file_id']);
    
    if ($downloadUrl) {
        header('Location: ' . $downloadUrl);
        exit;
    }
}

// Если файл локально на сервере (локальная разработка)
$localPath = APP_ROOT . 'storage/uploads/' . $file['file_path'];
if (file_exists($localPath) && is_readable($localPath)) {
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=3600');
    readfile($localPath);
    exit;
}

// Ошибка - файл недоступен
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Файл больше недоступен']);
?>
