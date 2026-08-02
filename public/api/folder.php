<?php
/**
 * API контроллер для операций с папками
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

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $request);

$action = $parts[2] ?? '';  // folder
$operation = $parts[3] ?? '';  // create, delete, rename
$folderId = isset($parts[4]) ? (int)$parts[4] : 0;

if ($operation === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    $parentId = isset($data['parent_id']) ? (int)$data['parent_id'] : null;
    
    $result = $fileManager->createFolder($name, $parentId);
    echo json_encode($result);
} elseif ($operation === 'delete' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $result = $fileManager->deleteFolder($folderId);
    echo json_encode($result);
} elseif ($operation === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    $result = $fileManager->renameFolder($folderId, $name);
    echo json_encode($result);
} elseif ($operation === 'move' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newParentId = isset($data['new_parent_id']) ? (int)$data['new_parent_id'] : null;
    $result = $fileManager->moveItem('folder', $folderId, $newParentId);
    echo json_encode($result);
} elseif ($operation === 'copy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newParentId = isset($data['new_parent_id']) ? (int)$data['new_parent_id'] : null;
    $result = $fileManager->copyItem('folder', $folderId, $newParentId);
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
}
?>
