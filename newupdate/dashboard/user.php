<?php
require_once __DIR__ . '/../config/database.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has correct role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

$error = '';
$success = '';

// Get opportunities
try {
    // Get all approved posts
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as company_name, u.company_name as official_company_name,
               (SELECT COUNT(*) FROM applications WHERE post_id = p.id AND user_id = ?) as has_applied
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'approved' 
          AND (p.deadline IS NULL OR p.deadline >= NOW())
        ORDER BY p.featured DESC, p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's applications
    $stmt = $pdo->prepare("
        SELECT a.*, p.title, p.category, p.id as post_id,
               COALESCE(u.company_name, u.name) as company_name 
        FROM applications a
        JOIN posts p ON a.post_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("User dashboard error: " . $e->getMessage());
    $error = 'Unable to load opportunities. Please try again later.';
    $posts = [];
    $applications = [];
}

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    
    if (!$post_id) {
        $error = 'Invalid post ID';
    } elseif (empty($cover_letter)) {
        $error = 'Please provide a cover letter';
    } else {
        try {
            // Check if already applied
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $user_id]);
            
            if ($stmt->fetch()) {
                $error = 'You have already applied for this opportunity';
            } else {
                // Handle file upload
                $resume_path = null;
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $file_type = $_FILES['resume']['type'];
                    
                    if (!in_array($file_type, $allowed_types)) {
                        $error = 'Only PDF and Word documents are allowed';
                    } elseif ($_FILES['resume']['size'] > 5 * 1024 * 1024) { // 5MB limit
                        $error = 'File size must be less than 5MB';
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/resumes/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                        $filename = 'resume_' . $user_id . '_' . time() . '.' . $file_ext;
                        $filepath = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['resume']['tmp_name'], $filepath)) {
                            $resume_path = 'uploads/resumes/' . $filename;
                        } else {
                            $error = 'Failed to upload resume';
                        }
                    }
                } else {
                    $error = 'Please upload your resume';
                }

                if (empty($error) && $resume_path) {
                    // Insert application
                    $stmt = $pdo->prepare("
                        INSERT INTO applications (post_id, user_id, cover_letter, resume_path, applied_at, status) 
                        VALUES (?, ?, ?, ?, NOW(), 'pending')
                    ");
                    
                    if ($stmt->execute([$post_id, $user_id, $cover_letter, $resume_path])) {
                        $success = 'Application submitted successfully!';
                        
                        // Refresh page after 2 seconds
                        header("refresh:2;url=user.php");
                    } else {
                        $error = 'Failed to submit application';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Application error: " . $e->getMessage());
            $error = 'Failed to submit application. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - New_update</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #f0f2f5;
            color: #1a1a1a;
        }
        
        .navbar {
            background: white;
            padding: 1rem 5%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0a66c2;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #0a66c2;
        }
        
        .nav-links a.active {
            color: #0a66c2;
            font-weight: 600;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #f0f2f5;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
        }
        
        .user-menu i {
            color: #0a66c2;
            font-size: 1.2rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .welcome-card h1 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            margin-bottom: 0.5rem;
        }
        
        .welcome-card p {
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.95rem;
        }
        
        .section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .section-header h2 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            color: #1a1a1a;
        }
        
        .section-header h2 i {
            color: #667eea;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .search-box i {
            color: #999;
        }
        
        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            min-width: 200px;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 30px;
            background: #f8f9fa;
            outline: none;
        }
        
        .opportunities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .opportunity-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s;
            background: white;
        }
        
        .opportunity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .opportunity-card.featured {
            border: 2px solid #ffc107;
            background: #fff9e6;
        }
        
        .opportunity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #e9ecef;
            color: #495057;
        }
        
        .category-badge.job { background: #0a66c2; color: white; }
        .category-badge.scholarship { background: #28a745; color: white; }
        .category-badge.internship { background: #ffc107; color: #1a1a1a; }
        .category-badge.news { background: #dc3545; color: white; }
        
        .featured-badge {
            background: #ffc107;
            color: #1a1a1a;
            padding: 0.25rem 0.5rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .opportunity-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .opportunity-title a {
            color: #1a1a1a;
            text-decoration: none;
        }
        
        .opportunity-title a:hover {
            color: #0a66c2;
        }
        
        .company-name {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .company-name i {
            color: #0a66c2;
        }
        
        .opportunity-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
            flex-wrap: wrap;
        }
        
        .opportunity-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .deadline {
            color: #dc3545;
            font-weight: 600;
        }
        
        .opportunity-excerpt {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: #0a66c2;
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #004182;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(10,102,194,0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #0a66c2;
            color: #0a66c2;
        }
        
        .btn-outline:hover {
            background: #0a66c2;
            color: white;
        }
        
        .btn-sm {
            padding: 0.4rem 1rem;
            font-size: 0.85rem;
        }
        
        .applications-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .applications-table th,
        .applications-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .applications-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .applications-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-reviewed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            font-size: 1.3rem;
            color: #1a1a1a;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: #dc3545;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group label i {
            color: #0a66c2;
            margin-right: 0.5rem;
        }
        
        .form-group input[type="file"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-group input[type="file"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0a66c2;
            box-shadow: 0 0 0 3px rgba(10,102,194,0.1);
        }
        
        .file-info {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .error, .success {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .error {
            background: #fee2e2;
            color: #dc3545;
            border: 1px solid #fecaca;
        }
        
        .success {
            background: #d4edda;
            color: #28a745;
            border: 1px solid #c3e6cb;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0a66c2;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
            grid-column: 1/-1;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                text-align: center;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .opportunities-grid {
                grid-template-columns: 1fr;
            }
            
            .applications-table {
                display: block;
                overflow-x: auto;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-bar {
                width: 100%;
            }
            
            .search-box {
                flex: 1;
            }
            
            .search-box input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="../index.php" class="logo">New_update</a>
        <div class="nav-links">
            <a href="../index.php"><i class="fas fa-home"></i> Home</a>
            <a href="user.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <div class="user-menu">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($user_name); ?></span>
            </div>
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="welcome-card">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p>Explore opportunities and advance your career. <?php echo count($posts); ?> opportunities available.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($posts); ?></div>
                <div class="stat-label">Available Opportunities</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($applications); ?></div>
                <div class="stat-label">Your Applications</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $pending_count = 0;
                    foreach ($applications as $app) {
                        if ($app['status'] === 'pending') $pending_count++;
                    }
                    echo $pending_count;
                    ?>
                </div>
                <div class="stat-label">Pending Reviews</div>
            </div>
        </div>
        
        <!-- Available Opportunities -->
        <div class="section">
            <div class="section-header">
                <h2>
                    <i class="fas fa-briefcase"></i>
                    Available Opportunities
                </h2>
                <div class="filter-bar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search opportunities..." onkeyup="filterOpportunities()">
                    </div>
                    <select class="filter-select" id="categoryFilter" onchange="filterOpportunities()">
                        <option value="all">All Categories</option>
                        <option value="job">Jobs</option>
                        <option value="scholarship">Scholarships</option>
                        <option value="internship">Internships</option>
                        <option value="news">News</option>
                    </select>
                </div>
            </div>
            
            <div class="opportunities-grid" id="opportunitiesGrid">
                <?php if (empty($posts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Opportunities Available</h3>
                        <p>Check back later for new opportunities</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="opportunity-card <?php echo $post['featured'] ? 'featured' : ''; ?>" 
                             data-category="<?php echo $post['category']; ?>"
                             data-title="<?php echo strtolower($post['title']); ?>"
                             data-company="<?php echo strtolower($post['company_name']); ?>">
                            <div class="opportunity-header">
                                <span class="category-badge <?php echo $post['category']; ?>">
                                    <?php echo ucfirst($post['category']); ?>
                                </span>
                                <?php if ($post['featured']): ?>
                                    <span class="featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="opportunity-title">
                                <a href="../view_post.php?id=<?php echo $post['id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                            </h3>
                            
                            <div class="company-name">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($post['company_name'] ?? $post['official_company_name'] ?? 'Company'); ?>
                            </div>
                            
                            <div class="opportunity-meta">
                                <span>
                                    <i class="far fa-calendar"></i>
                                    Posted: <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                </span>
                                <?php if (!empty($post['deadline'])): ?>
                                    <span class="deadline">
                                        <i class="far fa-hourglass"></i>
                                        Deadline: <?php echo date('M j, Y', strtotime($post['deadline'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="opportunity-excerpt">
                                <?php echo htmlspecialchars(substr(strip_tags($post['content']), 0, 120)) . '...'; ?>
                            </div>
                            
                            <?php if ($post['has_applied'] > 0): ?>
                                <button class="btn btn-primary" disabled>
                                    <i class="fas fa-check"></i> Already Applied
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="openApplyModal(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes($post['title'])); ?>')">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- My Applications -->
        <?php if (!empty($applications)): ?>
            <div class="section">
                <div class="section-header">
                    <h2>
                        <i class="fas fa-file-alt"></i>
                        My Applications
                    </h2>
                </div>
                
                <div class="table-responsive">
                    <table class="applications-table">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Company</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <a href="../view_post.php?id=<?php echo $app['post_id']; ?>" style="color: #0a66c2; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($app['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($app['applied_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $app['status']; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../view_post.php?id=<?php echo $app['post_id']; ?>" class="btn btn-outline btn-sm" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Application Modal -->
    <div class="modal" id="applyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-paper-plane" style="color: #0a66c2;"></i>
                    Apply for Position
                </h3>
                <button class="modal-close" onclick="closeApplyModal()">&times;</button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="applicationForm">
                <input type="hidden" name="apply" value="1">
                <input type="hidden" name="post_id" id="modalPostId">
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-briefcase"></i>
                        Position
                    </label>
                    <p style="padding: 0.5rem 0; font-weight: 600; color: #0a66c2;" id="modalPostTitle"></p>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-file-pdf"></i>
                        Resume/CV *
                    </label>
                    <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
                    <div class="file-info">
                        <i class="fas fa-info-circle"></i>
                        Accepted formats: PDF, DOC, DOCX (Max 5MB)
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <i class="fas fa-comment"></i>
                        Cover Letter *
                    </label>
                    <textarea name="cover_letter" id="cover_letter" rows="6" required 
                              placeholder="Tell us why you're interested in this position and why you'd be a good fit..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn btn-outline" onclick="closeApplyModal()" style="flex: 1;">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;" id="submitBtn">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openApplyModal(postId, postTitle) {
            document.getElementById('modalPostId').value = postId;
            document.getElementById('modalPostTitle').textContent = postTitle;
            document.getElementById('applyModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeApplyModal() {
            document.getElementById('applyModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('applicationForm').reset();
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('applyModal');
            if (event.target == modal) {
                closeApplyModal();
            }
        }
        
        // Filter opportunities
        function filterOpportunities() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const cards = document.querySelectorAll('.opportunity-card');
            
            cards.forEach(card => {
                const title = card.dataset.title || '';
                const company = card.dataset.company || '';
                const category = card.dataset.category;
                
                const matchesSearch = title.includes(searchInput) || company.includes(searchInput);
                const matchesCategory = categoryFilter === 'all' || category === categoryFilter;
                
                card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
            });
            
            // Show empty state if no cards visible
            const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
            const emptyState = document.querySelector('.empty-state');
            
            if (visibleCards.length === 0 && !emptyState) {
                const grid = document.getElementById('opportunitiesGrid');
                if (!document.getElementById('noResults')) {
                    const noResults = document.createElement('div');
                    noResults.id = 'noResults';
                    noResults.className = 'empty-state';
                    noResults.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>No Matching Opportunities</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    `;
                    grid.appendChild(noResults);
                }
            } else {
                const noResults = document.getElementById('noResults');
                if (noResults) noResults.remove();
            }
        }
        
        // Form submission handling
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('resume');
            const file = fileInput.files[0];
            const submitBtn = document.getElementById('submitBtn');
            
            // Validate file size (5MB limit)
            if (file && file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must be less than 5MB');
                return;
            }
            
            // Validate file type
            if (file) {
                const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Only PDF and Word documents are allowed');
                    return;
                }
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.error, .success');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // File input preview
        document.getElementById('resume')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const fileInfo = this.nextElementSibling;
                if (fileInfo) {
                    fileInfo.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> Selected: ${fileName}`;
                }
            }
        });
    </script>
</body>
</html>