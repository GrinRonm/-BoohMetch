<?php
/**
 * Класс для работы с базой данных SQLite
 */

class Database
{
    private static ?Database $instance = null;
    private ?\PDO $pdo = null;
    private array $config = [];

    private function __construct(array $config = [])
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Получить синглтон экземпляра базы данных
     */
    public static function getInstance(array $config = []): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Подключиться к базе данных
     */
    private function connect(): void
    {
        try {
            $dbPath = DB_PATH;
            
            // Создаём папку, если её нет
            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Включаем иностранные ключи
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // Инициализируем базу данных
            $this->initDatabase();
            
        } catch (\PDOException $e) {
            $this->logError('Ошибка подключения к БД: ' . $e->getMessage());
            throw new \Exception('Ошибка подключения к базе данных');
        }
    }

    /**
     * Инициализировать базу данных (создать таблицы, если их нет)
     */
    private function initDatabase(): void
    {
        $sqlFile = APP_ROOT . 'storage/database/init.sql';
        
        if (!file_exists($sqlFile)) {
            throw new \Exception('Файл инициализации базы данных не найден: ' . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        
        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            $this->logError('Ошибка инициализации БД: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Выполнить запрос с параметрами
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            $this->logError('Ошибка выполнения запроса: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw $e;
        }
    }

    /**
     * Получить одну запись
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch() ?: null;
    }

    /**
     * Получить все записи
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Получить количество затронутых строк
     */
    public function getRowCount(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Получить последний вставленный ID
     */
    public function getLastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Начать транзакцию
     */
    public function beginTransaction(): void
    {
        try {
            $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            $this->logError('Ошибка начала транзакции: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Подтвердить транзакцию
     */
    public function commit(): void
    {
        try {
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->logError('Ошибка подтверждения транзакции: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Откатить транзакцию
     */
    public function rollback(): void
    {
        try {
            $this->pdo->rollBack();
        } catch (\PDOException $e) {
            $this->logError('Ошибка отката транзакции: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Логирование ошибок
     */
    private function logError(string $message): void
    {
        $logFile = APP_ROOT . 'storage/logs/database.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        if (DEBUG) {
            echo $logMessage;
        }
    }

    /**
     * Получить PDO объект
     */
    public function getPDO(): \PDO
    {
        return $this->pdo;
    }
}

?>
