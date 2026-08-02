<?php
/**
 * Класс для работы с Telegram API и интеграцией
 */

class TelegramManager
{
    private Database $db;
    private string $botToken;
    private string $chatId;
    private int $userId;

    public function __construct(Database $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->loadUserTelegramData();
    }

    /**
     * Загрузить данные Telegram пользователя
     */
    private function loadUserTelegramData(): void
    {
        $user = $this->db->fetchOne('SELECT telegram_chat_id, telegram_token FROM users WHERE id = ?', [$this->userId]);
        
        if ($user) {
            $this->chatId = $user['telegram_chat_id'] ?? '';
            $this->botToken = $this->decryptToken($user['telegram_token'] ?? '');
        }
    }

    /**
     * Сохранить токен бота и Chat ID
     */
    public function saveTelegramCredentials(string $token, string $chatId): array
    {
        try {
            // Тестируем подключение
            $this->botToken = $token;
            $this->chatId = $chatId;

            $testResult = $this->sendTestMessage('✅ Тестовое сообщение из BoohMetch');
            
            if (!$testResult['success']) {
                return $testResult;
            }

            // Шифруем токен перед сохранением
            $encryptedToken = $this->encryptToken($token);

            // Сохраняем в БД
            $this->db->query(
                'UPDATE users SET telegram_chat_id = ?, telegram_token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$chatId, $encryptedToken, $this->userId]
            );

            return ['success' => true, 'message' => 'Telegram подключен успешно'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при сохранении: ' . $e->getMessage()];
        }
    }

    /**
     * Отправить файл в Telegram по его ID из базы
     */
    public function sendFileToTelegram(int $fileId): array
    {
        try {
            // Получаем информацию о файле и пути из очереди
            $item = $this->db->fetchOne(
                'SELECT q.*, f.original_name 
                 FROM upload_queue q 
                 JOIN files f ON q.file_id = f.id 
                 WHERE q.file_id = ? AND q.user_id = ?',
                [$fileId, $this->userId]
            );

            if (!$item) {
                return ['success' => false, 'message' => 'Запись в очереди не найдена'];
            }

            if (!file_exists($item['local_path'])) {
                return ['success' => false, 'message' => 'Локальный файл не найден: ' . $item['local_path']];
            }

            // Отправляем файл
            $uploadResult = $this->sendFile($item['local_path'], $item['original_name']);

            if ($uploadResult['success']) {
                $telegramFileId = $uploadResult['file_id'];

                // Обновляем информацию о файле
                $this->db->query(
                    'UPDATE files SET telegram_file_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$telegramFileId, $fileId]
                );

                // Помечаем задачу в очереди как выполненную
                $this->db->query(
                    'UPDATE upload_queue SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    ['completed', $item['id']]
                );

                // Удаляем локальный файл после успешной загрузки
                unlink($item['local_path']);

                return ['success' => true, 'message' => 'Файл успешно отправлен в Telegram и удален локально'];
            } else {
                // Увеличиваем количество попыток при ошибке
                $this->db->query(
                    'UPDATE upload_queue SET attempt_count = attempt_count + 1, error_message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$uploadResult['message'], $item['id']]
                );

                return $uploadResult;
            }

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при обработке файла: ' . $e->getMessage()];
        }
    }

    /**
     * Отправить тестовое сообщение
     */
    public function sendTestMessage(string $text): array
    {
        $response = $this->makeRequest('sendMessage', [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

        return $response;
    }

    /**
     * Отправить файл в Telegram
     */
    public function sendFile(string $filePath, string $fileName): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Файл не найден', 'file_id' => null];
        }

        try {
            $fileHandle = fopen($filePath, 'r');
            
            $postData = [
                'chat_id' => $this->chatId,
                'document' => new \CURLFile($filePath, mime_content_type($filePath), $fileName)
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, TELEGRAM_API_URL . '/bot' . $this->botToken . '/sendDocument');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => 'Ошибка Telegram API', 'file_id' => null];
            }

            $data = json_decode($response, true);

            if (!$data['ok']) {
                $errorMessage = $data['description'] ?? 'Неизвестная ошибка';
                return ['success' => false, 'message' => $errorMessage, 'file_id' => null];
            }

            $fileId = $data['result']['document']['file_id'] ?? null;

            return [
                'success' => true,
                'message' => 'Файл успешно отправлен',
                'file_id' => $fileId
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка отправки: ' . $e->getMessage(), 'file_id' => null];
        }
    }

    /**
     * Получить ссылку на файл из Telegram
     */
    public function downloadFileFromTelegram(string $fileId): ?string
    {
        $response = $this->makeRequest('getFile', ['file_id' => $fileId]);

        if (!$response['success']) {
            return null;
        }

        $filePath = $response['data']['file_path'] ?? null;
        if (!$filePath) {
            return null;
        }

        return TELEGRAM_API_URL . '/file/bot' . $this->botToken . '/' . $filePath;
    }

    /**
     * Скачать файл из Telegram
     */
    public function downloadFile(string $fileId, string $destinationPath): array
    {
        $fileUrl = $this->downloadFileFromTelegram($fileId);

        if (!$fileUrl) {
            return ['success' => false, 'message' => 'Не удалось получить ссылку на файл'];
        }

        try {
            $fileContent = file_get_contents($fileUrl);
            
            if ($fileContent === false) {
                return ['success' => false, 'message' => 'Ошибка скачивания файла'];
            }

            if (!file_put_contents($destinationPath, $fileContent)) {
                return ['success' => false, 'message' => 'Ошибка сохранения файла'];
            }

            return ['success' => true, 'message' => 'Файл успешно скачан'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * Сделать запрос к Telegram API
     */
    private function makeRequest(string $method, array $params): array
    {
        try {
            $url = TELEGRAM_API_URL . '/bot' . $this->botToken . '/' . $method;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'message' => "Ошибка API (код: $httpCode)"];
            }

            $data = json_decode($response, true);

            if (!$data['ok']) {
                $errorMessage = $data['description'] ?? 'Неизвестная ошибка';
                return ['success' => false, 'message' => $errorMessage];
            }

            return ['success' => true, 'data' => $data['result'] ?? []];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * Зашифровать токен
     */
    private function encryptToken(string $token): string
    {
        return base64_encode(openssl_encrypt($token, 'AES-256-CBC', ENCRYPTION_KEY, false, substr(md5(ENCRYPTION_KEY), 0, 16)));
    }

    /**
     * Расшифровать токен
     */
    private function decryptToken(string $encryptedToken): string
    {
        try {
            $decoded = base64_decode($encryptedToken);
            return openssl_decrypt($decoded, 'AES-256-CBC', ENCRYPTION_KEY, false, substr(md5(ENCRYPTION_KEY), 0, 16));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Проверить, подключен ли Telegram
     */
    public function isConnected(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }
}

?>
