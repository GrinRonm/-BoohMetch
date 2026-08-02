<?php
/**
 * Скрипт обработки очереди загрузок в Telegram
 * Должен запускаться через cron каждую минуту: * * * * * /usr/bin/php /var/www/BoohMetch/src/cron/process_uploads.php
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

try {
    $db = Database::getInstance();

    // Получаем файлы из очереди на загрузку
    $queueItems = $db->fetchAll(
        'SELECT * FROM upload_queue 
         WHERE status = ? AND attempt_count < 3
         ORDER BY created_at ASC LIMIT 10',
        ['pending']
    );

    foreach ($queueItems as $item) {
        try {
            log_message("Processing file " . $item['file_id'] . " for user " . $item['user_id'], 'info');

            $telegramManager = new TelegramManager($db, $item['user_id']);
            $result = $telegramManager->sendFileToTelegram($item['file_id']);

            if ($result['success']) {
                log_message("File " . $item['file_id'] . " uploaded to Telegram", 'info');
            } else {
                log_message("Failed to upload file " . $item['file_id'] . ": " . $result['message'], 'error');
            }
        } catch (\Exception $e) {
            log_message("Error processing file " . $item['file_id'] . ": " . $e->getMessage(), 'error');
        }
    }

    // Очищаем старые завершённые записи
    $db->query(
        'DELETE FROM upload_queue 
         WHERE status = ? AND updated_at < datetime("now", "-7 days")',
        ['completed']
    );

    log_message("Queue processing completed. Items processed: " . count($queueItems), 'info');

} catch (\Exception $e) {
    log_message("Queue processing error: " . $e->getMessage(), 'error');
}
?>
