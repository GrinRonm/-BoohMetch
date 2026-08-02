<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение Telegram - BoohMetch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
        }
        .setup-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .step {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .step-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 10px;
        }
        .step-content {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-left: 40px;
        }
        .code-block {
            background: #e8e8e8;
            padding: 8px 12px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
            margin: 5px 0;
            word-break: break-all;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 5px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .tip {
            background: #e3f2fd;
            padding: 10px 12px;
            border-radius: 5px;
            font-size: 13px;
            color: #1565c0;
            margin-top: 10px;
            border-left: 3px solid #1565c0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="setup-title">
            <i class="fas fa-paper-plane"></i> Подключение Telegram
        </h1>

        <p style="text-align: center; color: #666; margin-bottom: 30px;">
            Для работы BoohMetch нужно подключить Telegram-бот. Это безопасно и займёт несколько минут.
        </p>

        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '/';
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (isset($error) && $error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 30px;">
            <div class="step">
                <div class="step-title">
                    <span class="step-number">1</span>
                    Создайте Telegram-бота
                </div>
                <div class="step-content">
                    <p>Напишите @BotFather в Telegram (или перейдите по ссылке t.me/BotFather)</p>
                    <p>Отправьте команду: <code>/newbot</code></p>
                    <p>Следуйте инструкциям и скопируйте токен бота (выглядит как: <code>1234567890:ABCdefGHIjklMNOpqrsTUVwxyzABCdefGHI</code>)</p>
                </div>
            </div>

            <div class="step">
                <div class="step-title">
                    <span class="step-number">2</span>
                    Создайте Telegram-канал
                </div>
                <div class="step-content">
                    <p>В Telegram нажмите «+» → «Новый канал»</p>
                    <p>Выберите приватный канал и назовите его (например, «BoohMetch Storage»)</p>
                    <p>Добавьте бота как администратора канала</p>
                </div>
            </div>

            <div class="step">
                <div class="step-title">
                    <span class="step-number">3</span>
                    Получите Chat ID канала
                </div>
                <div class="step-content">
                    <p>Отправьте боту @userinfobot сообщение в канал</p>
                    <p>Или используйте Telegram Web и посмотрите URL канала: <code>https://web.telegram.org/k/#-1001234567890</code> (Chat ID: <code>-1001234567890</code>)</p>
                    <div class="tip">
                        <strong>💡 Совет:</strong> Если это приватный канал, Chat ID будет начинаться с <code>-100</code>
                    </div>
                </div>
            </div>
        </div>

        <div style="border-top: 2px solid #eee; padding-top: 30px;">
            <h5 style="margin-bottom: 20px;">Укажите данные вашего бота</h5>

            <form method="POST">
                <input type="hidden" name="action" value="save">

                <div class="form-group">
                    <label for="telegram_token" class="form-label">Токен бота</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="telegram_token"
                        name="telegram_token"
                        placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
                        required
                    >
                    <small class="form-text text-muted">Пример: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11</small>
                </div>

                <div class="form-group">
                    <label for="telegram_chat_id" class="form-label">Chat ID канала</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="telegram_chat_id"
                        name="telegram_chat_id"
                        placeholder="-1001234567890"
                        required
                    >
                    <small class="form-text text-muted">Пример: -1001234567890</small>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Подключить Telegram
                </button>
            </form>
        </div>

        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
            <strong>⚠️ Безопасность:</strong> Ваш токен бота будет зашифрован и храниться на сервере. Никогда не делитесь токеном с другими!
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
