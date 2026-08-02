<?php
/**
 * Главная страница - облачное хранилище (интерфейс Google Drive)
 */

// Проверяем авторизацию
if (!isset($auth) || !$auth->isAuthenticated()) {
    header('Location: /login');
    exit;
}

$userId = $auth->getUserId();
$userData = $auth->getUserData();

// Получаем содержимое в зависимости от вида
$fileManager = new FileManager($db, $userId);
$view = $_GET['view'] ?? 'drive';
$folderId = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Проверяем безопасность - папка должна принадлежать пользователю
if ($folderId && !$fileManager->folderExists($folderId)) {
    http_response_code(403);
    header('Location: /');
    exit;
}

// Получаем иерархию папок для хлебных крошек
$breadcrumbs = [];
if ($folderId) {
    $breadcrumbs = $fileManager->getBreadcrumbs($folderId);
}

// AJAX-запрос
if (isset($_GET['ajax'])) {
    $content = ['folders' => [], 'files' => []];
    
    try {
        switch ($view) {
            case 'recent': 
                $content['files'] = $fileManager->getRecentFiles(50);
                break;
            case 'favorites': 
                $content['files'] = $fileManager->getFavoriteFiles(50);
                break;
            case 'trash': 
                $content['files'] = $db->fetchAll(
                    'SELECT * FROM files WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC',
                    [$userId]
                );
                break;
            case 'shares':
                $content['files'] = $db->fetchAll(
                    'SELECT f.* FROM files f JOIN shares s ON f.id = s.file_id WHERE f.user_id = ? AND f.is_deleted = 0 GROUP BY f.id',
                    [$userId]
                );
                break;
            default: 
                $content = $fileManager->getFolderContent($folderId, 50, 0);
                break;
        }
        
        // Добавляем успешный статус
        $content['success'] = true;
    } catch (Exception $e) {
        http_response_code(500);
        $content = [
            'success' => false,
            'message' => 'Ошибка загрузки содержимого: ' . $e->getMessage()
        ];
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($content);
    exit;
}

$content = ['folders' => [], 'files' => []];
$pageTitle = 'Мой диск';

switch ($view) {
    case 'recent':
        $content['files'] = $fileManager->getRecentFiles(50);
        $pageTitle = 'Недавние файлы';
        break;
    case 'favorites':
        $content['files'] = $fileManager->getFavoriteFiles(50);
        $pageTitle = 'Избранное';
        break;
    case 'trash':
        // В FileManager.php нет метода для корзины, но мы можем получить удаленные файлы
        $content['files'] = $db->fetchAll('SELECT * FROM files WHERE user_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC', [$userId]);
        $pageTitle = 'Корзина';
        break;
    case 'shares':
        // Получаем файлы, на которые созданы ссылки
        $content['files'] = $db->fetchAll(
            'SELECT f.* FROM files f JOIN shares s ON f.id = s.file_id WHERE f.user_id = ? AND f.is_deleted = 0 GROUP BY f.id',
            [$userId]
        );
        $pageTitle = 'Поделённые ссылки';
        break;
    default:
        $content = $fileManager->getFolderContent($folderId, 50, 0);
        $pageTitle = 'Мой диск';
        break;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BoohMetch - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/public/assets/css/style.css?v=1.2">
</head>
<body>
    <div class="wrapper">
        <!-- Боковое меню -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-cloud"></i>
                <span>BoohMetch</span>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/" class="nav-item <?php echo $view === 'drive' ? 'active' : ''; ?>" data-id="null">
                    <i class="fas fa-home"></i> Мой диск
                </a>
                <a href="/?view=recent" class="nav-item <?php echo $view === 'recent' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Недавние
                </a>
                <a href="/?view=favorites" class="nav-item <?php echo $view === 'favorites' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i> Избранное
                </a>
                <a href="/?view=trash" class="nav-item <?php echo $view === 'trash' ? 'active' : ''; ?>">
                    <i class="fas fa-trash"></i> Корзина
                </a>
                <a href="/?view=shares" class="nav-item <?php echo $view === 'shares' ? 'active' : ''; ?>">
                    <i class="fas fa-share-alt"></i> Поделённые ссылки
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/settings" class="profile-link">
                    <i class="fas fa-cog"></i> Настройки
                </a>
                <a href="/logout" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Выход
                </a>
            </div>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <!-- Топ панель -->
            <div class="top-bar">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Поиск файлов...">
                </div>
                
                <div class="top-actions">
                    <button class="btn btn-light" id="viewToggle" title="Переключить вид">
                        <i class="fas fa-list"></i>
                    </button>
                    <div class="user-menu">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($userData['email']); ?>" alt="Avatar" class="avatar">
                        <span><?php echo htmlspecialchars($userData['email']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Хлебные крошки (выше основной области) -->
            <div class="breadcrumbs">
                <a href="/" class="ajax-link <?php echo !$folderId ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Мой диск
                </a>
                <?php foreach ($breadcrumbs as $folder): ?>
                        <span class="separator"> / </span>
                        <a href="/?folder=<?php echo $folder['id']; ?>" class="ajax-link <?php echo end($breadcrumbs)['id'] === $folder['id'] ? 'active' : ''; ?>">
                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

            <!-- Основная область -->
            <div class="content-area">

                <!-- Кнопки действий -->
                <div class="action-bar">
                    <button class="btn btn-primary" id="uploadBtn">
                        <i class="fas fa-upload"></i> Загрузить файл
                    </button>
                    <button class="btn btn-outline-primary" id="createFolderBtn">
                        <i class="fas fa-folder-plus"></i> Создать папку
                    </button>
                    
                    <!-- Кнопка вставки -->
                    <button class="btn btn-outline-success" id="pasteBtn" style="display: none;" onclick="window.App.bulkPaste()">
                        <i class="fas fa-paste"></i> Вставить
                    </button>

                    <!-- Сортировка -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Сортировать по имени" onclick="window.App.sortBy('name')">
                            <i class="fas fa-sort-alpha-down"></i> Имя
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Сортировать по размеру" onclick="window.App.sortBy('size')">
                            <i class="fas fa-sort-amount-down"></i> Размер
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" title="Сортировать по дате" onclick="window.App.sortBy('date')">
                            <i class="fas fa-sort-numeric-down"></i> Дата
                        </button>
                    </div>
                    
                    <!-- Массовые действия (скрыто по умолчанию) -->
                    <div id="bulkActions" style="display: none;">
                        <span id="selectedCount" class="badge bg-info me-2">0 выбрано</span>
                        <button class="btn btn-outline-secondary btn-sm" id="bulkCopyBtn" onclick="window.App.bulkCopy()" title="Копировать выбранное">
                            <i class="fas fa-copy"></i> Копировать
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="bulkCutBtn" onclick="window.App.bulkCut()" title="Вырезать выбранное">
                            <i class="fas fa-cut"></i> Вырезать
                        </button>
                        <button class="btn btn-outline-danger btn-sm" id="bulkDeleteBtn" onclick="window.App.bulkDelete()" title="Удалить выбранное">
                            <i class="fas fa-trash"></i> Удалить
                        </button>
                    </div>
                </div>

                <!-- Скрытый input для загрузки -->
                <input type="file" id="fileInput" style="display: none;" multiple>

                <!-- Progress bar для загрузки -->
                <div id="uploadProgress" style="display: none; margin-bottom: 15px;">
                    <div class="d-flex justify-content-between mb-2">
                        <span><strong>Загрузка:</strong> <span id="uploadFileName"></span></span>
                        <span id="uploadSpeed" class="text-muted"></span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">
                            <span id="uploadPercentage">0%</span>
                        </div>
                    </div>
                </div>

                <!-- Таблица файлов -->
                <div class="files-container">
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Название</th>
                                <th>Размер</th>
                                <th>Дата загрузки</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody id="filesTableBody">
                            <?php if (empty($content['folders']) && empty($content['files'])): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        <i class="fas fa-folder-open"></i> Папка пуста
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <!-- Папки -->
                            <?php foreach ($content['folders'] as $folder): ?>
                                <tr class="folder-item" data-id="<?php echo $folder['id']; ?>">
                                    <td>
                                        <input type="checkbox" class="item-checkbox">
                                    </td>
                                    <td>
                                        <a href="/?folder=<?php echo $folder['id']; ?>" class="ajax-link">
                                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                                        </a>
                                    </td>
                                    <td>-</td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($folder['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-action" onclick="window.App.shareFolder(<?php echo $folder['id']; ?>)" title="Поделиться">
                                            <i class="fas fa-share"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.renameFolder(<?php echo $folder['id']; ?>)" title="Переименовать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.deleteFolder(<?php echo $folder['id']; ?>)" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Файлы -->
                            <?php foreach ($content['files'] as $file): ?>
                                <tr class="file-item" data-id="<?php echo $file['id']; ?>">
                                    <td>
                                        <input type="checkbox" class="item-checkbox">
                                    </td>
                                    <td>
                                        <i class="fas <?php echo getFileIcon($file['original_name']); ?>"></i>
                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($file['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-action" onclick="App.toggleFavorite(<?php echo $file['id']; ?>)" title="В избранное">
                                            <i class="fas fa-star <?php echo $file['is_favorite'] ? 'active' : ''; ?>"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.shareFile(<?php echo $file['id']; ?>)" title="Поделиться">
                                            <i class="fas fa-share"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.previewFile(<?php echo $file['id']; ?>)" title="Просмотр">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.downloadFile(<?php echo $file['id']; ?>)" title="Скачать">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.renameFile(<?php echo $file['id']; ?>)" title="Переименовать">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-action" onclick="App.deleteFile(<?php echo $file['id']; ?>)" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальные окна -->
    <div class="modal fade" id="shareModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Поделиться файлом</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Срок действия</label>
                        <select class="form-control" id="expireTime">
                            <option value="1">1 день</option>
                            <option value="7">7 дней</option>
                            <option value="30">30 дней</option>
                            <option value="0">Бессрочно</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>
                            <input type="checkbox" id="allowDownload" checked> Разрешить скачивание
                        </label>
                    </div>
                    <div>
                        <label>Ссылка:</label>
                        <div class="input-group">
                            <input type="text" id="shareLink" class="form-control" readonly>
                            <button class="btn btn-outline-secondary" id="copyShareBtn">Копировать</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="contextMenu" class="context-menu" style="display: none;">
        <div class="context-menu-item" data-action="cut">
            <i class="fas fa-cut"></i> Вырезать
        </div>
        <div class="context-menu-item" data-action="copy">
            <i class="fas fa-copy"></i> Копировать
        </div>
        <div class="context-menu-item" data-action="paste" id="pasteMenuItem" style="display: none;">
            <i class="fas fa-paste"></i> Вставить
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item" data-action="rename">
            <i class="fas fa-edit"></i> Переименовать
        </div>
        <div class="context-menu-item" data-action="share">
            <i class="fas fa-share"></i> Поделиться
        </div>
        <div class="context-menu-divider"></div>
        <div class="context-menu-item text-danger" data-action="delete">
            <i class="fas fa-trash"></i> Удалить
        </div>
    </div>

    <!-- Модальное окно для просмотра файлов и изображений -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewTitle">Просмотр файла</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewBody" style="text-align: center;">
                    <!-- Изображение -->
                    <img id="previewImage" style="display: none; max-width: 100%; max-height: 70vh; border-radius: 8px;">
                    <!-- PDF -->
                    <iframe id="previewPDF" style="display: none; width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
                    <!-- Текст -->
                    <pre id="previewText" style="display: none; background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-y: auto; max-height: 70vh; text-align: left;"></pre>
                </div>
                <div class="modal-footer">
                    <a id="previewDownloadBtn" class="btn btn-primary" download>
                        <i class="fas fa-download"></i> Скачать оригинал
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Глобальная функция копирования в буфер обмена
        function copyToClipboard() {
            const shareLink = document.getElementById('shareLink');
            if (!shareLink || !shareLink.value) {
                alert('Нечего копировать!');
                return;
            }

            // Используем современный API если доступен
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareLink.value).then(() => {
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> Скопировано!';
                    btn.classList.add('btn-success');
                    btn.classList.remove('btn-outline-secondary');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-secondary');
                    }, 2000);
                }).catch(err => {
                    console.error('Ошибка копирования:', err);
                    fallbackCopy(shareLink);
                });
            } else {
                // Fallback для старых браузеров
                fallbackCopy(shareLink);
            }
        }

        // Fallback метод копирования
        function fallbackCopy(element) {
            element.select();
            try {
                document.execCommand('copy');
                alert('✓ Ссылка скопирована в буфер обмена!');
            } catch (err) {
                alert('✗ Не удалось скопировать. Скопируйте вручную.');
            }
        }

        // Функция для просмотра файла
        function previewFile(fileId, fileName) {
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            const previewBody = document.getElementById('previewBody');
            const previewTitle = document.getElementById('previewTitle');
            const previewImage = document.getElementById('previewImage');
            const previewPDF = document.getElementById('previewPDF');
            const previewText = document.getElementById('previewText');
            const downloadBtn = document.getElementById('previewDownloadBtn');
            
            // Скрыть все элементы
            previewImage.style.display = 'none';
            previewPDF.style.display = 'none';
            previewText.style.display = 'none';
            
            previewTitle.textContent = fileName;
            downloadBtn.href = `/api/download/${fileId}`;
            
            const ext = fileName.split('.').pop().toLowerCase();
            
            // Показываем изображения
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
                previewImage.src = `/api/preview/${fileId}`;
                previewImage.style.display = 'block';
                modal.show();
            }
            // Показываем PDF
            else if (ext === 'pdf') {
                previewPDF.src = `/api/preview/${fileId}`;
                previewPDF.style.display = 'block';
                modal.show();
            }
            // Показываем текстовые файлы
            else if (['txt', 'md', 'html', 'css', 'js', 'json', 'xml'].includes(ext)) {
                fetch(`/api/preview/${fileId}`)
                    .then(r => r.text())
                    .then(content => {
                        previewText.textContent = content;
                        previewText.style.display = 'block';
                        modal.show();
                    })
                    .catch(err => alert('✗ Ошибка загрузки файла'));
            }
            else {
                alert('✗ Просмотр этого типа файла не поддерживается');
            }
        }
    </script>
    <script type="module" src="/public/assets/js/modules/api.js?v=1.0"></script>
    <script type="module" src="/public/assets/js/modules/ui.js?v=1.0"></script>
    <script type="module" src="/public/assets/js/app.js?v=1.5"></script>
</body>
</html>

<?php

// function getFileIcon($filename) {
//     $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
//     $icons = [
//         'pdf' => 'fa-file-pdf',
//         'doc' => 'fa-file-word',
//         'docx' => 'fa-file-word',
//         'xls' => 'fa-file-excel',
//         'xlsx' => 'fa-file-excel',
//         'jpg' => 'fa-file-image',
//         'jpeg' => 'fa-file-image',
//         'png' => 'fa-file-image',
//         'gif' => 'fa-file-image',
//         'zip' => 'fa-file-archive',
//         'rar' => 'fa-file-archive',
//         'mp3' => 'fa-file-audio',
//         'mp4' => 'fa-file-video',
//         'txt' => 'fa-file-alt'
//     ];
//     return $icons[$ext] ?? 'fa-file';
// }

// function formatBytes($bytes) {
//     if ($bytes === 0) return '0 Б';
//     $k = 1024;
//     $sizes = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
//     $i = floor(log($bytes, $k));
//     return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
// }
?>
