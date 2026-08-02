<?php
/**
 * API контроллер для загрузки файлов
 */

// Включаем конфиг и проверяем авторизацию
require_once dirname(__DIR__, 2) . '/config/config.php';

// Автозагрузка
spl_autoload_register(function ($class) {
    $paths = [
        APP_ROOT . 'src/classes/' . $class . '.php',
        APP_ROOT . 'src/controllers/' . $class . '.php',
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

// Обработка загрузки файла
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['files'])) {
        echo json_encode(['success' => false, 'message' => 'Файлы не предоставлены']);
        exit;
    }

    $folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    $uploadedCount = 0;
    $errors = [];

    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        $file = [
            'name' => $_FILES['files']['name'][$key],
            'type' => $_FILES['files']['type'][$key],
            'tmp_name' => $tmp_name,
            'error' => $_FILES['files']['error'][$key],
            'size' => $_FILES['files']['size'][$key]
        ];

        $result = $fileManager->uploadFile($file, $folderId);
        
        if ($result['success']) {
            $uploadedCount++;
        } else {
            $errors[] = $file['name'] . ': ' . $result['message'];
        }
    }

    $message = "Загружено $uploadedCount файл(ов)";
    if ($errors) {
        $message .= '. Ошибки: ' . implode(', ', $errors);
    }

    echo json_encode([
        'success' => $uploadedCount > 0,
        'message' => $message,
        'uploaded' => $uploadedCount,
        'errors' => $errors
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод']);
}
?>
