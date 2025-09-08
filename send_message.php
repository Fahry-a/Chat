<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message_content = isset($_POST['message']) ? trim($_POST['message']) : '';
$message_type = 'text';
$file_path = null;

// Validate receiver ID
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Receiver ID tidak valid']);
    exit();
}

// Validate receiver exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$receiver_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Penerima tidak ditemukan']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// Handle file upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $file_type = $file['type'];
    $file_size = $file['size'];
    
    // Check file size (max 50MB)
    if ($file_size > 50 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File terlalu besar (max 50MB)']);
        exit();
    }
    
    // Determine message type and allowed extensions
    if (strpos($file_type, 'image/') === 0) {
        $message_type = 'image';
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $upload_dir = 'uploads/images/';
    } elseif (strpos($file_type, 'video/') === 0) {
        $message_type = 'video';
        $allowed_types = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
        $upload_dir = 'uploads/videos/';
    } elseif (strpos($file_type, 'audio/') === 0) {
        $message_type = 'audio';
        $allowed_types = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];
        $upload_dir = 'uploads/audios/';
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipe file tidak didukung']);
        exit();
    }
    
    // Create upload directory if not exists
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload']);
            exit();
        }
    }
    
    // Get file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_extension, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Ekstensi file tidak diizinkan']);
        exit();
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;
    
    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupload file']);
        exit();
    }
    
    $file_path = $target_file;
}

// Validate message content
if (empty($message_content) && empty($file_path)) {
    echo json_encode(['success' => false, 'message' => 'Pesan atau file harus diisi']);
    exit();
}

// Insert message to database
try {
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_type, message_content, file_path, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $sender_id,
        $receiver_id,
        $message_type,
        $message_content,
        $file_path
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesan ke database']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>