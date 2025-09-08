<?php
require_once 'config.php';
checkLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['user_id'])) {
    echo json_encode([]);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = (int)$_POST['user_id'];

try {
    // Get messages between current user and selected user
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.username as sender_username,
            CASE 
                WHEN m.sender_id = ? THEN 1 
                ELSE 0 
            END as is_sent
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE 
            (m.sender_id = ? AND m.receiver_id = ?) OR 
            (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    
    $stmt->execute([
        $current_user_id,
        $current_user_id, $other_user_id,
        $other_user_id, $current_user_id
    ]);
    
    $messages = $stmt->fetchAll();
    
    // Mark received messages as read
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$other_user_id, $current_user_id]);
    
    echo json_encode($messages);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>