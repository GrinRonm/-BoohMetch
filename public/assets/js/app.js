/**
 * BoohMetch Main Application
 */
import API from './modules/api.js';
import UI from './modules/ui.js';

class BoohMetchApp {
    constructor() {
        this.clipboard = { type: null, id: null, action: null, items: null };
        this.currentFolderId = new URLSearchParams(window.location.search).get('folder') || null;
        this.currentView = new URLSearchParams(window.location.search).get('view') || 'drive';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupDragDrop();
        this.setupSearch();
        this.setupContextMenu();
        this.setupDraggableItems();
        this.setupTableEventDelegation();
        this.setupHistoryHandler();
        window.App = this;
        console.log('✓ App initialized');
    }

    setupEventListeners() {
        try {
            const uploadBtn = document.getElementById('uploadBtn');
            const createFolderBtn = document.getElementById('createFolderBtn');
            const fileInput = document.getElementById('fileInput');
            const selectAll = document.getElementById('selectAll');

            if (uploadBtn) uploadBtn.onclick = () => fileInput?.click();
            if (fileInput) fileInput.onchange = (e) => this.uploadFiles(e.target.files);
            if (createFolderBtn) createFolderBtn.onclick = () => this.showCreateFolderDialog();
            if (selectAll) selectAll.onchange = (e) => this.toggleSelectAll(e.target.checked);

            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('item-checkbox')) {
                    this.updateBulkActionsUI();
                }
            });

            document.addEventListener('click', (e) => {
                const link = e.target.closest('.ajax-link, .sidebar-nav .nav-item');
                if (link && link.href && link.href.includes(window.location.origin)) {
                    e.preventDefault();
                    this.navigateTo(link.href);
                }
                const menu = document.getElementById('contextMenu');
                if (menu) menu.style.display = 'none';
            });
        } catch (error) {
            console.error('setupEventListeners error:', error);
        }
    }

    setupTableEventDelegation() {
        try {
            const tbody = document.getElementById('filesTableBody');
            if (!tbody) return;

            // Удаляем старый слушатель если он есть
            if (this._tableClickListener) {
                tbody.removeEventListener('click', this._tableClickListener);
            }

            // Создаем новый слушатель и сохраняем ссылку
            this._tableClickListener = (e) => {
                const button = e.target.closest('.btn-action');
                if (!button) return;

                const id = parseInt(button.dataset.id);

                // Folder actions
                if (button.classList.contains('folder-share')) {
                    this.shareFolder(id);
                } else if (button.classList.contains('folder-rename')) {
                    this.renameFolder(id);
                } else if (button.classList.contains('folder-delete')) {
                    this.deleteFolder(id);
                }
                // File actions
                else if (button.classList.contains('file-favorite')) {
                    this.toggleFavorite(id);
                } else if (button.classList.contains('file-preview')) {
                    const filename = button.dataset.filename || '';
                    const tgId = button.dataset.tgId || '';
                    this.previewFile(id, filename, tgId);
                } else if (button.classList.contains('file-share')) {
                    this.shareFile(id);
                } else if (button.classList.contains('file-download')) {
                    this.downloadFile(id);
                } else if (button.classList.contains('file-rename')) {
                    this.renameFile(id);
                } else if (button.classList.contains('file-delete')) {
                    this.deleteFile(id);
                }
            };

            tbody.addEventListener('click', this._tableClickListener);
        } catch (error) {
            console.error('setupTableEventDelegation error:', error);
        }
    }

    async navigateTo(url, pushState = true) {
        try {
            // Показываем loader
            this.showLoader(true);

            const urlObj = new URL(url);
            const folderId = urlObj.searchParams.get('folder') || null;
            const view = urlObj.searchParams.get('view') || 'drive';

            console.log(`Navigating to: folder=${folderId}, view=${view}`);

            const data = await API.getFolderContent(folderId, view);

            // Проверяем ошибку
            if (data.success === false) {
                this.showLoader(false);
                UI.showNotification(data.message || 'Ошибка загрузки содержимого папки', 'error');
                return;
            }

            this.currentFolderId = folderId;
            this.currentView = view;
            UI.updateFilesTable(data);
            
            // Обновляем breadcrumbs для всех видов (включая главную страницу)
            const breadcrumbData = await API.getBreadcrumbs(folderId);
            if (breadcrumbData && (breadcrumbData.success !== false || breadcrumbData.breadcrumbs)) {
                UI.updateBreadcrumbs(breadcrumbData.breadcrumbs, folderId);
            }
            
            // Обновляем активные пункты меню
            document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => {
                const itemUrl = new URL(item.href);
                const itemView = itemUrl.searchParams.get('view') || 'drive';
                item.classList.toggle('active', itemView === view && !folderId);
            });

            if (pushState) {
                history.pushState({ folderId, view }, '', url);
            }

            console.log('✓ Navigation complete');
        } catch (error) {
            console.error('navigateTo error:', error);
            UI.showNotification('Ошибка навигации: ' + error.message, 'error');
        } finally {
            this.showLoader(false);
        }
    }

    showLoader(show = true) {
        try {
            const loader = document.getElementById('loadingSpinner');
            if (!loader) {
                if (show) {
                    const spinner = document.createElement('div');
                    spinner.id = 'loadingSpinner';
                    spinner.style.cssText = `
                        position: fixed;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        z-index: 9999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: rgba(255,255,255,0.9);
                        border-radius: 8px;
                        padding: 20px;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    `;
                    spinner.innerHTML = `
                        <div style="text-align: center;">
                            <div style="
                                border: 3px solid #f3f3f3;
                                border-top: 3px solid #3498db;
                                border-radius: 50%;
                                width: 40px;
                                height: 40px;
                                animation: spin 1s linear infinite;
                            "></div>
                            <p style="margin-top: 10px; color: #666;">Загрузка...</p>
                        </div>
                        <style>
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        </style>
                    `;
                    document.body.appendChild(spinner);
                }
            } else {
                loader.style.display = show ? 'flex' : 'none';
            }
        } catch (error) {
            console.error('showLoader error:', error);
        }
    }

    setupHistoryHandler() {
        try {
            window.onpopstate = (e) => {
                if (e.state) {
                    this.navigateTo(window.location.href, false);
                }
            };
        } catch (error) {
            console.error('setupHistoryHandler error:', error);
        }
    }

    async uploadFiles(files) {
        try {
            if (!files.length) return;
            UI.showNotification(`Загрузка ${files.length} файла(ов)...`, 'info');

            this.showLoader(true);
            const response = await API.upload(files, this.currentFolderId);

            if (response.success === false) {
                UI.showNotification(response.message || 'Ошибка загрузки', 'error');
                this.showLoader(false);
                return;
            }

            await this.refreshCurrentFolder();
            UI.showNotification('Файлы успешно загружены', 'success');
        } catch (error) {
            console.error('uploadFiles error:', error);
            UI.showNotification('Ошибка загрузки: ' + error.message, 'error');
        } finally {
            this.showLoader(false);
        }
    }

    async refreshCurrentFolder() {
        try {
            const data = await API.getFolderContent(this.currentFolderId, this.currentView);
            if (data && data.success !== false) {
                UI.updateFilesTable(data);
            }
        } catch (error) {
            console.error('refreshCurrentFolder error:', error);
        }
    }

    async toggleFavorite(fileId) {
        try {
            // Защита от двойного клика
            if (this._favoriteInProgress) return;
            this._favoriteInProgress = true;

            const response = await API.toggleFavorite(fileId);
            if (response.success) {
                // Обновляем только звездочку для этого файла, не перезагружаем всю таблицу
                const starButton = document.querySelector(`.file-favorite[data-id="${fileId}"] i`);
                if (starButton) {
                    starButton.classList.toggle('active');
                }
                UI.showNotification('Избранное обновлено', 'success');
            } else {
                UI.showNotification(response.message || 'Ошибка', 'error');
            }
        } catch (error) {
            console.error('toggleFavorite error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        } finally {
            this._favoriteInProgress = false;
        }
    }

    previewFile(fileId, filename = '', telegramFileId = '') {
        try {
            window.open(`/public/api/preview.php?id=${fileId}`, '_blank');
        } catch (error) {
            console.error('previewFile error:', error);
            UI.showNotification('Ошибка просмотра: ' + error.message, 'error');
        }
    }

    async shareFile(fileId) {
        try {
            const shareUrl = `${window.location.origin}/public/api/share.php?file_id=${fileId}`;
            const modal = new bootstrap.Modal(document.getElementById('shareModal'), {});

            document.getElementById('shareLink').value = shareUrl;
            document.getElementById('shareTitle').textContent = 'Поделиться файлом';

            document.getElementById('copyShareBtn').onclick = () => {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    UI.showNotification('Ссылка скопирована в буфер обмена', 'success');
                }).catch(() => {
                    document.getElementById('shareLink').select();
                    document.execCommand('copy');
                    UI.showNotification('Ссылка скопирована', 'success');
                });
            };

            modal.show();
        } catch (error) {
            console.error('shareFile error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async shareFolder(folderId) {
        try {
            const shareUrl = `${window.location.origin}/public/api/share.php?folder_id=${folderId}`;
            const modal = new bootstrap.Modal(document.getElementById('shareModal'), {});

            document.getElementById('shareLink').value = shareUrl;
            document.getElementById('shareTitle').textContent = 'Поделиться папкой';

            document.getElementById('copyShareBtn').onclick = () => {
                navigator.clipboard.writeText(shareUrl).then(() => {
                    UI.showNotification('Ссылка скопирована в буфер обмена', 'success');
                }).catch(() => {
                    document.getElementById('shareLink').select();
                    document.execCommand('copy');
                    UI.showNotification('Ссылка скопирована', 'success');
                });
            };

            modal.show();
        } catch (error) {
            console.error('shareFolder error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async downloadFile(fileId) {
        try {
            window.location.href = `/public/api/download.php?id=${fileId}`;
        } catch (error) {
            console.error('downloadFile error:', error);
            UI.showNotification('Ошибка загрузки: ' + error.message, 'error');
        }
    }

    async renameFile(fileId) {
        try {
            const newName = prompt('Новое имя файла:');
            if (newName) {
                const response = await API.renameFile(fileId, newName);
                if (response.success) {
                    await this.refreshCurrentFolder();
                    UI.showNotification('Файл переименован', 'success');
                }
            }
        } catch (error) {
            console.error('renameFile error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async renameFolder(folderId) {
        try {
            const newName = prompt('Новое имя папки:');
            if (newName) {
                const response = await API.renameFolder(folderId, newName);
                if (response.success) {
                    await this.refreshCurrentFolder();
                    UI.showNotification('Папка переименована', 'success');
                }
            }
        } catch (error) {
            console.error('renameFolder error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async deleteFile(fileId) {
        try {
            if (!confirm('Удалить файл?')) return;
            const response = await API.deleteFile(fileId);
            if (response.success) {
                await this.refreshCurrentFolder();
                UI.showNotification('Файл удален', 'success');
            }
        } catch (error) {
            console.error('deleteFile error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async deleteFolder(folderId) {
        try {
            if (!confirm('Удалить папку?')) return;
            const response = await API.deleteFolder(folderId);
            if (response.success) {
                await this.refreshCurrentFolder();
                UI.showNotification('Папка удалена', 'success');
            }
        } catch (error) {
            console.error('deleteFolder error:', error);
            UI.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    setupDragDrop() {
        try {
            const dropZone = document.getElementById('uploadArea');
            if (!dropZone) return;

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.style.backgroundColor = '#f0f0f0';
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.style.backgroundColor = '';
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.style.backgroundColor = '';
                if (e.dataTransfer.files) {
                    this.uploadFiles(e.dataTransfer.files);
                }
            });
        } catch (error) {
            console.error('setupDragDrop error:', error);
        }
    }

    setupSearch() {
        try {
            const searchBtn = document.getElementById('searchBtn');
            const searchInput = document.getElementById('searchInput');

            if (searchBtn) {
                searchBtn.onclick = () => {
                    if (searchInput && searchInput.value) {
                        this.globalSearch(searchInput.value);
                    }
                };
            }

            if (searchInput) {
                searchInput.onkeypress = (e) => {
                    if (e.key === 'Enter' && searchInput.value) {
                        this.globalSearch(searchInput.value);
                    }
                };
            }
        } catch (error) {
            console.error('setupSearch error:', error);
        }
    }

    async globalSearch(query) {
        try {
            const results = await API.search(query);
            if (results.success) {
                UI.updateFilesTable(results);
                document.getElementById('currentFolderName').textContent = `Результаты поиска: "${query}"`;
            }
        } catch (error) {
            console.error('globalSearch error:', error);
            UI.showNotification('Ошибка поиска: ' + error.message, 'error');
        }
    }

    setupContextMenu() {
        try {
            document.addEventListener('contextmenu', (e) => {
                const row = e.target.closest('.file-item, .folder-item');
                if (!row) return;

                e.preventDefault();
                const menu = document.getElementById('contextMenu');
                if (!menu) return;

                const fileId = row.dataset.id;
                const isFolder = row.classList.contains('folder-item');

                menu.style.left = e.pageX + 'px';
                menu.style.top = e.pageY + 'px';
                menu.style.display = 'block';

                menu.innerHTML = isFolder ? `
                    <a href="#" onclick="return false;" data-action="rename" data-id="${fileId}">Переименовать</a>
                    <a href="#" onclick="return false;" data-action="share" data-id="${fileId}">Поделиться</a>
                    <a href="#" onclick="return false;" data-action="delete" data-id="${fileId}">Удалить</a>
                ` : `
                    <a href="#" onclick="return false;" data-action="favorite" data-id="${fileId}">В избранное</a>
                    <a href="#" onclick="return false;" data-action="preview" data-id="${fileId}">Просмотреть</a>
                    <a href="#" onclick="return false;" data-action="download" data-id="${fileId}">Скачать</a>
                    <a href="#" onclick="return false;" data-action="share" data-id="${fileId}">Поделиться</a>
                    <a href="#" onclick="return false;" data-action="rename" data-id="${fileId}">Переименовать</a>
                    <a href="#" onclick="return false;" data-action="delete" data-id="${fileId}">Удалить</a>
                `;

                menu.querySelectorAll('a').forEach(item => {
                    item.onclick = (e) => {
                        e.preventDefault();
                        const action = item.dataset.action;
                        const id = parseInt(item.dataset.id);

                        if (isFolder) {
                            if (action === 'rename') this.renameFolder(id);
                            else if (action === 'share') this.shareFolder(id);
                            else if (action === 'delete') this.deleteFolder(id);
                        } else {
                            if (action === 'favorite') this.toggleFavorite(id);
                            else if (action === 'preview') this.previewFile(id);
                            else if (action === 'download') this.downloadFile(id);
                            else if (action === 'share') this.shareFile(id);
                            else if (action === 'rename') this.renameFile(id);
                            else if (action === 'delete') this.deleteFile(id);
                        }

                        menu.style.display = 'none';
                    };
                });
            });
        } catch (error) {
            console.error('setupContextMenu error:', error);
        }
    }

    setupDraggableItems() {
        try {
            const tbody = document.getElementById('filesTableBody');
            if (!tbody) return;

            tbody.querySelectorAll('.file-item').forEach(item => {
                item.draggable = true;

                item.ondragstart = (e) => {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('fileId', item.dataset.id);
                };
            });

            tbody.querySelectorAll('.folder-item').forEach(folder => {
                folder.ondragover = (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    folder.style.backgroundColor = '#e3f2fd';
                };

                folder.ondragleave = () => {
                    folder.style.backgroundColor = '';
                };

                folder.ondrop = async (e) => {
                    e.preventDefault();
                    folder.style.backgroundColor = '';
                    const fileId = e.dataTransfer.getData('fileId');
                    const folderId = folder.dataset.id;

                    if (fileId && folderId) {
                        try {
                            const response = await API.moveFile(fileId, folderId);
                            if (response.success) {
                                await this.refreshCurrentFolder();
                                UI.showNotification('Файл перемещен', 'success');
                            }
                        } catch (error) {
                            console.error('Drop operation error:', error);
                        }
                    }
                };
            });
        } catch (error) {
            console.error('setupDraggableItems error:', error);
        }
    }

    toggleSelectAll(checked) {
        try {
            document.querySelectorAll('.item-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
            });
            this.updateBulkActionsUI();
        } catch (error) {
            console.error('toggleSelectAll error:', error);
        }
    }

    updateBulkActionsUI() {
        try {
            const selected = document.querySelectorAll('.item-checkbox:checked').length;
            const bulkActions = document.getElementById('bulkActions');
            if (bulkActions) {
                bulkActions.style.display = selected > 0 ? 'flex' : 'none';
            }
        } catch (error) {
            console.error('updateBulkActionsUI error:', error);
        }
    }

    async showCreateFolderDialog() {
        try {
            const folderName = prompt('Введите имя папки:');
            if (folderName) {
                const response = await API.createFolder(folderName, this.currentFolderId);
                if (response.success) {
                    await this.refreshCurrentFolder();
                    UI.showNotification('Папка создана', 'success');
                }
            }
        } catch (error) {
            console.error('showCreateFolderDialog error:', error);
            UI.showNotification('Ошибка создания папки: ' + error.message, 'error');
        }
    }

    async sortBy(field) {
        try {
            const order = this.currentOrder === 'asc' ? 'desc' : 'asc';
            this.currentOrder = order;

            const data = await API.getFolderContent(this.currentFolderId, this.currentView, field, order);
            if (data && data.success !== false) {
                UI.updateFilesTable(data);
            }
        } catch (error) {
            console.error('sortBy error:', error);
            UI.showNotification('Ошибка сортировки: ' + error.message, 'error');
        }
    }

    bulkCopy() {
        try {
            const selected = document.querySelectorAll('.item-checkbox:checked');
            const ids = Array.from(selected).map(cb => cb.closest('tr').dataset.id);
            if (ids.length > 0) {
                this.clipboard = { type: 'copy', items: ids };
                UI.showNotification(`${ids.length} элемент(ов) скопирован(о)`, 'success');
                this.updateBulkActionsUI();
            }
        } catch (error) {
            console.error('bulkCopy error:', error);
        }
    }

    bulkCut() {
        try {
            const selected = document.querySelectorAll('.item-checkbox:checked');
            const ids = Array.from(selected).map(cb => cb.closest('tr').dataset.id);
            if (ids.length > 0) {
                this.clipboard = { type: 'cut', items: ids };
                UI.showNotification(`${ids.length} элемент(ов) вырезан(о)`, 'success');
                this.updateBulkActionsUI();
            }
        } catch (error) {
            console.error('bulkCut error:', error);
        }
    }

    async bulkPaste() {
        try {
            if (!this.clipboard.items || !this.clipboard.type) {
                UI.showNotification('Буфер обмена пуст', 'error');
                return;
            }

            for (let id of this.clipboard.items) {
                if (this.clipboard.type === 'copy') {
                    await API.copyFile(id, this.currentFolderId);
                } else if (this.clipboard.type === 'cut') {
                    await API.moveFile(id, this.currentFolderId);
                }
            }

            this.clipboard = { type: null, items: null };
            await this.refreshCurrentFolder();
            UI.showNotification('Вставлено успешно', 'success');
            this.updateBulkActionsUI();
        } catch (error) {
            console.error('bulkPaste error:', error);
            UI.showNotification('Ошибка при вставлении: ' + error.message, 'error');
        }
    }

    async bulkDelete() {
        try {
            const selected = document.querySelectorAll('.item-checkbox:checked');
            const ids = Array.from(selected).map(cb => cb.closest('tr').dataset.id);

            if (ids.length > 0 && confirm(`Удалить ${ids.length} элемент(ов)?`)) {
                for (let id of ids) {
                    const row = document.querySelector(`[data-id="${id}"]`);
                    if (row.classList.contains('folder-item')) {
                        await API.deleteFolder(id);
                    } else {
                        await API.deleteFile(id);
                    }
                }

                await this.refreshCurrentFolder();
                UI.showNotification('Элементы удалены', 'success');
                this.updateBulkActionsUI();
            }
        } catch (error) {
            console.error('bulkDelete error:', error);
            UI.showNotification('Ошибка удаления: ' + error.message, 'error');
        }
    }
}

// Global error handlers
window.addEventListener('error', (event) => {
    console.error('✗ Uncaught error:', event.error);
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('✗ Unhandled rejection:', event.reason);
});

// Initialize app
new BoohMetchApp();
