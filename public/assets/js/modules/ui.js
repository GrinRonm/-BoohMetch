/**
 * BoohMetch UI Module
 */
const UI = {
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed; top: 20px; right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white; padding: 15px 20px; border-radius: 6px;
            box-shadow: 0 10px 15px rgba(0,0,0,0.1); z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    updateFilesTable(data) {
        const tbody = document.getElementById('filesTableBody');
        if (!tbody) return;

        if (!data.folders.length && !data.files.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted"><i class="fas fa-folder-open"></i> Папка пуста</td></tr>`;
            return;
        }

        let html = '';
        
        // Render Folders
        data.folders.forEach(folder => {
            html += `
                <tr class="folder-item" data-id="${folder.id}">
                    <td><input type="checkbox" class="item-checkbox"></td>
                    <td>
                        <a href="/?folder=${folder.id}" class="ajax-link">
                            <i class="fas fa-folder"></i> ${this.escapeHtml(folder.name)}
                        </a>
                    </td>
                    <td>-</td>
                    <td data-date="${folder.created_at}">${folder.created_at}</td>
                    <td>
                        <button class="btn-action folder-share" data-id="${folder.id}" title="Поделиться"><i class="fas fa-share"></i></button>
                        <button class="btn-action folder-rename" data-id="${folder.id}" title="Переименовать"><i class="fas fa-edit"></i></button>
                        <button class="btn-action folder-delete" data-id="${folder.id}" title="Удалить"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        // Render Files
        data.files.forEach(file => {
            const icon = this.getFileIconClass(file.original_name);
            // Нормализуем is_favorite (может быть 0, 1, '0', '1', false, true)
            const isFavorite = file.is_favorite === 1 || file.is_favorite === '1' || file.is_favorite === true;
            
            // Определяем можно ли просмотреть файл (только изображения и PDF)
            const ext = file.original_name.split('.').pop().toLowerCase();
            const previewable = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'].includes(ext);
            
            html += `
                <tr class="file-item" data-id="${file.id}">
                    <td><input type="checkbox" class="item-checkbox"></td>
                    <td><i class="fas ${icon}"></i> ${this.escapeHtml(file.original_name)}</td>
                    <td data-bytes="${file.size}">${this.formatBytes(file.size)}</td>
                    <td data-date="${file.created_at}">${file.created_at}</td>
                    <td>
                        <button class="btn-action file-favorite" data-id="${file.id}" title="В избранное">
                            <i class="fas fa-star ${isFavorite ? 'active' : ''}"></i>
                        </button>
                        ${previewable ? `<button class="btn-action file-preview" data-id="${file.id}" data-filename="${this.escapeHtml(file.original_name)}" data-tg-id="${file.telegram_file_id}" title="Просмотреть"><i class="fas fa-eye"></i></button>` : ''}
                        <button class="btn-action file-share" data-id="${file.id}" title="Поделиться"><i class="fas fa-share"></i></button>
                        <button class="btn-action file-download" data-id="${file.id}" title="Скачать"><i class="fas fa-download"></i></button>
                        <button class="btn-action file-rename" data-id="${file.id}" title="Переименовать"><i class="fas fa-edit"></i></button>
                        <button class="btn-action file-delete" data-id="${file.id}" title="Удалить"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        
        // Re-initialize events for new elements
        if (window.App) {
            window.App.setupTableEventDelegation();
            window.App.setupDraggableItems();
            window.App.setupContextMenu();
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    formatBytes(bytes) {
        if (bytes === 0) return '0 Б';
        const k = 1024;
        const sizes = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },

    getFileIconClass(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const iconMap = {
            'pdf': 'fa-file-pdf text-danger',
            'doc': 'fa-file-word text-primary', 'docx': 'fa-file-word text-primary',
            'xls': 'fa-file-excel text-success', 'xlsx': 'fa-file-excel text-success',
            'jpg': 'fa-file-image text-warning', 'jpeg': 'fa-file-image text-warning', 'png': 'fa-file-image text-warning',
            'zip': 'fa-file-archive text-secondary', 'rar': 'fa-file-archive text-secondary',
            'mp3': 'fa-file-audio text-info', 'mp4': 'fa-file-video text-danger'
        };
        return iconMap[ext] || 'fa-file text-muted';
    },

    updateBreadcrumbs(breadcrumbs = [], folderId = null) {
        const container = document.querySelector('.breadcrumbs');
        if (!container) return;

        // Всегда показываем breadcrumbs (даже на главной) чтобы не было скачков
        container.style.display = 'flex';

        let html = '<a href="/" class="ajax-link">';
        html += '<i class="fas fa-home"></i> Мой диск</a>';

        if (Array.isArray(breadcrumbs) && breadcrumbs.length > 0) {
            breadcrumbs.forEach((folder, index) => {
                const isActive = folderId && folder.id === folderId;
                html += '<span class="separator"> / </span>';
                html += '<a href="/?folder=' + folder.id + '" class="ajax-link ' + (isActive ? 'active' : '') + '">';
                html += '<i class="fas fa-folder"></i> ' + this.escapeHtml(folder.name) + '</a>';
            });
        }

        container.innerHTML = html;

        // Re-attach AJAX click handlers
        if (window.App) {
            const links = container.querySelectorAll('.ajax-link');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.App.navigateTo(link.href);
                });
            });
        }
    }
};

export default UI;
window.UI = UI;
