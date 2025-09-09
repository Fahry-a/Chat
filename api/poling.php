<?php
/**
 * Polling API Endpoint for Real-time Updates
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
    
    $since = $_GET['since'] ?? null;
    $conversationId = $_GET['conversation_id'] ?? null;
    
    if (empty($since)) {
        throw new Exception('Since timestamp is required');
    }
    
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'new_messages' => [],
        'unread_count' => 0,
        'typing_users' => []
    ];
    
    // Get new messages since timestamp
    if ($conversationId) {
        // Get new messages for specific conversation
        $db = Database::getInstance();
        $sql = 'SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                       f.original_name, f.mime_type, f.size, f.file_path
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                LEFT JOIN files f ON m.file_id = f.id
                JOIN conversations c ON m.conversation_id = c.id
                WHERE m.conversation_id = ?
                  AND (c.user1_id = ? OR c.user2_id = ?)
                  AND m.created_at > ?
                  AND m.deleted_by_receiver = 0
                ORDER BY m.created_at ASC';
        
        $newMessages = $db->fetchAll($sql, [$conversationId, $userId, $userId, $since]);
        
        // Format messages
        foreach ($newMessages as &$message) {
            if ($message['file_path']) {
                $message['file_url'] = UPLOAD_URL . $message['file_path'];
                $message['file_size_formatted'] = FileHandler::formatFileSize($message['size']);
            }
            $message['is_own'] = ($message['sender_id'] == $userId);
            $message['time_formatted'] = $chat->formatMessageTime($message['created_at']);
        }
        
        $response['new_messages'] = $newMessages;
    } else {
        // Get all new messages for user
        $newMessages = $chat->getNewMessages($userId, $since);
        $response['new_messages'] = $newMessages;
    }
    
    // Get total unread count
    $response['unread_count'] = $chat->getUnreadCount($userId);
    
    // Get online users (contacts who are online)
    $db = Database::getInstance();
    $onlineContacts = $db->fetchAll(
        'SELECT u.id, u.name FROM users u 
         JOIN contacts c ON u.id = c.contact_user_id 
         WHERE c.user_id = ? AND u.is_online = 1',
        [$userId]
    );
    $response['online_contacts'] = $onlineContacts;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Polling error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}