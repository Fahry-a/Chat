<?php
require_once 'config.php';

// Update last seen before logout
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
?>