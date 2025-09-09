<?php
/**
 * User Login API Endpoint
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

setCORSHeaders();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $auth = new Auth();
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        $input = $_POST;
    }
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $remember = isset($input['remember']) ? (bool)$input['remember'] : false;
    
    // Validate required fields
    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required');
    }
    
    // Attempt login
    $user = $auth->login($email, $password, $remember);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ? UPLOAD_URL . $user['avatar'] : null,
            'last_seen' => $user['last_seen']
        ],
        'csrf_token' => $auth->generateCSRFToken()
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}