<?php
/**
 * API для получения хлебных крошек (breadcrumbs)
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
$fileManager = new FileManager($db, $userId);

$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

if ($folderId && !$fileManager->folderExists($folderId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

$breadcrumbs = [];
if ($folderId) {
    $breadcrumbs = $fileManager->getBreadcrumbs($folderId);
}

echo json_encode([
    'success' => true,
    'breadcrumbs' => $breadcrumbs
]);
?>
