<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поделиться папкой - BoohMetch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; padding: 40px 20px; }
        .container { max-width: 800px; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); padding: 40px; }
        .folder-header { display: flex; align-items: center; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px; }
        .folder-icon { font-size: 40px; color: #ffca28; margin-right: 20px; }
        .folder-name { font-size: 24px; font-weight: 700; color: #333; }
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-item { display: flex; align-items: center; padding: 12px 15px; border-radius: 8px; transition: background 0.2s; margin-bottom: 5px; }
        .file-item:hover { background: #f8f9fa; }
        .file-icon { font-size: 20px; color: #667eea; margin-right: 15px; width: 25px; text-align: center; }
        .file-name { flex-grow: 1; font-weight: 500; color: #444; }
        .file-size { color: #888; font-size: 14px; margin-right: 20px; }
        .btn-download { color: #667eea; text-decoration: none; font-size: 18px; padding: 5px; }
        .btn-download:hover { color: #764ba2; }
    </style>
</head>
<body>
    <div class="container">
        <div class="folder-header">
            <div class="folder-icon"><i class="fas fa-folder"></i></div>
            <div class="folder-name"><?php echo htmlspecialchars($sharedFolder['name']); ?></div>
        </div>
        
        <div class="file-list">
            <?php 
            $shareManager = new ShareManager($db, 0);
            $content = $shareManager->getSharedFolder($token);
            if (empty($content['files'])): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3"></i><br>
                    Папка пуста
                </div>
            <?php else: ?>
                <?php foreach ($content['files'] as $file): ?>
                    <div class="file-item">
                        <div class="file-icon"><i class="fas <?php echo getFileIcon($file['original_name']); ?>"></i></div>
                        <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                        <div class="file-size"><?php echo formatBytes($file['size']); ?></div>
                        <?php if ($share['allow_download']): ?>
                            <a href="/share/<?php echo $token; ?>?download=1&file_id=<?php echo $file['id']; ?>" class="btn-download" title="Скачать">
                                <i class="fas fa-download"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="mt-5 text-center text-muted small">
            Предоставлено через BoohMetch
        </div>
    </div>
</body>
</html>
<?php
// getFileIcon и formatBytes уже определены в src/helpers/functions.php
?>