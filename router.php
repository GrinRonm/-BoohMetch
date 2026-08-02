<?php
/**
 * PHP Built-in Server Router
 * Используется для работы встроенного сервера PHP
 * 
 * Запуск: php -S localhost:8000 router.php
 */

// Список файлов/папок которые должны обслуживаться прямо
$public_paths = [
    '/public/assets/',
    '/storage/logs/',
];

$requested_file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requested_file_path = realpath($requested_file);

// Проверяем, существует ли реальный файл и он в пределах корневой директории
if ($requested_file_path !== false &&
    is_file($requested_file_path) &&
    strpos($requested_file_path, realpath(__DIR__)) === 0 &&
    is_readable($requested_file_path)) {
    
    // Проверяем расширение файла
    $file_ext = pathinfo($requested_file_path, PATHINFO_EXTENSION);
    
    // Список допустимых расширений для直接 доступа
    $allowed_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'html', 'xml', 'json'];
    
    if (in_array($file_ext, $allowed_extensions)) {
        return false; // Пусть встроенный сервер обслужит файл
    }
}

// Все остальные запросы идут через index.php
require_once __DIR__ . '/index.php';
