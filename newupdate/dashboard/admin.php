<?php
// dashboard/admin.php - COMPLETE ADMIN MONITORING SYSTEM
require_once '../config/config.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$pdo = getDB();
$active_tab = $_GET['tab'] ?? 'dashboard';

// Handle company actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE companies SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=companies');
    } elseif ($action === 'suspend') {
        $stmt = $pdo->prepare("UPDATE companies SET status = 'suspended' WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=companies');
    } elseif ($action === 'delete_company') {
        $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=companies');
    } elseif ($action === 'delete_post') {
        $stmt = $pdo->prepare("DELETE FROM opportunities WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=posts');
    } elseif ($action === 'delete_news') {
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        redirect('admin.php?tab=news');
    }
}

// Fetch statistics for dashboard
$total_companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$pending_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'pending'")->fetchColumn();
$approved_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'approved'")->fetchColumn();
$suspended_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'suspended'")->fetchColumn();

$total_posts = $pdo->query("SELECT COUNT(*) FROM opportunities")->fetchColumn();
$active_posts = $pdo->query("SELECT COUNT(*) FROM opportunities WHERE status = 'active' AND deadline > NOW()")->fetchColumn();
$expired_posts = $pdo->query("SELECT COUNT(*) FROM opportunities WHERE status = 'expired' OR deadline < NOW()")->fetchColumn();

$total_news = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$total_applications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$total_views = $pdo->query("SELECT SUM(views) FROM opportunities")->fetchColumn();

// Recent activities
$recent_companies = $pdo->query("SELECT * FROM companies ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_posts = $pdo->query("SELECT o.*, c.name as company_name FROM opportunities o JOIN companies c ON o.company_id = c.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
$recent_applications = $pdo->query("SELECT a.*, o.title as opportunity_title FROM applications a JOIN opportunities o ON a.opportunity_id = o.id ORDER BY a.applied_at DESC LIMIT 5")->fetchAll();

// Fetch all data for other tabs
$pending_companies_list = $pdo->query("SELECT * FROM companies WHERE status = 'pending' ORDER BY created_at DESC")->fetchAll();
$approved_companies_list = $pdo->query("SELECT * FROM companies WHERE status = 'approved' ORDER BY name")->fetchAll();
$suspended_companies_list = $pdo->query("SELECT * FROM companies WHERE status = 'suspended' ORDER BY created_at DESC")->fetchAll();
$all_posts = $pdo->query("SELECT o.*, c.name as company_name FROM opportunities o JOIN companies c ON o.company_id = c.id ORDER BY o.created_at DESC")->fetchAll();
$all_news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Complete Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }
        .bg-danger-gradient {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
        }
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .sidebar .nav-link.active {
            background: #3498db;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <div class="text-center py-4">
                    <h4 class="text-white">Admin Panel</h4>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['username']); ?></small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'companies' ? 'active' : ''; ?>" href="?tab=companies">
                        <i class="fas fa-building"></i> Companies 
                        <?php if($pending_companies > 0): ?>
                            <span class="badge bg-danger"><?php echo $pending_companies; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'posts' ? 'active' : ''; ?>" href="?tab=posts">
                        <i class="fas fa-briefcase"></i> Opportunities
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'create_post' ? 'active' : ''; ?>" href="?tab=create_post">
                        <i class="fas fa-plus-circle"></i> Create Post
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'news' ? 'active' : ''; ?>" href="?tab=news">
                        <i class="fas fa-newspaper"></i> News
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'applications' ? 'active' : ''; ?>" href="?tab=applications">
                        <i class="fas fa-file-alt"></i> Applications
                    </a>
                    <a class="nav-link <?php echo $active_tab == 'settings' ? 'active' : ''; ?>" href="?tab=settings">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php if($active_tab == 'dashboard'): ?>
                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary-gradient">
                                <p>Total Companies</p>
                                <h3><?php echo $total_companies; ?></h3>
                                <small>✅ <?php echo $approved_companies; ?> approved</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success-gradient">
                                <p>Active Opportunities</p>
                                <h3><?php echo $active_posts; ?></h3>
                                <small>📊 <?php echo $total_posts; ?> total</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning-gradient">
                                <p>Total Applications</p>
                                <h3><?php echo $total_applications; ?></h3>
                                <small>📝 pending review</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info-gradient">
                                <p>Total Views</p>
                                <h3><?php echo number_format($total_views); ?></h3>
                                <small>👁️ page views</small>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Actions Alert -->
                    <?php if($pending_companies > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong><?php echo $pending_companies; ?> companies pending approval!</strong>
                            <a href="?tab=companies" class="alert-link">Review now →</a>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <!-- Recent Companies -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Recent Registrations</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <?php foreach($recent_companies as $company): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($company['name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($company['email']); ?></small>
                                                    </div>
                                                    <span class="badge bg-<?php 
                                                        echo $company['status'] == 'approved' ? 'success' : 
                                                            ($company['status'] == 'pending' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($company['status']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">Registered: <?php echo date('M d, H:i', strtotime($company['created_at'])); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Posts -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Recent Opportunities</h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <?php foreach($recent_posts as $post): ?>
                                            <div class="list-group-item">
                                                <strong><?php echo htmlspecialchars($post['title']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($post['company_name']); ?> • 
                                                <?php echo ucfirst($post['category']); ?></small>
                                                <div class="mt-1">
                                                    <small class="text-muted">
                                                        Deadline: <?php echo date('M d, Y', strtotime($post['deadline'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif($active_tab == 'companies'): ?>
                    <!-- Pending Companies -->
                    <h3>Pending Approval <span class="badge bg-danger"><?php echo count($pending_companies_list); ?></span></h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr><th>Company</th><th>Email</th><th>Phone</th><th>Registered</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($pending_companies_list as $company): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                        <?php if($company['website']): ?>
                                            <br><small><a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">Website</a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($company['email']); ?></td>
                                    <td><?php echo htmlspecialchars($company['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <a href="?action=approve&id=<?php echo $company['id']; ?>&tab=companies" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </a>
                                        <a href="?action=delete_company&id=<?php echo $company['id']; ?>&tab=companies" class="btn btn-danger btn-sm" onclick="return confirm('Delete this company?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Approved Companies -->
                    <h3>Approved Companies <span class="badge bg-success"><?php echo count($approved_companies_list); ?></span></h3>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr><th>Company</th><th>Contact</th><th>Posts</th><th>Joined</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($approved_companies_list as $company): 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM opportunities WHERE company_id = ?");
                                    $stmt->execute([$company['id']]);
                                    $post_count = $stmt->fetchColumn();
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($company['address'] ?? 'No address'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($company['email']); ?>
                                        <?php if($company['phone']): ?>
                                            <br><small><?php echo htmlspecialchars($company['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $post_count; ?></td>
                                    <td><?php echo date('M Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <a href="?action=suspend&id=<?php echo $company['id']; ?>&tab=companies" class="btn btn-warning btn-sm" onclick="return confirm('Suspend this company?')">
                                            <i class="fas fa-pause"></i> Suspend
                                        </a>
                                        <a href="?action=delete_company&id=<?php echo $company['id']; ?>&tab=companies" class="btn btn-danger btn-sm" onclick="return confirm('Delete this company permanently?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif($active_tab == 'posts'): ?>
                    <h3>All Opportunities <span class="badge bg-info"><?php echo count($all_posts); ?></span></h3>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr><th>Title</th><th>Company</th><th>Category</th><th>Deadline</th><th>Status</th><th>Views</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_posts as $post): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><?php echo htmlspecialchars($post['company_name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($post['category']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($post['deadline'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $post['status'] == 'active' ? 'success' : 
                                                ($post['status'] == 'expired' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo number_format($post['views'] ?? 0); ?></td>
                                    <td>
                                        <a href="../view_post.php?id=<?php echo $post['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?action=delete_post&id=<?php echo $post['id']; ?>&tab=posts" class="btn btn-danger btn-sm" onclick="return confirm('Delete this post?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif($active_tab == 'create_post'): ?>
                    <h3>Create New Opportunity</h3>
                    <?php include 'create_post.php'; ?>

                <?php elseif($active_tab == 'news'): ?>
                    <h3>Manage News</h3>
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newsModal">
                                <i class="fas fa-plus"></i> Add News
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <?php foreach($all_news as $news): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5><?php echo htmlspecialchars($news['title']); ?></h5>
                                        <p><?php echo substr(strip_tags($news['content']), 0, 100); ?>...</p>
                                        <small class="text-muted">Published: <?php echo date('M d, Y', strtotime($news['created_at'])); ?></small>
                                        <?php if($news['expires_at']): ?>
                                            <br><small class="text-danger">Expires: <?php echo date('M d, Y', strtotime($news['expires_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <a href="edit_news.php?id=<?php echo $news['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="?action=delete_news&id=<?php echo $news['id']; ?>&tab=news" class="btn btn-danger btn-sm" onclick="return confirm('Delete this news?')">Delete</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif($active_tab == 'applications'): ?>
                    <h3>Recent Applications</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr><th>Opportunity</th><th>Applicant</th><th>Email</th><th>Applied On</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['opportunity_title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['applicant_email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $app['status'] == 'pending' ? 'warning' : 
                                                ($app['status'] == 'accepted' ? 'success' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm" onclick="viewApplication(<?php echo $app['id']; ?>)">View Details</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif($active_tab == 'settings'): ?>
                    <h3>System Settings</h3>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="update_settings.php">
                                <div class="mb-3">
                                    <label>Site Name</label>
                                    <input type="text" name="site_name" class="form-control" value="Opportunity Hub">
                                </div>
                                <div class="mb-3">
                                    <label>Admin Email</label>
                                    <input type="email" name="admin_email" class="form-control" value="admin@opportunityhub.com">
                                </div>
                                <div class="mb-3">
                                    <label>Auto-archive days (opportunities)</label>
                                    <input type="number" name="archive_days" class="form-control" value="30">
                                </div>
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- News Modal -->
    <div class="modal fade" id="newsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add News</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="create_news.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Content</label>
                            <textarea name="content" id="newsEditor" rows="10"></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Expires After (days)</label>
                            <input type="number" name="expires_days" class="form-control" value="30">
                            <small>Leave empty for no expiry</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save News</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://kit.fontawesome.com/your-kit.js"></script>
    <script>
        if(document.getElementById('newsEditor')) {
            var quill = new Quill('#newsEditor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ 'header': 1 }, { 'header': 2 }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'color': [] }, { 'background': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });
            
            document.querySelector('#newsModal form').onsubmit = function() {
                document.querySelector('textarea[name="content"]').value = quill.root.innerHTML;
            };
        }
        
        function viewApplication(id) {
            alert('Application details feature coming soon! ID: ' + id);
        }
    </script>
</body>
</html>