<?php
/**
 * Chat and Messaging Management Class
 */
class Chat {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get or create conversation between two users
     */
    public function getOrCreateConversation($user1Id, $user2Id) {
        // Ensure user1Id is always the smaller ID for consistency
        $smaller = min($user1Id, $user2Id);
        $larger = max($user1Id, $user2Id);
        
        // Check if conversation exists
        $sql = 'SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?';
        $conversation = $this->db->fetch($sql, [$smaller, $larger]);
        
        if (!$conversation) {
            // Create new conversation
            $sql = 'INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)';
            $this->db->execute($sql, [$smaller, $larger]);
            $conversationId = $this->db->lastInsertId();
            
            $conversation = $this->db->fetch('SELECT * FROM conversations WHERE id = ?', [$conversationId]);
        }
        
        return $conversation;
    }
    
    /**
     * Send a message
     */
    public function sendMessage($senderId, $recipientId, $messageBody = null, $fileId = null) {
        if (empty($messageBody) && empty($fileId)) {
            throw new Exception('Message body or file is required');
        }
        
        // Get or create conversation
        $conversation = $this->getOrCreateConversation($senderId, $recipientId);
        
        // Determine message type
        $messageType = 'text';
        if ($fileId) {
            $file = $this->db->fetch('SELECT mime_type FROM files WHERE id = ?', [$fileId]);
            if ($file) {
                if (strpos($file['mime_type'], 'image/') === 0) {
                    $messageType = 'image';
                } elseif (strpos($file['mime_type'], 'video/') === 0) {
                    $messageType = 'video';
                } elseif (strpos($file['mime_type'], 'audio/') === 0) {
                    $messageType = 'audio';
                } else {
                    $messageType = 'file';
                }
            }
        }
        
        // Insert message
        $sql = 'INSERT INTO messages (conversation_id, sender_id, message_body, file_id, message_type) VALUES (?, ?, ?, ?, ?)';
        $this->db->execute($sql, [$conversation['id'], $senderId, $messageBody, $fileId, $messageType]);
        $messageId = $this->db->lastInsertId();
        
        // Update conversation last message
        $this->db->execute(
            'UPDATE conversations SET last_message_id = ?, last_message_at = NOW() WHERE id = ?',
            [$messageId, $conversation['id']]
        );
        
        return $this->getMessageById($messageId);
    }
    
    /**
     * Get messages for a conversation
     */
    public function getMessages($conversationId, $userId, $limit = 50, $offset = 0) {
        $sql = 'SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                       f.original_name, f.mime_type, f.size, f.file_path
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                LEFT JOIN files f ON m.file_id = f.id
                WHERE m.conversation_id = ? 
                  AND (m.deleted_by_sender = 0 OR m.sender_id != ?)
                  AND (m.deleted_by_receiver = 0 OR m.sender_id = ?)
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?';
        
        $messages = $this->db->fetchAll($sql, [$conversationId, $userId, $userId, $limit, $offset]);
        
        // Add file URLs and format data
        foreach ($messages as &$message) {
            if ($message['file_path']) {
                $message['file_url'] = UPLOAD_URL . $message['file_path'];
                $message['file_size_formatted'] = FileHandler::formatFileSize($message['size']);
            }
            $message['is_own'] = ($message['sender_id'] == $userId);
            $message['time_formatted'] = $this->formatMessageTime($message['created_at']);
        }
        
        return array_reverse($messages); // Return in ascending order
    }
    
