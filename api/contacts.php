<?php
/**
 * Contacts API Endpoint
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

try {
    $auth = new Auth();
    $auth->requireAuth();
    $auth->updateActivity();
    
    $chat = new Chat();
    $userId = $auth->getCurrentUserId();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get contacts list
            $search = $_GET['search'] ?? '';
            $contacts = $chat->getContacts($userId, $search);
            
            // Format contacts data
            foreach ($contacts as &$contact) {
                $contact['avatar_url'] = $contact['avatar'] ? UPLOAD_URL . $contact['avatar'] : null;
                $contact['status'] = $contact['is_online'] ? 'online' : 'offline';
                $contact['last_seen_formatted'] = $contact['last_seen'] ? date('M j, Y g:i A', strtotime($contact['last_seen'])) : null;
            }
            
            echo json_encode([
                'success' => true,
                'contacts' => $contacts
            ]);
            break;
            
        case 'POST':
            // Add new contact
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input)) {
                $input = $_POST;
            }
            
            $contactUserId = $input['contact_user_id'] ?? null;
            $contactName = $input['contact_name'] ?? null;
            
            if (empty($contactUserId)) {
                throw new Exception('Contact user ID is required');
            }
            
            if ($contactUserId == $userId) {
                throw new Exception('Cannot add yourself as contact');
            }
            
            $chat->addContact($userId, $contactUserId, $contactName);
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact added successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Contacts API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error'
    ]);
}