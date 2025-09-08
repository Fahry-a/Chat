<?php
require_once 'config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: chat.php');
    exit();
} else {
    header('Location: register.php');
    exit();
}
?>