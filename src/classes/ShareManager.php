<?php
/**
 * Класс для управления ссылками поделиться
 */

class ShareManager
{
    private Database $db;
    private int $userId;

    public function __construct(Database $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * Создать ссылку поделиться
     */
    public function createShare(?int $fileId, ?int $folderId, int $expiryDays = 0, bool $allowDownload = true): array
    {
        try {
            if (!$fileId && !$folderId) {
                return ['success' => false, 'message' => 'Укажите файл или папку'];
            }

            // Проверяем, что это файл/папка пользователя
            if ($fileId) {
                $file = $this->db->fetchOne('SELECT id FROM files WHERE id = ? AND user_id = ?', [$fileId, $this->userId]);
                if (!$file) {
                    return ['success' => false, 'message' => 'Файл не найден'];
                }
            }

            if ($folderId) {
                $folder = $this->db->fetchOne('SELECT id FROM folders WHERE id = ? AND user_id = ?', [$folderId, $this->userId]);
                if (!$folder) {
                    return ['success' => false, 'message' => 'Папка не найдена'];
                }
            }

            // Генерируем токен
            $shareToken = bin2hex(random_bytes(16));

            // Вычисляем дату истечения
            $expiresAt = null;
            if ($expiryDays > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));
            }

            // Сохраняем ссылку
            $this->db->query(
                'INSERT INTO shares (user_id, file_id, folder_id, share_token, allow_download, expires_at) 
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$this->userId, $fileId, $folderId, $shareToken, $allowDownload ? 1 : 0, $expiresAt]
            );

            $shareId = $this->db->getLastInsertId();

            // Генерируем URL
            $shareUrl = $_SERVER['HTTP_HOST'] . '/share/' . $shareToken;

            return [
                'success' => true,
                'message' => 'Ссылка создана',
                'share_id' => $shareId,
                'share_token' => $shareToken,
                'share_url' => $shareUrl
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка создания ссылки: ' . $e->getMessage()];
        }
    }

    /**
     * Получить ссылку по токену (для публичного доступа)
     */
    public function getShareByToken(string $token): ?array
    {
        try {
            $share = $this->db->fetchOne('SELECT * FROM shares WHERE share_token = ?', [$token]);

            if (!$share) {
                return null;
            }

            // Проверяем, не истекла ли ссылка
            if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
                return null;
            }

            return $share;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить доступ к файлу по ссылке поделиться
     */
    public function getSharedFile(string $token): ?array
    {
        try {
            $share = $this->getShareByToken($token);

            if (!$share || !$share['file_id']) {
                return null;
            }

            // Увеличиваем счётчик доступов
            $this->db->query(
                'UPDATE shares SET access_count = access_count + 1 WHERE id = ?',
                [$share['id']]
            );

            // Получаем информацию о файле
            $file = $this->db->fetchOne('SELECT * FROM files WHERE id = ?', [$share['file_id']]);

            return $file;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить доступ к папке по ссылке поделиться
     */
    public function getSharedFolder(string $token): ?array
    {
        try {
            $share = $this->getShareByToken($token);

            if (!$share || !$share['folder_id']) {
                return null;
            }

            // Увеличиваем счётчик доступов
            $this->db->query(
                'UPDATE shares SET access_count = access_count + 1 WHERE id = ?',
                [$share['id']]
            );

            // Получаем информацию о папке
            $folder = $this->db->fetchOne('SELECT * FROM folders WHERE id = ?', [$share['folder_id']]);

            // Получаем файлы в папке
            $files = $this->db->fetchAll(
                'SELECT * FROM files WHERE folder_id = ? AND is_deleted = 0 ORDER BY created_at DESC',
                [$share['folder_id']]
            );

            return [
                'folder' => $folder,
                'files' => $files
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить все ссылки пользователя
     */
    public function getUserShares(): array
    {
        try {
            return $this->db->fetchAll(
                'SELECT s.*, 
                        f.original_name as file_name,
                        fo.name as folder_name
                 FROM shares s
                 LEFT JOIN files f ON s.file_id = f.id
                 LEFT JOIN folders fo ON s.folder_id = fo.id
                 WHERE s.user_id = ?
                 ORDER BY s.created_at DESC',
                [$this->userId]
            );

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Удалить ссылку
     */
    public function deleteShare(int $shareId): array
    {
        try {
            // Проверяем, что это ссылка пользователя
            $share = $this->db->fetchOne('SELECT id FROM shares WHERE id = ? AND user_id = ?', [$shareId, $this->userId]);

            if (!$share) {
                return ['success' => false, 'message' => 'Ссылка не найдена'];
            }

            $this->db->query('DELETE FROM shares WHERE id = ?', [$shareId]);

            return ['success' => true, 'message' => 'Ссылка удалена'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка удаления: ' . $e->getMessage()];
        }
    }

    /**
     * Проверить, позволяет ли ссылка скачивание
     */
    public function isDownloadAllowed(string $token): bool
    {
        $share = $this->getShareByToken($token);
        return $share && $share['allow_download'];
    }
}

?>
