<?php
/**
 * Authentication and Session Management Class
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
    
    /**
     * Register a new user
     */
    public function register($name, $email, $password, $avatar = null) {
        // Validate input
        if (empty($name) || empty($email) || empty($password)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters');
        }
        
        // Check if email already exists
        $existing = $this->db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            throw new Exception('Email already registered');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $sql = 'INSERT INTO users (name, email, password, avatar) VALUES (?, ?, ?, ?)';
        $this->db->execute($sql, [$name, $email, $hashedPassword, $avatar]);
        
        $userId = $this->db->lastInsertId();
        
        // Auto-login after registration
        $this->createSession($userId);
        
        return $userId;
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $remember = false) {
        if (empty($email) || empty($password)) {
            throw new Exception('Email and password are required');
        }
        
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception('Invalid email or password');
        }
        
        $this->createSession($user['id']);
        $this->updateUserStatus($user['id'], true);
        
        return $user;
    }
    
    /**
     * Create user session
     */
    private function createSession($userId) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Generate CSRF token
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->updateUserStatus($_SESSION['user_id'], false);
        }
        
        session_unset();
        session_destroy();
        
        // Start new session for guest access
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetch('SELECT id, name, email, avatar, last_seen FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }
    
    /**
     * Update user online status
     */
    public function updateUserStatus($userId, $isOnline) {
        $sql = 'UPDATE users SET is_online = ?, last_seen = NOW() WHERE id = ?';
        $this->db->execute($sql, [$isOnline ? 1 : 0, $userId]);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Require authentication
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }
    }
    
    /**
     * Update user activity
     */
    public function updateActivity() {
        if ($this->isLoggedIn()) {
            $this->updateUserStatus($this->getCurrentUserId(), true);
        }
    }
}