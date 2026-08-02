<?php
/**
 * Класс для управления файлами и папками
 */

class FileManager
{
    private Database $db;
    private int $userId;
    private TelegramManager $telegramManager;

    public function __construct(Database $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->telegramManager = new TelegramManager($db, $userId);
    }

    /**
     * Создать новую папку
     */
    public function createFolder(string $name, ?int $parentFolderId = null): array
    {
        try {
            // Валидируем название папки
            if (empty($name) || strlen($name) > 255) {
                return ['success' => false, 'message' => 'Некорректное название папки'];
            }

            // Проверяем лимит файлов в папке
            if ($parentFolderId) {
                $count = $this->db->fetchOne(
                    'SELECT COUNT(*) as cnt FROM folders WHERE parent_id = ?',
                    [$parentFolderId]
                );
                if ($count['cnt'] >= MAX_FILES_PER_FOLDER) {
                    return ['success' => false, 'message' => 'Слишком много файлов в папке'];
                }
            }

            // Вставляем папку
            $this->db->query(
                'INSERT INTO folders (user_id, parent_id, name) VALUES (?, ?, ?)',
                [$this->userId, $parentFolderId, $name]
            );

            $folderId = $this->db->getLastInsertId();

            return [
                'success' => true,
                'message' => 'Папка создана успешно',
                'folder_id' => $folderId,
                'folder' => [
                    'id' => $folderId,
                    'name' => $name,
                    'parent_id' => $parentFolderId,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка создания папки: ' . $e->getMessage()];
        }
    }

    /**
     * Загрузить файл
     */
    public function uploadFile(array $file, ?int $folderId = null): array
    {
        try {
            // Валидируем файл
            $validationResult = $this->validateUpload($file);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            $originalName = $file['name'];
            $tmpPath = $file['tmp_name'];
            $fileSize = $file['size'];
            $mimeType = $file['type'];

            // Генерируем уникальное имя для хранилища
            $storedName = $this->generateFileName($originalName);
            $uploadDir = UPLOAD_TMP_DIR;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $storagePath = $uploadDir . $storedName;

            // Перемещаем файл
            if (!move_uploaded_file($tmpPath, $storagePath)) {
                return ['success' => false, 'message' => 'Ошибка перемещения файла'];
            }

            // Сохраняем информацию о файле в БД
            $this->db->query(
                'INSERT INTO files (user_id, folder_id, original_name, stored_name, mime_type, size) 
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$this->userId, $folderId, $originalName, $storedName, $mimeType, $fileSize]
            );

            $fileId = $this->db->getLastInsertId();

            // Добавляем файл в очередь загрузки в Telegram
            $this->db->query(
                'INSERT INTO upload_queue (user_id, file_id, local_path, status) 
                 VALUES (?, ?, ?, ?)',
                [$this->userId, $fileId, $storagePath, 'pending']
            );

            return [
                'success' => true,
                'message' => 'Файл загружен успешно',
                'file_id' => $fileId,
                'file' => [
                    'id' => $fileId,
                    'original_name' => $originalName,
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка загрузки: ' . $e->getMessage()];
        }
    }

    /**
     * Валидировать загруженный файл
     */
    private function validateUpload(array $file): array
    {
        // Проверяем наличие файла
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Файл не загружен'];
        }

        // Проверяем размер
        if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_SIZE) {
            $maxSizeGB = UPLOAD_MAX_SIZE / (1024 * 1024 * 1024);
            return ['success' => false, 'message' => "Размер файла превышает лимит ($maxSizeGB ГБ)"];
        }

        // Проверяем расширение
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, BLOCKED_EXTENSIONS)) {
            return ['success' => false, 'message' => "Файлы с расширением .$ext запрещены"];
        }

