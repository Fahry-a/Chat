<?php
/**
 * Send Message API Endpoint
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Chat.php';
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
    $auth->requireAuth();
    $auth->updateActivity();
    
    $chat = new Chat();
    $userId = $auth->getCurrentUserId();
    
    // Get input data
    $recipientId = $_POST['recipient_id'] ?? null;
    $messageBody = trim($_POST['message'] ?? '');
    $fileId = null;
    
    if (empty($recipientId)) {
        throw new Exception('Recipient ID is required');
    }
    
    if ($recipientId == $userId) {
        throw new Exception('Cannot send message to yourself');
    }
    
    // Check if recipient exists
    $db = Database::getInstance();
    $recipient = $db->fetch('SELECT id FROM users WHERE id = ?', [$recipientId]);
    if (!$recipient) {
        throw new Exception('Recipient not found');
    }
    
    // Handle file upload if present
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileHandler = new FileHandler();
        $uploadedFile = $fileHandler->uploadFile($_FILES['file'], $userId);
        $fileId = $uploadedFile['id'];
    }
    
    // Check if we have either message or file
    if (empty($messageBody) && empty($fileId)) {
        throw new Exception('Message text or file is required');
    }
    
    // Send message
    $message = $chat->sendMessage($userId, $recipientId, $messageBody, $fileId);
    
    // Format response
    if ($message['file_path']) {
        $message['file_url'] = UPLOAD_URL . $message['file_path'];
        $message['file_size_formatted'] = FileHandler::formatFileSize($message['size']);
    }
    
    $message['is_own'] = true;
    $message['time_formatted'] = $chat->formatMessageTime($message['created_at']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Send message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}