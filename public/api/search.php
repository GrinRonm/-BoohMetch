<?php
/**
 * API поиск по файлам и папкам
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

if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Не авторизованы']);
    exit;
}

$userId = $auth->getUserId();
$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Поисковый запрос слишком короткий', 'files' => [], 'folders' => []]);
    exit;
}

// Экранируем для поиска
$searchPattern = '%' . addcslashes($query, '%_') . '%';

// Поиск файлов
$files = $db->fetchAll(
    'SELECT * FROM files 
     WHERE user_id = ? AND is_deleted = 0 AND original_name LIKE ? 
     ORDER BY created_at DESC LIMIT 100',
    [$userId, $searchPattern]
);

// Поиск папок
$folders = $db->fetchAll(
    'SELECT * FROM folders 
     WHERE user_id = ? AND name LIKE ? 
     ORDER BY created_at DESC LIMIT 50',
    [$userId, $searchPattern]
);

echo json_encode([
    'success' => true,
    'files' => $files ?: [],
    'folders' => $folders ?: [],
    'total' => count($files) + count($folders)
]);
?>
