<?php
require_once 'config.php';
checkLogin();

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Get all users except current user
$stmt = $pdo->prepare("SELECT id, username, last_seen FROM users WHERE id != ? ORDER BY last_seen DESC");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat App</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($current_user['username']); ?></h3>
                    <span class="status online">Online</span>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            
            <div class="users-list">
                <h4>Pengguna Online</h4>
                <?php foreach ($users as $user): ?>
                    <div class="user-item" data-user-id="<?php echo $user['id']; ?>" onclick="selectUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-details">
                            <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                            <span class="last-seen"><?php echo date('H:i', strtotime($user['last_seen'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-header">
                <button class="menu-toggle" id="menu-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="chat-user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <h3 id="chat-username">Pilih pengguna untuk mulai chat</h3>
                        <span class="status" id="chat-status"></span>
                    </div>
                </div>
            </div>
            
            <div class="messages-container" id="messages-container">
                <div class="welcome-message">
                    <i class="fas fa-comments"></i>
                    <h3>Selamat datang di Chat App!</h3>
                    <p>Pilih pengguna di menu untuk mulai chatting</p>
                </div>
            </div>
            
            <div class="message-input-container" id="message-input-container" style="display: none;">
                <div class="file-upload-options">
                    <button type="button" id="image-btn" class="upload-btn" title="Kirim Gambar">
                        <i class="fas fa-image"></i>
                    </button>
                    <button type="button" id="video-btn" class="upload-btn" title="Kirim Video">
                        <i class="fas fa-video"></i>
                    </button>
                    <button type="button" id="audio-btn" class="upload-btn" title="Kirim Audio">
                        <i class="fas fa-microphone"></i>
                    </button>
                </div>
                
                <form id="message-form" enctype="multipart/form-data">
                    <input type="hidden" id="receiver-id" name="receiver_id">
                    <input type="file" id="file-input" name="file" style="display: none;" accept="">
                    
                    <div class="message-input">
                        <input type="text" id="message-text" name="message" placeholder="Ketik pesan...">
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="chat.js"></script>
</body>
</html>