    /**
     * Get message by ID
     */
    public function getMessageById($messageId) {
        $sql = 'SELECT m.*, u.name as sender_name, u.avatar as sender_avatar,
                       f.original_name, f.mime_type, f.size, f.file_path
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                LEFT JOIN files f ON m.file_id = f.id
                WHERE m.id = ?';
        
        $message = $this->db->fetch($sql, [$messageId]);
        
        if ($message && $message['file_path']) {
            $message['file_url'] = UPLOAD_URL . $message['file_path'];
            $message['file_size_formatted'] = FileHandler::formatFileSize($message['size']);
        }
        
        return $message;
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead($conversationId, $userId) {
        $sql = 'UPDATE messages SET is_read = 1, read_at = NOW() 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0';
        $this->db->execute($sql, [$conversationId, $userId]);
        
        return $this->db->getConnection()->rowCount();
    }
    
    /**
     * Delete message
     */
    public function deleteMessage($messageId, $userId, $deleteForAll = false) {
        $message = $this->getMessageById($messageId);
        
        if (!$message) {
            throw new Exception('Message not found');
        }
        
        // Get conversation to determine if user is sender or receiver
        $conversation = $this->db->fetch('SELECT * FROM conversations WHERE id = ?', [$message['conversation_id']]);
        $isSender = ($message['sender_id'] == $userId);
        $isParticipant = ($conversation['user1_id'] == $userId || $conversation['user2_id'] == $userId);
        
        if (!$isParticipant) {
            throw new Exception('Permission denied');
        }
        
        if ($deleteForAll && !$isSender) {
            throw new Exception('Only sender can delete for everyone');
        }
        
        if ($deleteForAll) {
            // Delete for everyone (physical delete or mark both flags)
            $this->db->execute('UPDATE messages SET deleted_by_sender = 1, deleted_by_receiver = 1 WHERE id = ?', [$messageId]);
        } else {
            // Delete only for current user
            if ($isSender) {
                $this->db->execute('UPDATE messages SET deleted_by_sender = 1 WHERE id = ?', [$messageId]);
            } else {
                $this->db->execute('UPDATE messages SET deleted_by_receiver = 1 WHERE id = ?', [$messageId]);
            }
        }
        
        return true;
    }
    
    /**
     * Get user's conversations
     */
    public function getConversations($userId, $search = '') {
        $searchCondition = '';
        $params = [$userId, $userId];
        
        if (!empty($search)) {
            $searchCondition = 'AND (u1.name LIKE ? OR u2.name LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql = "SELECT c.*, 
                       CASE 
                         WHEN c.user1_id = ? THEN u2.id 
                         ELSE u1.id 
                       END as other_user_id,
                       CASE 
                         WHEN c.user1_id = ? THEN u2.name 
                         ELSE u1.name 
                       END as other_user_name,
                       CASE 
                         WHEN c.user1_id = ? THEN u2.avatar 
                         ELSE u1.avatar 
                       END as other_user_avatar,
                       CASE 
                         WHEN c.user1_id = ? THEN u2.is_online 
                         ELSE u1.is_online 
                       END as other_user_online,
                       CASE 
                         WHEN c.user1_id = ? THEN u2.last_seen 
                         ELSE u1.last_seen 
                       END as other_user_last_seen,
                       m.message_body as last_message,
                       m.message_type as last_message_type,
                       m.created_at as last_message_time,
                       m.sender_id as last_message_sender,
                       (SELECT COUNT(*) FROM messages m2 
                        WHERE m2.conversation_id = c.id 
                          AND m2.sender_id != ? 
                          AND m2.is_read = 0
                          AND m2.deleted_by_receiver = 0) as unread_count
                FROM conversations c
                LEFT JOIN users u1 ON c.user1_id = u1.id
                LEFT JOIN users u2 ON c.user2_id = u2.id
                LEFT JOIN messages m ON c.last_message_id = m.id
                WHERE (c.user1_id = ? OR c.user2_id = ?) {$searchCondition}
                ORDER BY c.last_message_at DESC";
        
        $conversations = $this->db->fetchAll($sql, array_merge([$userId, $userId, $userId, $userId, $userId, $userId], $params));
        
        foreach ($conversations as &$conv) {
            $conv['last_message_formatted'] = $this->formatLastMessage($conv);
            $conv['last_message_time_formatted'] = $this->formatMessageTime($conv['last_message_time']);
        }
        
        return $conversations;
    }
    
    /**
     * Get user contacts
     */
    public function getContacts($userId, $search = '') {
        $searchCondition = '';
        $params = [$userId];
        
        if (!empty($search)) {
            $searchCondition = 'AND (u.name LIKE ? OR u.email LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql = "SELECT u.id, u.name, u.email, u.avatar, u.is_online, u.last_seen,
                       c.contact_name
                FROM users u
                LEFT JOIN contacts c ON u.id = c.contact_user_id AND c.user_id = ?
                WHERE u.id != ? {$searchCondition}
                ORDER BY u.is_online DESC, u.name ASC";
        
        return $this->db->fetchAll($sql, array_merge($params, [$userId]));
    }
    
    /**
     * Add contact
     */
    public function addContact($userId, $contactUserId, $contactName = null) {
        // Check if users exist
        $user = $this->db->fetch('SELECT id FROM users WHERE id = ?', [$contactUserId]);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Check if already a contact
        $existing = $this->db->fetch('SELECT id FROM contacts WHERE user_id = ? AND contact_user_id = ?', [$userId, $contactUserId]);
        if ($existing) {
            throw new Exception('Already in contacts');
        }
        
        // Add contact
        $sql = 'INSERT INTO contacts (user_id, contact_user_id, contact_name) VALUES (?, ?, ?)';
        $this->db->execute($sql, [$userId, $contactUserId, $contactName]);
        
        return true;
    }
    
    /**
     * Get new messages since timestamp (for polling)
     */
    public function getNewMessages($userId, $since) {
        $sql = 'SELECT m.*, c.user1_id, c.user2_id, u.name as sender_name, u.avatar as sender_avatar
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE (c.user1_id = ? OR c.user2_id = ?) 
                  AND m.sender_id != ?
                  AND m.created_at > ?
                  AND m.deleted_by_receiver = 0
                ORDER BY m.created_at ASC';
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId, $since]);
    }
    
    /**
     * Format message time for display
     */
    private function formatMessageTime($timestamp) {
        if (empty($timestamp)) return '';
        
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        } elseif ($diff < 604800) {
            return floor($diff / 86400) . 'd ago';
        } else {
            return date('M j', $time);
        }
    }
    