        // Проверяем MIME-тип
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            return ['success' => false, 'message' => "Тип файла не разрешен: $mimeType"];
        }

        return ['success' => true];
    }

    /**
     * Сгенерировать уникальное имя файла
     */
    private function generateFileName(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        return $randomName;
    }

    /**
     * Получить список файлов в папке
     */
    public function getFiles(?int $folderId = null, string $sortBy = 'created_at', string $order = 'DESC', int $limit = PAGINATION_LIMIT, int $offset = 0): array
    {
        try {
            $allowedSort = ['name', 'created_at', 'size'];
            $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'created_at';
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

            $sql = 'SELECT * FROM files 
                    WHERE user_id = ? AND is_deleted = 0';
            $params = [$this->userId];

            if ($folderId !== null) {
                $sql .= ' AND folder_id = ?';
                $params[] = $folderId;
            } else {
                $sql .= ' AND folder_id IS NULL';
            }

            $sql .= " ORDER BY $sortBy $order LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            return $this->db->fetchAll($sql, $params);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Получить список папок в родительской папке
     */
    public function getFolders(?int $parentFolderId = null): array
    {
        try {
            $sql = 'SELECT id, name, created_at FROM folders 
                    WHERE user_id = ?';
            $params = [$this->userId];

            if ($parentFolderId !== null) {
                $sql .= ' AND parent_id = ?';
                $params[] = $parentFolderId;
            } else {
                $sql .= ' AND parent_id IS NULL';
            }

            $sql .= ' ORDER BY name ASC';

            return $this->db->fetchAll($sql, $params);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Получить информацию о файле
     */
    public function getFile(int $fileId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM files WHERE id = ? AND user_id = ? AND is_deleted = 0',
            [$fileId, $this->userId]
        );
    }
    
    /**
     * Удалить файл (мягкое удаление)
     */
    public function deleteFile(int $fileId): array
    {
        try {
            $file = $this->getFile($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'Файл не найден'];
            }

            $this->db->query(
                'UPDATE files SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP 
                 WHERE id = ? AND user_id = ?',
                [$fileId, $this->userId]
            );

            return ['success' => true, 'message' => 'Файл удален'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка удаления: ' . $e->getMessage()];
        }
    }
    public function getFolderContent(?int $folderId = null, int $limit = 50, int $offset = 0): array
    {
        try {
            return [
                'folders' => $this->getFolders($folderId),
                'files'   => $this->getFiles($folderId, 'created_at', 'DESC', $limit, $offset)
            ];
        } catch (\Exception $e) {
            return [
                'folders' => [],
                'files'   => []
            ];
        }
    }
    /**
     * Переименовать файл
     */
    public function renameFile(int $fileId, string $newName): array
    {
        try {
            if (empty($newName) || strlen($newName) > 255) {
                return ['success' => false, 'message' => 'Некорректное название'];
            }

            $file = $this->getFile($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'Файл не найден'];
            }

            $this->db->query(
                'UPDATE files SET original_name = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ? AND user_id = ?',
                [$newName, $fileId, $this->userId]
            );

            return ['success' => true, 'message' => 'Файл переименован'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка переименования: ' . $e->getMessage()];
        }
    }

    /**
     * Добавить файл в избранное
     */
    public function toggleFavorite(int $fileId): array
    {
        try {
            $file = $this->getFile($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'Файл не найден'];
            }

            $newState = $file['is_favorite'] ? 0 : 1;

            $this->db->query(
                'UPDATE files SET is_favorite = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ? AND user_id = ?',
                [$newState, $fileId, $this->userId]
            );

            return ['success' => true, 'is_favorite' => $newState];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * Переименовать папку
     */
    public function renameFolder(int $folderId, string $newName): array
    {
        try {
            if (empty($newName) || strlen($newName) > 255) {
                return ['success' => false, 'message' => 'Некорректное название'];
            }

            $this->db->query(
                'UPDATE folders SET name = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ? AND user_id = ?',
                [$newName, $folderId, $this->userId]
            );

            return ['success' => true, 'message' => 'Папка переименована'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка переименования: ' . $e->getMessage()];
        }
    }

    /**
     * Удалить папку (и всё содержимое)
     */
    public function deleteFolder(int $folderId): array
    {
        try {
            // Мягко удаляем все файлы в этой папке
            $this->db->query(
                'UPDATE files SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP 
                 WHERE folder_id = ? AND user_id = ?',
                [$folderId, $this->userId]
            );

            // Рекурсивно удаляем подпапки (в данном случае просто удаляем саму папку)
            // В идеале тут должна быть рекурсия для всех уровней вложенности
            $this->db->query(
                'DELETE FROM folders WHERE id = ? AND user_id = ?',
                [$folderId, $this->userId]
            );

            return ['success' => true, 'message' => 'Папка удалена'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка удаления: ' . $e->getMessage()];
        }
    }

    /**
     * Поиск файлов
     */
    public function searchFiles(string $query, int $limit = PAGINATION_LIMIT): array
    {
        try {
            $searchTerm = '%' . $query . '%';
            return $this->db->fetchAll(
                'SELECT * FROM files 
                 WHERE user_id = ? AND is_deleted = 0 AND original_name LIKE ?
                 ORDER BY created_at DESC LIMIT ?',
                [$this->userId, $searchTerm, $limit]
            );

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Переместить файл или папку
     */
    public function moveItem(string $type, int $id, ?int $newParentId): array
    {
        try {
            $table = ($type === 'file') ? 'files' : 'folders';
            $parentColumn = ($type === 'file') ? 'folder_id' : 'parent_id';

            // Проверяем, что перемещаем не в саму себя (для папок)
            if ($type === 'folder' && $id === $newParentId) {
                return ['success' => false, 'message' => 'Нельзя переместить папку в саму себя'];
            }

            $this->db->query(
                "UPDATE $table SET $parentColumn = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?",
                [$newParentId, $id, $this->userId]
            );

            return ['success' => true, 'message' => 'Элемент перемещен'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка перемещения: ' . $e->getMessage()];
        }
    }

    /**
     * Копировать файл или папку
     */
    public function copyItem(string $type, int $id, ?int $newParentId): array
    {
        try {
            if ($type === 'file') {
                $file = $this->db->fetchOne('SELECT * FROM files WHERE id = ? AND user_id = ?', [$id, $this->userId]);
                if (!$file) return ['success' => false, 'message' => 'Файл не найден'];

                $this->db->query(
                    'INSERT INTO files (user_id, folder_id, original_name, stored_name, telegram_file_id, mime_type, size) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$this->userId, $newParentId, $file['original_name'] . ' (копия)', $file['stored_name'], $file['telegram_file_id'], $file['mime_type'], $file['size']]
                );
            } else {
                $folder = $this->db->fetchOne('SELECT * FROM folders WHERE id = ? AND user_id = ?', [$id, $this->userId]);
                if (!$folder) return ['success' => false, 'message' => 'Папка не найдена'];

                // Копируем саму папку
                $this->db->query(
                    'INSERT INTO folders (user_id, parent_id, name) VALUES (?, ?, ?)',
                    [$this->userId, $newParentId, $folder['name'] . ' (копия)']
                );
                $newFolderId = $this->db->getLastInsertId();

                // Копируем содержимое (рекурсивно было бы лучше, но для начала так)
                $subFiles = $this->db->fetchAll('SELECT id FROM files WHERE folder_id = ? AND user_id = ?', [$id, $this->userId]);
                foreach ($subFiles as $sf) {
                    $this->copyItem('file', $sf['id'], $newFolderId);
                }
                
                $subFolders = $this->db->fetchAll('SELECT id FROM folders WHERE parent_id = ? AND user_id = ?', [$id, $this->userId]);
                foreach ($subFolders as $sf) {
                    $this->copyItem('folder', $sf['id'], $newFolderId);
                }
            }

            return ['success' => true, 'message' => 'Элемент скопирован'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка копирования: ' . $e->getMessage()];
        }
    }

    /**
     * Получить недавние файлы
     */
    public function getRecentFiles(int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM files 
             WHERE user_id = ? AND is_deleted = 0
             ORDER BY updated_at DESC LIMIT ?',
            [$this->userId, $limit]
        );
    }

    /**
     * Получить избранные файлы
     */
    public function getFavoriteFiles(int $limit = PAGINATION_LIMIT): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM files 
             WHERE user_id = ? AND is_deleted = 0 AND is_favorite = 1
             ORDER BY created_at DESC LIMIT ?',
            [$this->userId, $limit]
        );
    }

    /**
     * Получить хлебные крошки (путь к папке)
     */
    public function getBreadcrumbs(?int $folderId = null): array
    {
        $breadcrumbs = [];
        $currentId = $folderId;

        while ($currentId) {
            $folder = $this->db->fetchOne(
                'SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?',
                [$currentId, $this->userId]
            );

            if (!$folder) break;

            array_unshift($breadcrumbs, $folder);
            $currentId = $folder['parent_id'];
        }

        return $breadcrumbs;
    }

    /**
     * Получить название папки
     */
    public function getFolderName(?int $folderId = null): string
    {
        if (!$folderId) return 'Мой диск';

        $folder = $this->db->fetchOne(
            'SELECT name FROM folders WHERE id = ? AND user_id = ?',
            [$folderId, $this->userId]
        );

        return $folder ? $folder['name'] : 'Папка';
    }

    /**
     * Проверить, существует ли папка для пользователя
     */
    public function folderExists(int $folderId): bool
    {
        $result = $this->db->fetchOne(
            'SELECT id FROM folders WHERE id = ? AND user_id = ?',
            [$folderId, $this->userId]
        );
        return !!$result;
    }
}

?>
