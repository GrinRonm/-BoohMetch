<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поделиться файлом - BoohMetch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .share-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 40px; max-width: 500px; width: 100%; text-align: center; }
        .file-icon { font-size: 64px; color: #667eea; margin-bottom: 20px; }
        .file-name { font-size: 20px; font-weight: 600; margin-bottom: 10px; word-break: break-all; }
        .file-info { color: #777; margin-bottom: 30px; }
        .btn-download { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; padding: 12px 30px; border-radius: 8px; font-weight: 600; width: 100%; }
        .btn-download:hover { opacity: 0.9; color: white; }
    </style>
</head>
<body>
    <div class="share-card">
        <div class="file-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="file-name"><?php echo htmlspecialchars($sharedFile['original_name']); ?></div>
        <div class="file-info">
            Размер: <?php echo formatBytes($sharedFile['size']); ?><br>
            Загружен: <?php echo date('d.m.Y H:i', strtotime($sharedFile['created_at'])); ?>
        </div>
        
        <?php if ($share['allow_download']): ?>
            <a href="?download=1" class="btn btn-download">
                <i class="fas fa-download me-2"></i> Скачать файл
            </a>
        <?php else: ?>
            <div class="alert alert-warning">Скачивание этого файла ограничено владельцем</div>
        <?php endif; ?>
        
        <div class="mt-4 text-muted small">
            Файл предоставлен через BoohMetch
        </div>
    </div>
<?php 
// formatBytes уже определена в src/helpers/functions.php
?>
</body>
</html>