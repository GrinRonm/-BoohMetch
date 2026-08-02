<?php
/**
 * API контроллер для операций с файлами (удаление, переименование, избранное)
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

// Парсим URL для действия
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = explode('/', $request);

$action = $parts[2] ?? '';  // file
$operation = $parts[3] ?? '';  // delete, rename, favorite
$fileId = isset($parts[4]) ? (int)$parts[4] : 0;

// Health check endpoint
if ($action === 'ping') {
    echo json_encode(['success' => true, 'message' => 'pong']);
    exit;
}

switch ($operation) {
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
            exit;
        }
        $result = $fileManager->deleteFile($fileId);
        echo json_encode($result);
        break;

    case 'rename':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $newName = $data['name'] ?? '';
        $result = $fileManager->renameFile($fileId, $newName);
        echo json_encode($result);
        break;

    case 'favorite':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
            exit;
        }
        $result = $fileManager->toggleFavorite($fileId);
        echo json_encode($result);
        break;

    case 'move':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $newParentId = isset($data['new_parent_id']) ? (int)$data['new_parent_id'] : null;
        $result = $fileManager->moveItem('file', $fileId, $newParentId);
        echo json_encode($result);
        break;

    case 'copy':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $newParentId = isset($data['new_parent_id']) ? (int)$data['new_parent_id'] : null;
        $result = $fileManager->copyItem('file', $fileId, $newParentId);
        echo json_encode($result);
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Не найдено']);
}
?>
