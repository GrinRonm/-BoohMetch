<?php
/**
 * Страница настроек пользователя
 */

// Проверяем авторизацию
if (!isset($auth) || !$auth->isAuthenticated()) {
    header('Location: /login');
    exit;
}

$userId = $auth->getUserId();
$userData = $auth->getUserData();
$db = Database::getInstance();

$error = '';
$success = '';

// Обработка сохранения настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_telegram') {
        $token = $_POST['telegram_token'] ?? '';
        $chatId = $_POST['telegram_chat_id'] ?? '';
        
        $telegramManager = new TelegramManager($db, $userId);
        $result = $telegramManager->saveTelegramCredentials($token, $chatId);
        
        if ($result['success']) {
            $success = 'Настройки Telegram обновлены';
            $userData = $auth->getUserData(); // Обновляем данные пользователя
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if ($newPass !== $confirmPass) {
            $error = 'Пароли не совпадают';
        } else {
            // В классе Auth должен быть метод для смены пароля
            // Если его нет, реализуем здесь или добавим в Auth.php
            $result = $auth->changePassword($userId, $oldPass, $newPass);
            if ($result['success']) {
                $success = 'Пароль успешно изменен';
            } else {
                $error = $result['message'];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки - BoohMetch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .settings-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            background: #fcfcfc;
        }
        .settings-body {
            padding: 25px;
        }
        .form-label {
            font-weight: 500;
            color: #444;
        }
        .btn-save {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
        }
        .btn-save:hover {
            background: #5a6fd6;
            color: white;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Боковое меню (как в drive.php) -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-cloud"></i>
                <span>BoohMetch</span>
            </div>
            
            <nav class="sidebar-nav">
                <a href="/" class="nav-item">
                    <i class="fas fa-home"></i> Мой диск
                </a>
                <a href="/?view=recent" class="nav-item">
                    <i class="fas fa-history"></i> Недавние
                </a>
                <a href="/?view=favorites" class="nav-item">
                    <i class="fas fa-star"></i> Избранное
                </a>
                <a href="/?view=trash" class="nav-item">
                    <i class="fas fa-trash"></i> Корзина
                </a>
                <a href="/?view=shares" class="nav-item">
                    <i class="fas fa-share-alt"></i> Поделённые ссылки
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="/settings" class="profile-link active">
                    <i class="fas fa-cog"></i> Настройки
                </a>
                <a href="/logout" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Выход
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="search-box">
                    <h4 class="mb-0">Настройки аккаунта</h4>
                </div>
                <div class="top-actions">
                    <div class="user-menu">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($userData['email']); ?>" alt="Avatar" class="avatar">
                        <span><?php echo htmlspecialchars($userData['email']); ?></span>
                    </div>
                </div>
            </div>

            <div class="content-area p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Настройки Telegram -->
                        <div class="settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0"><i class="fab fa-telegram me-2 text-primary"></i> Интеграция с Telegram</h5>
                            </div>
                            <div class="settings-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_telegram">
                                    <div class="mb-3">
                                        <label class="form-label">Токен бота</label>
                                        <input type="password" name="telegram_token" class="form-control" value="<?php echo htmlspecialchars($userData['telegram_token'] ? '********' : ''); ?>" placeholder="Введите токен бота">
                                        <small class="text-muted">Если вы не хотите менять токен, оставьте поле с восьмью звездочками.</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Chat ID канала</label>
                                        <input type="text" name="telegram_chat_id" class="form-control" value="<?php echo htmlspecialchars($userData['telegram_chat_id']); ?>" placeholder="-100...">
                                    </div>
                                    <button type="submit" class="btn-save">Сохранить настройки Telegram</button>
                                </form>
                            </div>
                        </div>

                        <!-- Смена пароля -->
                        <div class="settings-card">
                            <div class="settings-header">
                                <h5 class="mb-0"><i class="fas fa-lock me-2 text-warning"></i> Безопасность</h5>
                            </div>
                            <div class="settings-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label class="form-label">Текущий пароль</label>
                                        <input type="password" name="old_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Новый пароль</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Подтверждение пароля</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn-save">Сменить пароль</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>