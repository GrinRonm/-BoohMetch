<?php
/**
 * Вспомогательные функции приложения
 */

/**
 * ПолучитьIcon для типа файла
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fa-file-pdf text-danger',
        'doc' => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls' => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'jpg' => 'fa-file-image text-warning',
        'jpeg' => 'fa-file-image text-warning',
        'png' => 'fa-file-image text-warning',
        'gif' => 'fa-file-image text-warning',
        'zip' => 'fa-file-archive text-secondary',
        'rar' => 'fa-file-archive text-secondary',
        '7z' => 'fa-file-archive text-secondary',
        'txt' => 'fa-file-alt text-muted',
        'mp3' => 'fa-file-audio text-info',
        'mp4' => 'fa-file-video text-danger',
        'avi' => 'fa-file-video text-danger',
        'mov' => 'fa-file-video text-danger'
    ];
    
    return $icons[$ext] ?? 'fa-file text-muted';
}

/**
 * Форматировать размер файла в читаемый формат
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes === 0) return '0 Б';
    
    $k = 1024;
    $sizes = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $i = floor(log($bytes, $k));
    
    return round($bytes / pow($k, $i), $precision) . ' ' . $sizes[$i];
}

/**
 * Проверить, авторизован ли пользователь
 */
function isAuth($auth) {
    return $auth && $auth->isAuthenticated();
}

/**
 * Получить текущего пользователя
 */
function getCurrentUser($auth) {
    return $auth ? $auth->getUserData() : null;
}

/**
 * Получить ID текущего пользователя
 */
function getCurrentUserId($auth) {
    return $auth ? $auth->getUserId() : null;
}

/**
 * Сгенерировать безопасный токен
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Валидировать email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидировать пароль
 */
function validatePassword($password) {
    return strlen($password) >= 6;
}

/**
 * Валидировать имя файла
 */
function validateFilename($filename) {
    // Максимальная длина имени файла
    if (strlen($filename) > 255) {
        return false;
    }
    
    // Запрещённые символы
    $forbidden = ['<', '>', ':', '"', '/', '\\', '|', '?', '*'];
    foreach ($forbidden as $char) {
        if (strpos($filename, $char) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Получить расширение файла
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Получить имя файла без расширения
 */
function getFilenameWithoutExtension($filename) {
    return pathinfo($filename, PATHINFO_FILENAME);
}

/**
 * Проверить, разрешено ли расширение
 */
function isExtensionAllowed($filename) {
    $ext = getFileExtension($filename);
    
    // Проверяем запрещённые расширения
    if (in_array($ext, BLOCKED_EXTENSIONS)) {
        return false;
    }
    
    return true;
}

/**
 * Получить MIME-тип файла
 */
function getMimeType($filename) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filename);
    finfo_close($finfo);
    return $mimeType;
}

/**
 * Проверить, существует ли файл/папка
 */
function itemExists($db, $userId, $itemId, $type = 'file') {
    $table = $type === 'file' ? 'files' : 'folders';
    $result = $db->fetchOne(
        "SELECT id FROM $table WHERE id = ? AND user_id = ?",
        [$itemId, $userId]
    );
    return $result !== null;
}

/**
 * Получить путь к файлу
 */
function getFilePath($filename) {
    return UPLOAD_TMP_DIR . $filename;
}

/**
 * Удалить файл с сервера
 */
function deleteLocalFile($filename) {
    $filepath = getFilePath($filename);
    
    if (file_exists($filepath)) {
        return @unlink($filepath);
    }
    
    return false;
}

/**
 * Логирование действия
 */
function logAction($action, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] $action";
    
    if ($details) {
        $message .= " - $details";
    }
    
    log_message($message, 'action');
}

/**
 * Проверить, является ли путь безопасным (от path traversal)
 */
function isSafePath($path) {
    // Проверяем на попытки обхода директории
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    // Проверяем на абсолютные пути
    if ($path[0] === '/' || (strlen($path) > 1 && $path[1] === ':')) {
        return false;
    }
    
    return true;
}

/**
 * Перенаправить на URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Отправить JSON ответ
 */
function sendJsonResponse($success = true, $message = '', $data = []) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Получить значение из GET с санитизацией
 */
function getParam($name, $default = null) {
    return isset($_GET[$name]) ? htmlspecialchars($_GET[$name]) : $default;
}

/**
 * Получить значение из POST с санитизацией
 */
function postParam($name, $default = null) {
    return isset($_POST[$name]) ? htmlspecialchars($_POST[$name]) : $default;
}

/**
 * Получить JSON данные из тела запроса
 */
function getJsonBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Проверить, является ли запрос AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Форматировать дату
 */
function formatDate($date, $format = 'd.m.Y H:i') {
    try {
        return date($format, strtotime($date));
    } catch (\Exception $e) {
        return '';
    }
}

/**
 * Получить дату в ISO 8601 формате
 */
function getIsoDate($date = null) {
    $time = $date ? strtotime($date) : time();
    return date('c', $time);
}

/**
 * Проверить, истекла ли ссылка общего доступа
 */
function isShareExpired($expiresAt) {
    if (empty($expiresAt)) {
        return false;
    }
    
    $expiresTime = strtotime($expiresAt);
    return $expiresTime < time();
}

/**
 * Получить статус сессии Telegram
 */
function getTelegramStatus($user) {
    if (!$user || !isset($user['telegram_chat_id'])) {
        return 'not_connected';
    }
    
    if (empty($user['telegram_chat_id'])) {
        return 'not_connected';
    }
    
    return 'connected';
}

?>
