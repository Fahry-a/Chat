<?php
/**
 * Messages API Endpoint
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    
    $conversationId = $_GET['conversation_id'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 messages
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    
    if (empty($conversationId)) {
        throw new Exception('Conversation ID is required');
    }
    
    // Verify user has access to this conversation
    $db = Database::getInstance();
    $conversation = $db->fetch('SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)', 
                              [$conversationId, $userId, $userId]);
    
    if (!$conversation) {
        throw new Exception('Conversation not found or access denied');
    }
    
    // Get messages
    $messages = $chat->getMessages($conversationId, $userId, $limit, $offset);
    
    // Mark messages as read
    $chat->markAsRead($conversationId, $userId);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'conversation_id' => $conversationId,
        'has_more' => count($messages) === $limit
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Messages API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}