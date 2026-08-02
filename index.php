<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On'); 

/**
 * Главный контроллер приложения BoohMetch
 * Находится в корневой папке проекта
 */

// Подключаем конфиг
require_once __DIR__ . '/config/config.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        APP_ROOT . 'src/classes/' . $class . '.php',
        APP_ROOT . 'src/controllers/' . $class . '.php',
        APP_ROOT . 'src/helpers/' . $class . '.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Инициализируем базу данных
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Ошибка подключения к базе данных: ' . $e->getMessage();
    exit;
}

// Инициализируем авторизацию
$auth = new Auth($db);

// === ГЛОБАЛЬНАЯ ОБРАБОТКА ОШИБОК ===
// Устанавливаем обработчик фатальных ошибок
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && (E_ERROR & $error['type'])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка сервера: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

// Устанавливаем handler для exceptions
set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
});

// JSON response helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Получаем маршрут
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Парсим URL для получения пути
$urlParts = parse_url($request);
$route = rtrim($urlParts['path'], '/') ?: '/';
parse_str($urlParts['query'] ?? '', $_GET);

// === МАРШРУТИЗАЦИЯ ===

// Главная страница
if ($route === '/') {
    if ($auth->isAuthenticated()) {
        $userData = $auth->getUserData();
        if (!$userData['telegram_chat_id']) {
            header('Location: /telegram-setup');
            exit;
        }
        include APP_ROOT . 'views/drive.php';
    } else {
        header('Location: /login');
    }
    exit;
}

// Логин
elseif ($route === '/login') {
    if ($auth->isAuthenticated()) {
        header('Location: /');
        exit;
    }

    $error = '';
    if ($method === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $result = $auth->login($email, $password);

        if ($result['success']) {
            header('Location: /');
            exit;
        } else {
            $error = $result['message'];
        }
    }

    include APP_ROOT . 'views/login.php';
    exit;
}

// Регистрация
elseif ($route === '/register') {
    if ($auth->isAuthenticated()) {
        header('Location: /');
        exit;
    }

    $error = '';
    if ($method === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $result = $auth->register($email, $password);

        if ($result['success']) {
            header('Location: /telegram-setup');
            exit;
        } else {
            $error = $result['message'];
        }
    }

    include APP_ROOT . 'views/register.php';
    exit;
}

// Логаут
elseif ($route === '/logout') {
    $auth->logout();
    header('Location: /login');
    exit;
}

// Установка Telegram
elseif ($route === '/telegram-setup') {
    if (!$auth->isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    $userId = $auth->getUserId();
    $error = '';
    $success = '';

    if ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        $telegramManager = new TelegramManager($db, $userId);

        if ($action === 'save') {
            $token = $_POST['telegram_token'] ?? '';
            $chatId = $_POST['telegram_chat_id'] ?? '';

            if (empty($token) || empty($chatId)) {
                $error = 'Заполните все поля';
            } else {
                $result = $telegramManager->saveTelegramCredentials($token, $chatId);
                if ($result['success']) {
                    $success = $result['message'];
                    $_SESSION['telegram_connected'] = true;
                } else {
                    $error = $result['message'];
                }
            }
        }
    }

    include APP_ROOT . 'views/telegram-setup.php';
    exit;
}

// Настройки
elseif ($route === '/settings') {
    if (!$auth->isAuthenticated()) {
        header('Location: /login');
        exit;
    }

    include APP_ROOT . 'views/settings.php';
    exit;
}

// API маршруты
elseif (preg_match('/^\/api\/(.*)/', $route, $matches)) {
    $apiRoute = $matches[1];
    
    if ($apiRoute === 'upload.php' || $apiRoute === 'upload') {
        include APP_ROOT . 'public/api/upload.php';
    } elseif (preg_match('/^preview/', $apiRoute)) {
        include APP_ROOT . 'public/api/preview.php';
    } elseif (preg_match('/^search/', $apiRoute)) {
        include APP_ROOT . 'public/api/search.php';
    } elseif (preg_match('/^breadcrumbs/', $apiRoute)) {
        include APP_ROOT . 'public/api/breadcrumbs.php';
    } elseif (preg_match('/^file/', $apiRoute)) {
        include APP_ROOT . 'public/api/file.php';
    } elseif (preg_match('/^folder/', $apiRoute)) {
        include APP_ROOT . 'public/api/folder.php';
    } elseif (preg_match('/^download/', $apiRoute)) {
        include APP_ROOT . 'public/api/download.php';
    } elseif (preg_match('/^share/', $apiRoute)) {
        include APP_ROOT . 'public/api/share.php';
    }
    exit;
}

// Публичная ссылка на файл/папку
elseif (preg_match('/^\/share\/([a-z0-9]+)$/i', $route, $matches)) {
    $token = $matches[1];
    $shareManager = new ShareManager($db, 0);

    $share = $db->fetchOne(
        'SELECT s.*, f.id as file_id, fo.id as folder_id 
         FROM shares s
         LEFT JOIN files f ON s.file_id = f.id
         LEFT JOIN folders fo ON s.folder_id = fo.id
         WHERE s.share_token = ?',
        [$token]
    );

    if (!$share) {
        http_response_code(404);
        echo 'Ссылка не найдена или истекла';
        exit;
    }

    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        http_response_code(410);
        echo 'Ссылка истекла';
        exit;
    }

    if ($share['file_id']) {
        $sharedFile = $db->fetchOne('SELECT * FROM files WHERE id = ?', [$share['file_id']]);
        
        if (!$sharedFile) {
            http_response_code(404);
            echo 'Файл не найден';
            exit;
        }

        if ($_GET['download'] ?? false) {
            if (!$share['allow_download']) {
                http_response_code(403);
                echo 'Скачивание не разрешено';
                exit;
            }

            $db->query('UPDATE shares SET access_count = access_count + 1 WHERE id = ?', [$share['id']]);

            $telegramManager = new TelegramManager($db, $share['user_id']);
            $downloadUrl = $telegramManager->downloadFileFromTelegram($sharedFile['telegram_file_id']);

            if ($downloadUrl) {
                header('Location: ' . $downloadUrl);
            } else {
                http_response_code(410);
                echo 'Файл больше не доступен';
            }
            exit;
        }

        include APP_ROOT . 'views/shared-file.php';
    } elseif ($share['folder_id']) {
        $sharedFolder = $db->fetchOne('SELECT * FROM folders WHERE id = ?', [$share['folder_id']]);
        
        if (!$sharedFolder) {
            http_response_code(404);
            echo 'Папка не найдена';
            exit;
        }

        include APP_ROOT . 'views/shared-folder.php';
    }
    exit;
}

// 404 - страница не найдена
else {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - BoohMetch</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container d-flex align-items-center justify-content-center" style="height: 100vh;">
            <div class="text-center">
                <h1 class="display-1 fw-bold text-primary">404</h1>
                <p class="fs-3"><strong>Страница не найдена</strong></p>
                <p class="lead">Запрошенная страница не существует или была удалена.</p>
                <a href="/" class="btn btn-primary mt-3">Вернуться на главную</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
