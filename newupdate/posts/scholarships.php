<!-- post/scholarships.php -->
<?php
require_once '../config/database.php';

// First, check if external_link column exists, if not, add it
try {
    $pdo->query("SELECT external_link FROM posts LIMIT 1");
} catch (PDOException $e) {
    // Add external_link column if it doesn't exist
    $pdo->exec("ALTER TABLE posts ADD COLUMN external_link VARCHAR(500) NULL AFTER deadline");
}

// Fetch all scholarships
$stmt = $pdo->prepare("
    SELECT posts.*, users.name as author_name, users.email as author_email 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    WHERE category = 'scholarship' 
    ORDER BY 
        CASE 
            WHEN deadline >= CURDATE() THEN 0 
            ELSE 1 
        END,
        deadline ASC,
        created_at DESC
");
$stmt->execute();
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_scholarships = count($scholarships);
$active_scholarships = 0;
$expiring_soon = 0;
$external_scholarships = 0;

foreach($scholarships as $scholarship) {
    if(!empty($scholarship['deadline'])) {
        $deadline = strtotime($scholarship['deadline']);
        $now = time();
        $days_remaining = ceil(($deadline - $now) / (60 * 60 * 24));
        
        if($deadline > $now) {
            $active_scholarships++;
            if($days_remaining <= 7) {
                $expiring_soon++;
            }
        }
    }
    
    // Check if it has external link
    if(!empty($scholarship['external_link'])) {
        $external_scholarships++;
    }
}

// Helper function to check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Scholarships - New_update</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.5;
            overflow-x: hidden;
            width: 100%;
        }
        
        /* Navigation - Responsive */
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
            width: 100%;
        }
        
        .logo {
            font-size: clamp(1.5rem, 4vw, 1.8rem);
            font-weight: 700;
            color: #2c3e50;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: clamp(0.5rem, 2vw, 2rem);
            align-items: center;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            font-size: clamp(0.8rem, 2vw, 1rem);
            transition: color 0.3s;
            white-space: nowrap;
        }
        
        .nav-links a:hover {
            color: #3498db;
        }
        
        .nav-links a.active {
            color: #3498db;
            font-weight: 600;
        }
        
        .login-btn {
            background: #3498db;
            color: white !important;
            padding: 0.5rem clamp(0.8rem, 2vw, 1.5rem);
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .login-btn:hover {
            background: #2980b9;
        }
        
        /* Page Header - Responsive */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: clamp(2rem, 5vw, 3rem) clamp(1rem, 3vw, 2rem);
            text-align: center;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .page-header h1 {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .page-header p {
            font-size: clamp(1rem, 2.5vw, 1.2rem);
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Stats Cards - Responsive Grid */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
            gap: clamp(0.8rem, 2vw, 1.5rem);
            max-width: 1200px;
            margin: -3rem auto 2rem;
            padding: 0 clamp(0.8rem, 2vw, 1rem);
            position: relative;
            z-index: 10;
            width: 100%;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: clamp(1rem, 3vw, 1.5rem);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-number {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: clamp(0.8rem, 2vw, 1rem);
        }
        
        /* Search and Filter Bar - Responsive */
        .search-filter-bar {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 clamp(0.8rem, 2vw, 1rem);
            width: 100%;
        }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            width: 100%;
        }
        
        .search-box i {
            color: #667eea;
            font-size: clamp(1rem, 2vw, 1.2rem);
        }
        
        .search-box input {
            flex: 1;
            border: none;
            padding: 0.8rem 0;
            font-size: clamp(0.9rem, 2vw, 1rem);
            outline: none;
            background: transparent;
            width: 100%;
            min-width: 0; /* Prevents input from overflowing */
        }
        
        .filter-bar {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            width: 100%;
        }
        
        .filter-btn {
            background: white;
            color: #2c3e50;
            padding: 0.6rem clamp(0.8rem, 2vw, 1.5rem);
            border-radius: 30px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            font-weight: 600;
            font-size: clamp(0.8rem, 2vw, 0.95rem);
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        @media (max-width: 480px) {
            .filter-btn {
                white-space: normal;
                font-size: 0.8rem;
                padding: 0.5rem 0.8rem;
            }
            
            .filter-bar {
                gap: 0.3rem;
            }
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .filter-btn.external {
            border-color: #28a745;
            color: #28a745;
        }
        
        .filter-btn.external.active {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .filter-btn.external:hover {
            border-color: #28a745;
            color: #28a745;
        }
        
        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 clamp(0.8rem, 2vw, 1rem);
            width: 100%;
        }
        
        /* Scholarships Grid */
        .scholarships-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            width: 100%;
        }
        
        /* Scholarship Card - Fully Responsive */
        .scholarship-card {
            background: white;
            border-radius: 15px;
            padding: clamp(1rem, 3vw, 1.5rem);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid #eef2f6;
            display: flex;
            gap: clamp(1rem, 3vw, 1.5rem);
            align-items: flex-start;
            position: relative;
            width: 100%;
        }
        
        @media (max-width: 640px) {
            .scholarship-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
        
        .scholarship-card:hover {
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
            transform: translateY(-3px);
            border-color: #667eea;
        }
        
        .scholarship-card.external:hover {
            border-color: #28a745;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.15);
        }
        
        /* External Link Badge */
        .external-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #28a745;
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            z-index: 2;
        }
        
        @media (max-width: 640px) {
            .external-badge {
                position: static;
                margin-bottom: 0.5rem;
                display: inline-flex;
            }
        }
        
        .external-badge i {
            font-size: 0.8rem;
        }
        
        /* Scholarship Image - Responsive */
        .scholarship-image {
            flex-shrink: 0;
            width: clamp(60px, 15vw, 100px);
            height: clamp(60px, 15vw, 100px);
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
            overflow: hidden;
        }
        
        .scholarship-card.external .scholarship-image {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .scholarship-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        
        .scholarship-content {
            flex: 1;
            min-width: 0; /* Prevents text overflow */
            width: 100%;
        }
        
        .scholarship-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.8rem;
            width: 100%;
        }
        
        @media (max-width: 640px) {
            .scholarship-header {
                flex-direction: column;
                align-items: center;
                padding-right: 0;
            }
        }
        
        .scholarship-header h3 {
            color: #2c3e50;
            font-size: clamp(1.1rem, 3vw, 1.3rem);
            font-weight: 600;
            line-height: 1.4;
            word-break: break-word;
        }
        
        .scholarship-type {
            background: #764ba2;
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .scholarship-meta {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(0.8rem, 2vw, 1.5rem);
            color: #7f8c8d;
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            margin-bottom: 0.8rem;
        }
        
        @media (max-width: 640px) {
            .scholarship-meta {
                justify-content: center;
            }
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .meta-item i {
            color: #667eea;
        }
        
        .scholarship-card.external .meta-item i {
            color: #28a745;
        }
        
        .scholarship-description {
            color: #555;
            font-size: clamp(0.9rem, 2vw, 0.95rem);
            margin-bottom: 1rem;
            line-height: 1.6;
            word-break: break-word;
        }
        
        /* Deadline Styling */
        .deadline {
            background: #fff3e0;
            color: #e67e22;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: clamp(0.85rem, 2vw, 0.9rem);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 640px) {
            .deadline {
                width: 100%;
                justify-content: center;
            }
        }
        
        .deadline.urgent {
            background: #fee2e2;
            color: #dc2626;
            animation: pulse 2s infinite;
        }
        
        .deadline.expired {
            background: #f3f4f6;
            color: #9ca3af;
        }
        
        @keyframes pulse {
            0%,100% { opacity: 1; }
            50% { opacity: 0.8; transform: scale(1.02); }
        }
        
        .scholarship-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }
        
        @media (max-width: 640px) {
            .scholarship-footer {
                flex-direction: column;
                align-items: center;
            }
        }
        
        .views {
            color: #95a5a6;
            font-size: clamp(0.85rem, 2vw, 0.9rem);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        
        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
        }
        
        .view-btn {
            background: #667eea;
            color: white;
            padding: 0.6rem clamp(1rem, 3vw, 1.5rem);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: clamp(0.9rem, 2vw, 0.95rem);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        
        @media (max-width: 480px) {
            .view-btn {
                width: 100%;
                justify-content: center;
                white-space: normal;
            }
        }
        
        .view-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .view-btn.external {
            background: #28a745;
        }
        
        .view-btn.external:hover {
            background: #218838;
        }
        
        .apply-btn {
            background: #28a745;
            color: white;
            padding: 0.6rem clamp(1rem, 3vw, 1.5rem);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: clamp(0.9rem, 2vw, 0.95rem);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        
        @media (max-width: 480px) {
            .apply-btn {
                width: 100%;
                justify-content: center;
                white-space: normal;
            }
        }
        
        .apply-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 15px;
            padding: clamp(2rem, 5vw, 4rem) clamp(1rem, 3vw, 2rem);
            text-align: center;
            color: #7f8c8d;
            border: 2px dashed #e0e0e0;
            width: 100%;
        }
        
        .empty-state i {
            font-size: clamp(3rem, 8vw, 4rem);
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            margin-bottom: 1.5rem;
            word-break: break-word;
        }
        
        .post-btn {
            background: #667eea;
            color: white;
            padding: 0.8rem clamp(1.5rem, 4vw, 2rem);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .post-btn:hover {
            background: #764ba2;
        }
        
        /* Footer - Responsive */
        .footer {
            background: white;
            border-top: 1px solid #eef2f6;
            padding: 2rem clamp(1rem, 3vw, 5%);
            margin-top: 4rem;
            text-align: center;
            color: #7f8c8d;
            width: 100%;
        }
        
        .footer p {
            font-size: clamp(0.85rem, 2vw, 1rem);
        }
        
        /* Mobile Menu Improvements */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .nav-links {
                justify-content: center;
                width: 100%;
            }
            
            .nav-links a {
                white-space: normal;
                text-align: center;
            }
            
            .stats-container {
                margin-top: -2rem;
            }
            
            .filter-bar {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.5rem;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            
            .filter-bar::-webkit-scrollbar {
                height: 4px;
            }
            
            .filter-bar::-webkit-scrollbar-thumb {
                background: #667eea;
                border-radius: 4px;
            }
        }
        
        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .filter-btn,
            .view-btn,
            .apply-btn,
            .post-btn {
                min-height: 44px; /* Minimum touch target size */
            }
            
            .nav-links a {
                padding: 8px 12px; /* Larger touch area */
            }
        }
        
        /* Print styles */
        @media print {
            .navbar,
            .filter-bar,
            .search-box,
            .action-buttons,
            .footer {
                display: none;
            }
            
            .scholarship-card {
                break-inside: avoid;
                border: 1px solid #ddd;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .scholarship-card {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        /* Utility classes for responsive images */
        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Fix for very small devices */
        @media (max-width: 320px) {
            .scholarship-meta {
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
            }
            
            .meta-item {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Rest of your HTML remains exactly the same -->
    <nav class="navbar">
        <a href="../index.php" class="logo">new_update</a>
        <div class="nav-links">
            <a href="../index.php">HOME</a>
            <a href="jobs.php">JOBS</a>
            <a href="scholarships.php" class="active">SCHOLARSHIPS</a>
            <a href="internships.php">INTERNSHIPS</a>
            <a href="news.php">NEWS</a>
            <?php if(isLoggedIn()): ?>
                <a href="../dashboard/<?php echo isAdmin() ? 'admin.php' : 'user.php'; ?>">DASHBOARD</a>
                <a href="../logout.php">LOGOUT</a>
            <?php else: ?>
                <a href="../login.php" class="login-btn">LOGIN</a>
                <a href="../register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="page-header">
        <h1>🎓 Scholarship Opportunities</h1>
        <p>Discover fully funded scholarships, grants, and financial aid opportunities for Rwandan students</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-number"><?php echo $total_scholarships; ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-number"><?php echo $active_scholarships; ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div class="stat-number"><?php echo $expiring_soon; ?></div>
            <div class="stat-label">Urgent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏛️</div>
            <div class="stat-number"><?php echo count(array_unique(array_column($scholarships, 'author_name'))); ?></div>
            <div class="stat-label">Institutions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🔗</div>
            <div class="stat-number"><?php echo $external_scholarships; ?></div>
            <div class="stat-label">External</div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search scholarships by title, institution, or description...">
        </div>
        
        <div class="filter-bar">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="active">Active</button>
            <button class="filter-btn" data-filter="urgent">Urgent</button>
            <button class="filter-btn" data-filter="masters">Master's</button>
            <button class="filter-btn" data-filter="phd">PhD</button>
            <button class="filter-btn" data-filter="undergraduate">Undergraduate</button>
            <button class="filter-btn external" data-filter="external">External Links</button>
        </div>
    </div>

    <div class="container">
        <div class="scholarships-grid" id="scholarshipsGrid">
            <?php if(empty($scholarships)): ?>
                <div class="empty-state">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No Scholarships Available</h3>
                    <p>There are currently no scholarship opportunities posted.</p>
                    <?php if(isLoggedIn()): ?>
                        <a href="../dashboard/create_post.php" class="post-btn">Post a Scholarship</a>
                    <?php else: ?>
                        <p>Login to post scholarship opportunities</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach($scholarships as $index => $scholarship): 
                    // Calculate deadline status
                    $deadline_status = '';
                    $days_remaining = '';
                    $has_external = !empty($scholarship['external_link']);
                    
                    if(!empty($scholarship['deadline'])) {
                        $deadline = strtotime($scholarship['deadline']);
                        $now = time();
                        $diff = $deadline - $now;
                        $days_remaining = ceil($diff / (60 * 60 * 24));
                        
                        if($diff < 0) {
                            $deadline_status = 'expired';
                        } elseif($days_remaining <= 7) {
                            $deadline_status = 'urgent';
                        }
                    }
                ?>
                    <div class="scholarship-card <?php echo $has_external ? 'external' : ''; ?>" 
                         data-deadline="<?php echo $scholarship['deadline'] ?? ''; ?>"
                         data-external="<?php echo $has_external ? 'yes' : 'no'; ?>"
                         data-title="<?php echo strtolower(htmlspecialchars($scholarship['title'])); ?>"
                         data-institution="<?php echo strtolower(htmlspecialchars($scholarship['author_name'])); ?>"
                         data-description="<?php echo strtolower(htmlspecialchars(strip_tags($scholarship['content']))); ?>"
                         style="animation-delay: <?php echo $index * 0.1; ?>s">
                        
                        <!-- External Link Badge -->
                        <?php if($has_external): ?>
                            <span class="external-badge">
                                <i class="fas fa-external-link-alt"></i> External
                            </span>
                        <?php endif; ?>
                        
                        <!-- Left side image -->
                        <div class="scholarship-image">
                            <?php if(!empty($scholarship['image'])): 
                                $ext = strtolower(pathinfo($scholarship['image'], PATHINFO_EXTENSION));
                                if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($scholarship['image']); ?>" alt="<?php echo htmlspecialchars($scholarship['title']); ?>">
                                <?php else: ?>
                                    🎓
                                <?php endif; ?>
                            <?php else: ?>
                                🎓
                            <?php endif; ?>
                        </div>
                        
                        <!-- Right side content -->
                        <div class="scholarship-content">
                            <div class="scholarship-header">
                                <h3><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                                <span class="scholarship-type">Scholarship</span>
                            </div>
                            
                            <div class="scholarship-meta">
                                <span class="meta-item">
                                    <i class="fas fa-university"></i> <?php echo htmlspecialchars($scholarship['author_name']); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($scholarship['created_at'])); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-eye"></i> <?php echo $scholarship['views']; ?>
                                </span>
                            </div>
                            
                            <p class="scholarship-description">
                                <?php echo htmlspecialchars(substr(strip_tags($scholarship['content']), 0, 150)) . '...'; ?>
                            </p>
                            
                            <?php if(!empty($scholarship['deadline'])): ?>
                                <div class="deadline <?php echo $deadline_status; ?>">
                                    <i class="fas fa-hourglass-half"></i>
                                    Deadline: <?php echo date('M d, Y', strtotime($scholarship['deadline'])); ?>
                                    <?php if($deadline_status == 'urgent'): ?>
                                        <span>⚠️ <?php echo $days_remaining; ?>d</span>
                                    <?php elseif($deadline_status == 'expired'): ?>
                                        <span>❌ Expired</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="scholarship-footer">
                                <span class="views">
                                    <i class="fas fa-eye"></i> <?php echo $scholarship['views']; ?> views
                                </span>
                                <div class="action-buttons">
                                    <?php if($has_external): ?>
                                        <!-- External Link - Direct Apply -->
                                        <a href="<?php echo htmlspecialchars($scholarship['external_link']); ?>" 
                                           target="_blank" 
                                           class="view-btn external"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-external-link-alt"></i> Apply Now
                                        </a>
                                    <?php else: ?>
                                        <!-- Regular Post - View Details -->
                                        <a href="../view_post.php?id=<?php echo $scholarship['id']; ?>" 
                                           class="view-btn"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <!-- Quick Email Apply -->
                                        <a href="mailto:<?php echo htmlspecialchars($scholarship['author_email']); ?>?subject=Application for <?php echo urlencode($scholarship['title']); ?>" 
                                           class="apply-btn"
                                           onclick="event.stopPropagation();">
                                            <i class="fas fa-envelope"></i> Email
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> New_update. All rights reserved. | Find Scholarships in Rwanda</p>
    </footer>

    <!-- JavaScript remains exactly the same -->
    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const scholarshipCards = document.querySelectorAll('.scholarship-card');
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            scholarshipCards.forEach(card => {
                const title = card.dataset.title || '';
                const institution = card.dataset.institution || '';
                const description = card.dataset.description || '';
                
                const matches = title.includes(searchTerm) || 
                               institution.includes(searchTerm) || 
                               description.includes(searchTerm);
                
                card.style.display = matches ? 'flex' : 'none';
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const today = new Date();
                
                scholarshipCards.forEach(card => {
                    const deadlineStr = card.dataset.deadline;
                    const isExternal = card.dataset.external === 'yes';
                    const title = card.querySelector('h3').textContent.toLowerCase();
                    let showCard = true;
                    
                    if(filter === 'active') {
                        if(deadlineStr) {
                            const deadline = new Date(deadlineStr);
                            showCard = deadline >= today;
                        }
                    } else if(filter === 'urgent') {
                        if(deadlineStr) {
                            const deadline = new Date(deadlineStr);
                            const diffTime = deadline - today;
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            showCard = diffDays <= 7 && diffDays > 0;
                        } else {
                            showCard = false;
                        }
                    } else if(filter === 'masters') {
                        showCard = title.includes('master');
                    } else if(filter === 'phd') {
                        showCard = title.includes('phd') || title.includes('doctoral');
                    } else if(filter === 'undergraduate') {
                        showCard = title.includes('bachelor') || title.includes('undergraduate');
                    } else if(filter === 'external') {
                        showCard = isExternal;
                    }
                    
                    card.style.display = showCard ? 'flex' : 'none';
                });
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        scholarshipCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
    </script>
</body>
</html>