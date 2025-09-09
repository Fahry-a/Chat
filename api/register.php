<?php
/**
 * User Registration API Endpoint
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/FileHandler.php';

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
    
    // Handle form data (including file upload)
    if (empty($input)) {
        $input = $_POST;
    }
    
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $avatar = null;
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($password)) {
        throw new Exception('Name, email, and password are required');
    }
    
    // Handle avatar upload if provided
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileHandler = new FileHandler();
        
        // Validate that it's an image
        $mimeType = mime_content_type($_FILES['avatar']['tmp_name']);
        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            throw new Exception('Avatar must be an image file');
        }
        
        // Validate file size (max 2MB for avatar)
        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            throw new Exception('Avatar file size must be less than 2MB');
        }
        
        try {
            // Create avatars directory if it doesn't exist
            $avatarDir = UPLOAD_PATH . 'avatars/';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }
            
            // Generate unique filename for avatar
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarFilename = uniqid('avatar_') . '.' . $extension;
            $avatarPath = $avatarDir . $avatarFilename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarPath)) {
                $avatar = 'avatars/' . $avatarFilename;
            }
        } catch (Exception $e) {
            // Avatar upload failed, but continue with registration
            error_log('Avatar upload failed: ' . $e->getMessage());
        }
    }
    
    // Register user
    $userId = $auth->register($name, $email, $password, $avatar);
    
    // Get user data
    $user = $auth->getCurrentUser();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'avatar' => $user['avatar'] ? UPLOAD_URL . $user['avatar'] : null
        ],
        'csrf_token' => $auth->generateCSRFToken()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}