    /**
     * Format last message for conversation list
     */
    private function formatLastMessage($conversation) {
        if (empty($conversation['last_message']) && empty($conversation['last_message_type'])) {
            return 'No messages yet';
        }
        
        $prefix = '';
        if ($conversation['last_message_sender'] == $conversation['other_user_id']) {
            // Message from other user
            $prefix = '';
        } else {
            // Message from current user
            $prefix = 'You: ';
        }
        
        switch ($conversation['last_message_type']) {
            case 'image':
                return $prefix . '📷 Image';
            case 'video':
                return $prefix . '🎥 Video';
            case 'audio':
                return $prefix . '🎵 Audio';
            case 'file':
                return $prefix . '📎 File';
            default:
                return $prefix . ($conversation['last_message'] ?? 'Message');
        }
    }
    
    /**
     * Get conversation between two users
     */
    public function getConversationBetweenUsers($user1Id, $user2Id) {
        $smaller = min($user1Id, $user2Id);
        $larger = max($user1Id, $user2Id);
        
        return $this->db->fetch('SELECT * FROM conversations WHERE user1_id = ? AND user2_id = ?', [$smaller, $larger]);
    }
    
    /**
     * Get unread message count for user
     */
    public function getUnreadCount($userId) {
        $sql = 'SELECT COUNT(*) as count FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                WHERE (c.user1_id = ? OR c.user2_id = ?)
                  AND m.sender_id != ?
                  AND m.is_read = 0
                  AND m.deleted_by_receiver = 0';
        
        $result = $this->db->fetch($sql, [$userId, $userId, $userId]);
        return $result['count'] ?? 0;
    }
}