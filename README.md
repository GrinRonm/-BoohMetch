# BoohMetch

BoohMetch is a lightweight PHP web application for personal cloud storage with Telegram integration. Files uploaded by the user are processed through a Telegram-based workflow, making it suitable for experiments with remote storage, automation, and simple file-sharing scenarios.

## Features

- User registration and authentication
- Telegram bot connection and setup flow
- Upload, download, rename, delete, and favorite operations
- Folder hierarchy and navigation
- Public share links for files and folders
- Search and sorting by name, size, and date
- Responsive UI inspired by cloud storage services
- SQLite-based storage and background upload processing

## Tech Stack

- PHP 8.2+
- SQLite
- Bootstrap 5.3
- Vanilla JavaScript
- Telegram Bot API
- Cron-based background processing

## Project Structure

```text
BoohMetch/
├── config/
├── public/
│   └── api/
├── src/
│   ├── classes/
│   ├── cron/
│   └── helpers/
├── storage/
├── views/
└── install.php
```

## Installation

1. Clone the repository:

```bash
git clone https://github.com/GrinRonm/-BoohMetch.git
cd BoohMetch
```

2. Make sure the required folders are writable:

```bash
chmod -R 755 storage config public src
```

3. Configure the application in [config/config.php](config/config.php).

4. Run the installer:

```bash
php install.php
```

5. Configure Telegram bot credentials in the app after registration.

## Usage

- Register a new account
- Connect your Telegram bot and channel
- Upload files from the main dashboard
- Share files through generated public links

## Notes

This project is intended as a personal or experimental cloud-storage prototype. It is not a full production-grade SaaS platform and should be reviewed and hardened before deployment in a public environment.

│   └── .htaccess               # Запрет доступа
├── README.md                   # Этот файл
└── .gitignore                  # Исключение файлов
```

## 🔐 Безопасность

### Реализованные меры:

1. **Подготовленные запросы (PDO)** — защита от SQL-инъекций
2. **Проверка расширений файлов** — ограничение опасных типов
3. **Проверка MIME-типов** — валидация типов файлов
4. **Хеширование паролей** — password_hash() с BCRYPT
5. **Шифрование токенов** — AES-256-CBC для Telegram токенов
6. **Сессии** — timeout и HttpOnly cookies
7. **CSRF защита** — можно добавить токены
8. **X-Frame-Options** — защита от clickjacking
9. **X-Content-Type-Options** — запрет sniffing
10. **.htaccess** — ограничение доступа к чувствительным папкам

### Рекомендации безопасности:

- **HTTPS**: используйте SSL сертификаты в production
- **ENCRYPTION_KEY**: измените на длинный случайный ключ
- **Firewall**: ограничьте доступ к API с помощью rate limiting
- **Backup**: регулярно делайте резервные копии БД
- **Логирование**: мониторьте логи на предмет атак

## 📊 База данных

### Таблицы:

**users** — Пользователи с Telegram учётными данными
- id, email, password, telegram_chat_id, telegram_token, created_at, updated_at

**folders** — Папки с иерархией
- id, user_id, parent_id, name, created_at, updated_at

**files** — Файлы с метаданными
- id, user_id, folder_id, original_name, stored_name, telegram_file_id, mime_type, size, is_favorite, is_deleted, deleted_at, created_at, updated_at

**shares** — Ссылки общего доступа
- id, user_id, file_id, folder_id, share_token, allow_download, expires_at, access_count, created_at, updated_at

**upload_queue** — Очередь отправки в Telegram
- id, user_id, file_id, local_path, status, attempt_count, error_message, created_at, updated_at

## 🐛 Решение проблем

### БД не инициализируется

```bash
# Проверьте права доступа
ls -la /var/www/BoohMetch/storage/database/
chmod 755 /var/www/BoohMetch/storage/database/

# Создайте БД вручную
touch /var/www/BoohMetch/storage/database/database.sqlite
chmod 666 /var/www/BoohMetch/storage/database/database.sqlite
sqlite3 /var/www/BoohMetch/storage/database/database.sqlite < /var/www/BoohMetch/storage/database/init.sql
```

### Файлы не загружаются

1. Проверьте размер файла (`php.ini`: `upload_max_filesize`, `post_max_size`)
2. Проверьте права на папку `/storage/uploads/`
3. Проверьте логи в `/storage/logs/`

### Telegram не отправляет файлы

1. Проверьте токен бота (должен начинаться с цифр)
2. Проверьте Chat ID (должен быть отрицательным числом)
3. Бот должен быть администратором в канале
4. Проверьте cron: `crontab -l`
5. Проверьте логи: `tail -f /var/www/BoohMetch/storage/logs/application.log`

### Ошибка при входе

1. Проверьте, что БД инициализирована
2. Убедитесь, что таблица `users` существует
3. Проверьте логи приложения

## 📝 API Endpoints

- `POST /api/upload.php` — Загрузка файлов
- `POST /api/folder.php` — Создание папки
- `POST /api/file.php?action=rename` — Переименование файла
- `POST /api/file.php?action=favorite` — Добавление в избранное
- `DELETE /api/file.php?action=delete` — Удаление файла
- `GET /api/download.php?id=FILE_ID` — Скачивание файла
- `POST /api/share.php` — Создание ссылки общего доступа
- `GET /share/TOKEN` — Публичная ссылка на файл

## 🤝 Контрибьюция

Вклады приветствуются! Пожалуйста:

1. Создайте fork репозитория
2. Создайте ветку для вашей функции (`git checkout -b feature/amazing-feature`)
3. Коммитьте изменения (`git commit -m 'Add amazing feature'`)
4. Пушьте в ветку (`git push origin feature/amazing-feature`)
5. Откройте Pull Request

## 📄 Лицензия

Этот проект лицензирован под MIT License - смотрите файл [LICENSE](LICENSE) для деталей.

## 👨‍💻 Автор

**BoohMetch** создан как полнофункциональное облачное хранилище наследующее лучшие практики веб-разработки.

## 🙏 Благодарности

- Bootstrap 5.3 — UI Framework
- Font Awesome 6.4 — Icons
- Telegram Bot API — File Storage
- SQLite — Database

## 📞 Поддержка

Если вы нашли ошибку или у вас есть предложение, пожалуйста, откройте issue в репозитории.

---

**Последнее обновление:** April 14, 2026
