<?php
// dashboard/company.php
require_once '../config/config.php';

if (!isCompany()) {
    redirect('../login.php');
}

if (!isCompanyApproved($_SESSION['user_id'])) {
    echo "<div class='alert alert-warning'>Your account is pending approval. Please wait for admin approval.</div>";
    exit();
}

$pdo = getDB();
$company_id = $_SESSION['user_id'];

// Handle post deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM opportunities WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);
    redirect('company.php');
}

// Fetch company posts
$stmt = $pdo->prepare("SELECT * FROM opportunities WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$company_id]);
$posts = $stmt->fetchAll();

// Fetch company info
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - Opportunity Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">Company Dashboard - <?php echo htmlspecialchars($company['name']); ?></span>
            <div>
                <a href="create_post.php" class="btn btn-success btn-sm me-2">+ New Post</a>
                <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="../uploads/<?php echo htmlspecialchars($company['logo']); ?>" 
                             class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                        <h5><?php echo htmlspecialchars($company['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($company['email']); ?></p>
                        <?php if($company['website']): ?>
                            <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">Visit Website</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h4>Your Opportunities</h4>
                    </div>
                    <div class="card-body">
                        <?php if(empty($posts)): ?>
                            <div class="alert alert-info">You haven't posted any opportunities yet. <a href="create_post.php">Create your first post</a></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Title</th><th>Category</th><th>Deadline</th><th>Status</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($posts as $post): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                                            <td><?php echo ucfirst($post['category']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($post['deadline'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $post['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $post['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../view_post.php?id=<?php echo $post['id']; ?>" class="btn btn-info btn-sm" target="_blank">View</a>
                                                <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                                <a href="?delete=<?php echo $post['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this post?')">Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>