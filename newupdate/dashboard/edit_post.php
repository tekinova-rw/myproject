<?php
// dashboard/edit_post.php
require_once '../config/config.php';

if (!isAdmin() && !isCompany()) {
    redirect('../login.php');
}

$id = (int)$_GET['id'];
$pdo = getDB();

// Check permissions
if (isCompany()) {
    $stmt = $pdo->prepare("SELECT * FROM opportunities WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM opportunities WHERE id = ?");
    $stmt->execute([$id]);
}
$post = $stmt->fetch();

if (!$post) {
    redirect(isAdmin() ? 'admin.php?tab=posts' : 'company.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $category = sanitizeInput($_POST['category']);
    $description = $_POST['description'];
    $location = sanitizeInput($_POST['location']);
    $degree_required = sanitizeInput($_POST['degree_required']);
    $qualifications = array_map('sanitizeInput', explode("\n", $_POST['qualifications']));
    $application_method = sanitizeInput($_POST['application_method']);
    $application_value = sanitizeInput($_POST['application_value']);
    $deadline = sanitizeInput($_POST['deadline']);
    
    $qualifications_json = json_encode(array_filter($qualifications));
    
    $stmt = $pdo->prepare("UPDATE opportunities SET title=?, category=?, description=?, location=?, degree_required=?, qualifications=?, application_method=?, application_value=?, deadline=? WHERE id=?");
    $stmt->execute([$title, $category, $description, $location, $degree_required, $qualifications_json, $application_method, $application_value, $deadline, $id]);
    
    redirect(isAdmin() ? 'admin.php?tab=posts' : 'company.php');
}
?>
<!-- Similar to create_post.php but with values filled in -->