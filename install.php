#!/usr/bin/env php
<?php
/**
 * BoohMetch Installation Script
 * Запуск: php install.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "╔════════════════════════════════════════════╗\n";
echo "║   BoohMetch - Cloud Storage Installer     ║\n";
echo "║   Установка приложения                    ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

$root = __DIR__;
$config = $root . '/config/config.php';

// Проверка PHP версии
echo "[1/5] Проверка версии PHP... ";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4', '>=')) {
    echo "✓ OK (v$phpVersion)\n";
} else {
    echo "✗ ОШИБКА: требуется PHP 7.4 или выше (у вас: $phpVersion)\n";
    exit(1);
}

// Проверка необходимых расширений
echo "[2/5] Проверка расширений PHP... ";
$required_extensions = ['pdo', 'curl', 'fileinfo', 'json'];
$missing = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}

if (empty($missing)) {
    echo "✓ OK\n";
} else {
    echo "✗ ОШИБКА: отсутствуют расширения: " . implode(', ', $missing) . "\n";
    exit(1);
}

// Создание директорий
echo "[3/5] Создание директорий и установка прав доступа... ";

$dirs = [
    'storage',
    'storage/database',
    'storage/uploads',
    'storage/uploads/temp',
    'storage/logs',
    'public/api'
];

foreach ($dirs as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    
    // Установка прав для записи
    if (in_array($dir, ['storage/uploads', 'storage/uploads/temp', 'storage/logs', 'storage/database'])) {
        chmod($path, 0777);
    }
}

echo "✓ OK\n";

// Инициализация БД
echo "[4/5] Инициализация базы данных... ";

require_once $config;

try {
    $dbPath = DB_PATH;
    $dbDir = dirname($dbPath);
    
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    
    // Создаём файл БД
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Выполняем SQL инициализацию
    $sqlFile = $root . '/storage/database/init.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "✓ OK\n";
    } else {
        echo "✗ ОШИБКА: файл init.sql не найден\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "✗ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

// Проверка конфигурации
echo "[5/5] Проверка конфигурации... ";

if (file_exists($config)) {
    echo "✓ OK\n";
} else {
    echo "✗ ОШИБКА: файл config.php не найден\n";
    exit(1);
}

echo "\n╔════════════════════════════════════════════╗\n";
echo "║   ✓ Установка успешно завершена!        ║\n";
echo "╚════════════════════════════════════════════╝\n\n";

echo "Следующие шаги:\n\n";
echo "1. Откройте http://localhost/BoohMetch/ в браузере\n";
echo "2. Зарегистрируйтесь с email и паролем\n";
echo "3. Получите Telegram Bot Token от @BotFather\n";
echo "4. Создайте приватный Telegram канал\n";
echo "5. Добавьте бота в канал как администратора\n";
echo "6. Получите Chat ID канала\n";
echo "7. Введите токен и Chat ID на странице подключения Telegram\n\n";

echo "Документация: https://github.com/yourusername/boohmetch\n";
echo "Поддержка: https://github.com/yourusername/boohmetch/issues\n\n";

?>
