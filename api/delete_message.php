<?php
/**
 * Delete Message API Endpoint
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Chat.php';

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
    
    $chat = new Chat();
    $userId = $auth->getCurrentUserId();
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        $input = $_POST;
    }
    
    $messageId = $input['message_id'] ?? null;
    $deleteForAll = isset($input['delete_for_all']) ? (bool)$input['delete_for_all'] : false;
    
    if (empty($messageId)) {
        throw new Exception('Message ID is required');
    }
    
    // Delete message
    $result = $chat->deleteMessage($messageId, $userId, $deleteForAll);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $deleteForAll ? 'Message deleted for everyone' : 'Message deleted for you'
        ]);
    } else {
        throw new Exception('Failed to delete message');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Delete message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}