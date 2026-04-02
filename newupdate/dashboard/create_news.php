<?php
// dashboard/create_news.php
require_once '../config/config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $content = $_POST['content'];
    
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO news (title, content) VALUES (?, ?)");
    $stmt->execute([$title, $content]);
}

redirect('admin.php?tab=news');
?>