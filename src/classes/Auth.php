<?php
/**
 * Класс для управления сессиями и авторизацией
 */

class Auth
{
    private Database $db;
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_EMAIL = 'user_email';

    public function __construct(Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->startSession();
    }

    /**
     * Начать сессию
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
            
            // Проверяем timeout сессии
            if (isset($_SESSION['last_activity']) && 
                time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
            } else {
                $_SESSION['last_activity'] = time();
            }
        }
    }

    /**
     * Зарегистрировать нового пользователя
     */
    public function register(string $email, string $password): array
    {
        try {
            // Валидация email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Некорректный email'];
            }

            // Валидация пароля
            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'Пароль должен быть не менее 6 символов'];
            }

            // Проверяем, существует ли пользователь
            $existing = $this->db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                return ['success' => false, 'message' => 'Пользователь с таким email уже существует'];
            }

            // Хешируем пароль
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Вставляем пользователя
            $this->db->query(
                'INSERT INTO users (email, password) VALUES (?, ?)',
                [$email, $hashedPassword]
            );

            $userId = $this->db->getLastInsertId();

            // Авторизуем пользователя
            $this->setUser($userId, $email);

            return ['success' => true, 'message' => 'Регистрация успешна', 'user_id' => $userId];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка регистрации: ' . $e->getMessage()];
        }
    }

    /**
     * Логин пользователя
     */
    public function login(string $email, string $password): array
    {
        try {
            // Получаем пользователя
            $user = $this->db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);

            if (!$user) {
                return ['success' => false, 'message' => 'Пользователь не найден'];
            }

            // Проверяем пароль
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Неверный пароль'];
            }

            // Устанавливаем сессию
            $this->setUser($user['id'], $user['email']);

            return ['success' => true, 'message' => 'Вы успешно вошли', 'user_id' => $user['id']];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка входа: ' . $e->getMessage()];
        }
    }

    /**
     * Выход из системы
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        setcookie(SESSION_NAME, '', 0, '/');
    }

    /**
     * Установить данные пользователя в сессию
     */
    private function setUser(int $userId, string $email): void
    {
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_EMAIL] = $email;
    }

    /**
     * Получить ID текущего пользователя
     */
    public function getUserId(): ?int
    {
        return $_SESSION[self::SESSION_USER_ID] ?? null;
    }

    /**
     * Получить email текущего пользователя
     */
    public function getUserEmail(): ?string
    {
        return $_SESSION[self::SESSION_EMAIL] ?? null;
    }

    /**
     * Проверить, авторизован ли пользователь
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION[self::SESSION_USER_ID]);
    }

    /**
     * Получить полную информацию о пользователе
     */
    public function getUserData(): ?array
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }

        return $this->db->fetchOne('SELECT id, email, telegram_chat_id, telegram_token, created_at FROM users WHERE id = ?', [$userId]);
    }

    /**
     * Сменить пароль
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword): array
    {
        try {
            $user = $this->db->fetchOne('SELECT password FROM users WHERE id = ?', [$userId]);
            
            if (!$user || !password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Неверный текущий пароль'];
            }

            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'Новый пароль слишком короткий'];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $this->db->query(
                'UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$hashedPassword, $userId]
            );

            return ['success' => true, 'message' => 'Пароль успешно изменен'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Ошибка при смене пароля: ' . $e->getMessage()];
        }
    }
}

?>
