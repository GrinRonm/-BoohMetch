<?php
/**
 * Основная конфигурация приложения BoohMetch
 */

// Определяем корневую директорию приложения
const APP_ROOT = __DIR__ . '/../';

// Режим отладки
const DEBUG = true;

// Минимальная версия PHP: 7.4+
define('MIN_PHP_VERSION', '7.4');

// Параметры базы данных
const DB_PATH = APP_ROOT . 'storage/database/database.sqlite';

// Параметры загрузки файлов
const UPLOAD_MAX_SIZE = 2 * 1024 * 1024 * 1024; // 2 ГБ
const UPLOAD_TMP_DIR = APP_ROOT . 'storage/uploads/';
const UPLOAD_CHUNK_SIZE = 10 * 1024 * 1024; // 10 МБ

// Запрещённые расширения файлов
const BLOCKED_EXTENSIONS = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
    'exe', 'sh', 'bat', 'cmd', 'com', 'pif', 'scr',
    'js', 'jar', 'app', 'deb', 'rpm', 'dmg', 'msi',
    'mach', 'dll', 'so', 'dylib', 'ps1', 'psm1'
];

// Параметры сессии
const SESSION_TIMEOUT = 24 * 60 * 60; // 24 часа
const SESSION_NAME = 'BOOHMETCH_SESSION';

// Telegram BOT API
const TELEGRAM_API_URL = 'https://api.telegram.org';

// Пользовательские параметры ограничения
const MAX_FILES_PER_FOLDER = 10000;
const PAGINATION_LIMIT = 50;

// Шифрование токенов
const ENCRYPTION_KEY = 'your-secret-encryption-key-change-this'; // Измени на более безопасный ключ!

// Допустимые MIME-типы
const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
    'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac',
    'application/pdf', 'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv', 'text/html',
    'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
    'application/json', 'application/xml'
];

// Временная зона
date_default_timezone_set('UTC');

// Язык интерфейса
const LANG = 'ru';

// Инициализация сессии (только если не в CLI режиме и если не требуется инициализация БД)
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Подключаем помощник функции
require_once APP_ROOT . 'src/helpers/functions.php';

// Логирование
function log_message($message, $type = 'info') {
    $log_dir = APP_ROOT . 'storage/logs/';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $log_file = $log_dir . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$type] $message\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Обработка ошибок
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (DEBUG) {
        echo "Error: $errstr in $errfile on line $errline\n";
    }
    log_message("$errstr in $errfile on line $errline", 'error');
});

?>
