/**
 * BoohMetch API Module - Улучшенная версия
 * С обработкой ошибок, timeout, retry и кэшированием
 */

const API = {
    // Конфиг
    TIMEOUT_MS: 30000,
    MAX_RETRIES: 3,
    RETRY_DELAY_MS: 1000,
    cache: {},
    pendingRequests: {},

    /**
     * Основной метод для всех запросов
     */
    async request(endpoint, method = 'GET', body = null, options = {}) {
        const cacheKey = `${method}:${endpoint}`;
        const fullUrl = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;

        // Проверяем кэш (только для GET)
        if (method === 'GET' && this.cache[cacheKey] && !options.noCache) {
            return this.cache[cacheKey];
        }

        // Если уже есть pending запрос - возвращаем его
        if (this.pendingRequests[cacheKey]) {
            return this.pendingRequests[cacheKey];
        }

        const requestPromise = this._executeRequest(fullUrl, method, body, options);
        this.pendingRequests[cacheKey] = requestPromise;

        try {
            const result = await requestPromise;
            if (method === 'GET' && !options.noCache) {
                this.cache[cacheKey] = result;
            }
            return result;
        } finally {
            delete this.pendingRequests[cacheKey];
        }
    },

    /**
     * Выполнить запрос с timeout и retry
     */
    async _executeRequest(endpoint, method, body, options = {}, attempt = 0) {
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), this.TIMEOUT_MS);

            const fetchOptions = {
                method,
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                }
            };

            if (body && method !== 'GET') {
                fetchOptions.body = JSON.stringify(body);
            }

            const response = await fetch(endpoint, fetchOptions);
            clearTimeout(timeout);

            // Проверяем HTTP статус
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(
                    errorData.message ||
                    `HTTP ${response.status}: ${response.statusText}`
                );
            }

            return await response.json();
        } catch (error) {
            // Retry при сетевых ошибках и timeout
            if (attempt < this.MAX_RETRIES && (
                error.name === 'AbortError' ||
                error.message.includes('Failed to fetch')
            )) {
                console.warn(`Retry ${attempt + 1}/${this.MAX_RETRIES} для ${endpoint}`);
                await new Promise(resolve => setTimeout(resolve, this.RETRY_DELAY_MS));
                return this._executeRequest(endpoint, method, body, options, attempt + 1);
            }

            console.error(`API Error (${endpoint}):`, error);
            return {
                success: false,
                message: error.message || 'Ошибка сети или сервера',
                error: error.name
            };
        }
    },

    /**
     * Очистить кэш
     */
    clearCache() {
        this.cache = {};
    },

    clearCacheFor(endpoint) {
        Object.keys(this.cache).forEach(key => {
            if (key.includes(endpoint)) {
                delete this.cache[key];
            }
        });
    },

    // ===== FILES =====
    async upload(files, folderId, onProgress = null) {
        const formData = new FormData();
        for (let file of files) formData.append('files[]', file);
        if (folderId) formData.append('folder_id', folderId);

        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 300000); // 5 минут

            const fetchOptions = {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const response = await fetch('/api/upload', fetchOptions);
            clearTimeout(timeout);

            if (!response.ok) {
                throw new Error(`Upload failed: ${response.status}`);
            }

            const result = await response.json();
            this.clearCacheFor('/?ajax=1');
            return result;
        } catch (error) {
            console.error('Upload error:', error);
            return { success: false, message: 'Ошибка при загрузке файла' };
        }
    },

    async getFile(id) {
        return this.request(`/api/file/${id}`, 'GET');
    },

    async deleteFile(id) {
        const result = await this.request(`/api/file/delete/${id}`, 'DELETE', null, { noCache: true });
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async renameFile(id, name) {
        const result = await this.request(`/api/file/rename/${id}`, 'POST', { name }, { noCache: true });
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async toggleFavorite(id) {
        const result = await this.request(`/api/file/favorite/${id}`, 'POST', null, { noCache: true });
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async moveFile(id, newParentId) {
        const result = await this.request(
            `/api/file/move/${id}`,
            'POST',
            { new_parent_id: newParentId },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async copyFile(id, newParentId) {
        const result = await this.request(
            `/api/file/copy/${id}`,
            'POST',
            { new_parent_id: newParentId },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    // ===== FOLDERS =====
    async getFolder(id) {
        return this.request(`/api/folder/${id}`, 'GET');
    },

    async createFolder(name, parentId) {
        const result = await this.request(
            '/api/folder/create',
            'POST',
            { name, parent_id: parentId },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async deleteFolder(id) {
        const result = await this.request(`/api/folder/delete/${id}`, 'DELETE', null, { noCache: true });
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async renameFolder(id, name) {
        const result = await this.request(
            `/api/folder/rename/${id}`,
            'POST',
            { name },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async moveFolder(id, newParentId) {
        const result = await this.request(
            `/api/folder/move/${id}`,
            'POST',
            { new_parent_id: newParentId },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    async copyFolder(id, newParentId) {
        const result = await this.request(
            `/api/folder/copy/${id}`,
            'POST',
            { new_parent_id: newParentId },
            { noCache: true }
        );
        this.clearCacheFor('/?ajax=1');
        return result;
    },

    // ===== SHARE =====
    async createShare(fileId, folderId, expireTime, allowDownload) {
        return this.request(
            '/api/share',
            'POST',
            { file_id: fileId, folder_id: folderId, expire_time: expireTime, allow_download: allowDownload },
            { noCache: true }
        );
    },

    // ===== CONTENT =====
    async getFolderContent(folderId = null, view = 'drive') {
        const params = new URLSearchParams({ ajax: 1, view });
        if (folderId) params.append('folder', folderId);
        
        const url = `/?${params.toString()}`;
        return this.request(url, 'GET');
    },

    async getBreadcrumbs(folderId = null) {
        const params = new URLSearchParams();
        if (folderId) params.append('folder_id', folderId);
        
        const url = `/api/breadcrumbs${params.toString() ? '?' + params.toString() : ''}`;
        return this.request(url, 'GET');
    },

    // ===== SEARCH =====
    async search(query, limit = 50) {
        const params = new URLSearchParams({ q: query, limit });
        return this.request(`/api/search?${params.toString()}`, 'GET', null, { noCache: true });
    },

    // ===== UTILITIES =====
    async checkHealth() {
        try {
            const result = await Promise.race([
                this.request('/api/ping', 'GET'),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 5000))
            ]);
            return result.success !== false;
        } catch {
            return false;
        }
    }
};

export default API;
window.API = API;
