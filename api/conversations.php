<?php
/**
 * Conversations API Endpoint
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
    
    // Get conversations for current user
    $search = $_GET['search'] ?? '';
    $conversations = $chat->getConversations($userId, $search);
    
    // Format conversations data
    foreach ($conversations as &$conversation) {
        $conversation['other_user_avatar_url'] = $conversation['other_user_avatar'] ? UPLOAD_URL . $conversation['other_user_avatar'] : null;
        $conversation['other_user_status'] = $conversation['other_user_online'] ? 'online' : 'offline';
        
        // Format last seen
        if ($conversation['other_user_last_seen']) {
            $lastSeenTime = strtotime($conversation['other_user_last_seen']);
            $now = time();
            $diff = $now - $lastSeenTime;
            
            if ($conversation['other_user_online']) {
                $conversation['other_user_last_seen_formatted'] = 'Online';
            } elseif ($diff < 300) { // 5 minutes
                $conversation['other_user_last_seen_formatted'] = 'Just now';
            } elseif ($diff < 3600) { // 1 hour
                $conversation['other_user_last_seen_formatted'] = floor($diff / 60) . ' min ago';
            } elseif ($diff < 86400) { // 1 day
                $conversation['other_user_last_seen_formatted'] = floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
            } else {
                $conversation['other_user_last_seen_formatted'] = date('M j', $lastSeenTime);
            }
        } else {
            $conversation['other_user_last_seen_formatted'] = 'Never';
        }
        
        // Ensure unread_count is integer
        $conversation['unread_count'] = (int)$conversation['unread_count'];
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $conversations
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Conversations API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}