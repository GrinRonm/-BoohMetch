<?php
/**
 * API контроллер для общего доступа к файлам
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

header('Content-Type: application/json');

// Парсим URL
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $request);

// Проверяем, это ли ссылка на общий доступ
if (isset($parts[2]) && $parts[2] === 'share' && isset($parts[3])) {
    // Публичная ссылка на файл
    $shareToken = $parts[3];
    
    $share = $db->fetchOne(
        'SELECT s.*, f.id as file_id, f.original_name, f.size, f.telegram_file_id, u.id as user_id 
         FROM shares s
         LEFT JOIN files f ON s.file_id = f.id
         WHERE s.share_token = ?',
        [$shareToken]
    );

    if (!$share) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ссылка не найдена']);
        exit;
    }

    // Проверяем срок действия
    if ($share['expires_at']) {
        $expiresAt = strtotime($share['expires_at']);
        if ($expiresAt < time()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Ссылка истекла']);
            exit;
        }
    }

    // Если это запрос на скачивание
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
        if (!$share['allow_download']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Скачивание запрещено']);
            exit;
        }

        // Обновляем счётчик доступа
        $db->query(
            'UPDATE shares SET access_count = access_count + 1 WHERE id = ?',
            [$share['id']]
        );

        // Скачиваем файл из Telegram или с сервера
        if ($share['telegram_file_id']) {
            $telegramManager = new TelegramManager($db, $share['user_id']);
            $downloadUrl = $telegramManager->downloadFileFromTelegram($share['telegram_file_id']);
            
            if ($downloadUrl) {
                header('Location: ' . $downloadUrl);
                exit;
            }
        }

        http_response_code(410);
        echo json_encode(['success' => false, 'message' => 'Файл больше не доступен']);
        exit;
    }

    // Информация о файле для общего доступа
    echo json_encode([
        'success' => true,
        'file' => [
            'name' => $share['original_name'],
            'size' => $share['size'],
            'allow_download' => $share['allow_download'],
            'access_count' => $share['access_count']
        ]
    ]);
    exit;
}

// API для создания ссылки общего доступа
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Не авторизованы']);
    exit;
}

$userId = $auth->getUserId();
$shareManager = new ShareManager($db, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $fileId = isset($data['file_id']) ? (int)$data['file_id'] : null;
    $folderId = isset($data['folder_id']) ? (int)$data['folder_id'] : null;
    $expireTime = isset($data['expire_time']) ? (int)$data['expire_time'] : 0;
    $allowDownload = isset($data['allow_download']) ? (bool)$data['allow_download'] : true;

    $result = $shareManager->createShare($fileId, $folderId, $expireTime, $allowDownload);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
}
?>
