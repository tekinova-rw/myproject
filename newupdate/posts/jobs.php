<!-- posts/jobs.php -->
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Initialize variables
$jobs = [];
$error = null;
$search_term = '';

// Sanitize and validate inputs
$sort = isset($_GET['sort']) ? htmlspecialchars(trim($_GET['sort'])) : 'newest';
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';
$page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
$per_page = 12;

// Validate page number
if ($page === false || $page < 1) {
    $page = 1;
}

try {
    // Base query with prepared statements
    $sql = "SELECT posts.*, users.name as author_name, users.email as author_email 
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            WHERE category = 'job'";
    
    $count_sql = "SELECT COUNT(*) as total FROM posts WHERE category = 'job'";
    $params = [];
    
    // Add search functionality safely
    if (!empty($search)) {
        $sql .= " AND (posts.title LIKE :search OR posts.content LIKE :search)";
        $count_sql .= " AND (title LIKE :search OR content LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Add sorting
    switch ($sort) {
        case 'oldest':
            $sql .= " ORDER BY posts.created_at ASC";
            break;
        case 'popular':
            $sql .= " ORDER BY posts.views DESC, posts.created_at DESC";
            break;
        default: // newest
            $sql .= " ORDER BY posts.created_at DESC";
    }
    
    // Get total count for pagination
    $count_stmt = $pdo->prepare($count_sql);
    if (!empty($search)) {
        $count_stmt->bindValue(':search', '%' . $search . '%');
    }
    $count_stmt->execute();
    $total_jobs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_jobs / $per_page);
    
    // Validate page number
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
    }
    
    // Add pagination
    $offset = ($page - 1) * $per_page;
    $sql .= " LIMIT :offset, :per_page";
    
    // Prepare and execute main query with pagination
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters safely
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error securely
    error_log("Jobs page database error: " . $e->getMessage());
    $error = "Unable to load jobs at this time. Please try again later.";
}

// CSRF token for any forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function for time elapsed
function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Find the latest job opportunities and career openings">
    <meta name="keywords" content="jobs, career, employment, opportunities">
    <meta name="author" content="Opportunities Site">
    
    <title>Job Opportunities | Opportunities Site</title>
    
    <!-- Font Awesome with integrity hash for security -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" 
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <style>
        /* CSS Variables for consistency */
        :root {
            --primary: #4361ee;
            --primary-dark: #3730a3;
            --primary-light: #4895ef;
            --secondary: #06b6d4;
            --accent: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius: 10px;
            --radius-lg: 15px;
            --transition: all 0.3s ease;
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea08 0%, #764ba208 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        /* Skip to main content for accessibility */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--primary);
            color: white;
            padding: 8px;
            z-index: 100;
            text-decoration: none;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .menu-toggle {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
            background: none;
            border: none;
            padding: 0.5rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:hover {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .nav-links a.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .nav-links a i {
            margin-right: 0.5rem;
        }

        .btn-login {
            background: var(--primary);
            color: white !important;
            padding: 0.5rem 1.5rem !important;
            border-radius: 50px;
            border: none !important;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            color: white !important;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .alert-info {
            background: #dbeafe;
            color: var(--primary);
            border: 1px solid #bfdbfe;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 3rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '💼';
            position: absolute;
            top: 50%;
            right: 2rem;
            transform: translateY(-50%);
            font-size: 8rem;
            opacity: 0.1;
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 1;
            max-width: 600px;
        }

        .header-content h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .header-content p {
            font-size: 1.1rem;
            opacity: 0.95;
            margin-bottom: 1.5rem;
        }

        .job-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            font-size: 0.95rem;
        }

        /* Search and Filter Section */
        .search-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .search-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-wrapper {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .filter-select {
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 2px solid var(--gray-light);
            border-radius: var(--radius);
            font-size: 1rem;
            background: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-search:active {
            transform: translateY(0);
        }

        /* Jobs Grid */
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .job-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        .job-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0;
            transition: var(--transition);
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .job-card:hover::before {
            opacity: 1;
        }

        .job-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .badge-new {
            background: var(--primary);
        }

        .badge-hot {
            background: var(--danger);
        }

        .badge-featured {
            background: var(--accent);
        }

        .job-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-right: 4rem;
        }

        .job-title a {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .job-title a:hover {
            color: var(--primary);
        }

        .job-meta {
            margin-bottom: 1rem;
            color: var(--gray);
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .meta-item i {
            width: 18px;
            color: var(--primary);
        }

        .job-excerpt {
            color: var(--gray);
            margin: 1rem 0;
            flex: 1;
            line-height: 1.7;
        }

        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-light);
        }

        .read-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .read-more:hover {
            gap: 0.75rem;
            color: var(--primary-dark);
        }

        .share-btn {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
            padding: 0.5rem;
        }

        .share-btn:hover {
            color: var(--primary);
            transform: scale(1.1);
        }

        /* Empty State */
        .no-jobs {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            grid-column: 1 / -1;
        }

        .no-jobs i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }

        .no-jobs h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .no-jobs p {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        .btn-clear {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .btn-clear:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 0.5rem;
            border-radius: var(--radius);
            background: white;
            color: var(--dark);
            text-decoration: none;
            border: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Loading State */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .jobs-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
            
            .header-content h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
            }

            .menu-toggle {
                display: block;
            }

            .nav-links {
                display: none;
                width: 100%;
                flex-direction: column;
                padding: 1rem 0;
                gap: 1rem;
                background: white;
                border-radius: var(--radius);
                margin-top: 1rem;
            }

            .nav-links.show {
                display: flex;
            }

            .nav-links a {
                width: 100%;
                text-align: center;
                padding: 0.75rem;
            }

            .page-header {
                padding: 2rem;
            }

            .page-header::before {
                font-size: 6rem;
                right: 1rem;
            }

            .header-content h2 {
                font-size: 1.75rem;
            }

            .job-stats {
                flex-direction: column;
                gap: 0.5rem;
            }

            .search-form {
                flex-direction: column;
            }

            .search-wrapper,
            .filter-select,
            .btn-search {
                width: 100%;
            }

            .container {
                padding: 0 1rem;
            }

            .jobs-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .job-card {
                padding: 1rem;
            }

            .job-title {
                font-size: 1.1rem;
                padding-right: 3rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header::before {
                font-size: 4rem;
            }

            .header-content h2 {
                font-size: 1.5rem;
            }

            .stat-item {
                font-size: 0.85rem;
            }

            .pagination {
                gap: 0.25rem;
            }

            .pagination a,
            .pagination span {
                min-width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        /* Print Styles */
        @media print {
            .navbar,
            .search-section,
            .share-btn,
            .pagination {
                display: none;
            }

            .job-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="navbar-brand">
            <i class="fas fa-briefcase" aria-hidden="true"></i>
            <span>Opportunities Hub</span>
        </div>
        
        <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle menu" aria-expanded="false">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        
        <div class="nav-links" id="navLinks">
            <a href="../index.php"><i class="fas fa-home" aria-hidden="true"></i> Home</a>
            <a href="jobs.php" class="active" aria-current="page"><i class="fas fa-briefcase" aria-hidden="true"></i> Jobs</a>
            <a href="internships.php"><i class="fas fa-graduation-cap" aria-hidden="true"></i> Internships</a>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="../dashboard/<?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') ? 'admin.php' : 'user.php'; ?>">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn-login">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout
                </a>
            <?php else: ?>
                <a href="../login.php"><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login</a>
                <a href="../register.php" class="btn-login">
                    <i class="fas fa-user-plus" aria-hidden="true"></i> Register
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <main id="main-content" class="container">
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($search) && empty($jobs)): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                No jobs found matching "<?php echo htmlspecialchars($search); ?>"
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <h2><i class="fas fa-briefcase" aria-hidden="true"></i> Job Opportunities</h2>
                <p>Find your dream job from our curated list of opportunities</p>
                
                <div class="job-stats">
                    <span class="stat-item">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        <span><?php echo number_format($total_jobs); ?> Active Jobs</span>
                    </span>
                    <span class="stat-item">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        <span>Updated Daily</span>
                    </span>
                    <span class="stat-item">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        <span>Multiple Companies</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="search-section">
            <form class="search-form" method="GET" action="" role="search">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon" aria-hidden="true"></i>
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by title or description..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           aria-label="Search jobs">
                </div>
                
                <select name="sort" class="filter-select" aria-label="Sort jobs by">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                </select>
                
                <button type="submit" class="btn-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span>Search Jobs</span>
                </button>
                
                <?php if (!empty($search) || $sort !== 'newest'): ?>
                    <a href="jobs.php" class="btn-search" style="background: var(--gray);">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span>Clear Filters</span>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="jobs-grid">
            <?php if (!empty($jobs)): ?>
                <?php foreach ($jobs as $job): ?>
                    <article class="job-card">
                        <?php
                        $hours_old = (time() - strtotime($job['created_at'])) / 3600;
                        if ($hours_old < 24):
                        ?>
                            <span class="job-badge badge-new">
                                <i class="fas fa-star" aria-hidden="true"></i> New
                            </span>
                        <?php elseif ($job['views'] > 100): ?>
                            <span class="job-badge badge-hot">
                                <i class="fas fa-fire" aria-hidden="true"></i> Hot
                            </span>
                        <?php elseif ($job['views'] > 50): ?>
                            <span class="job-badge badge-featured">
                                <i class="fas fa-crown" aria-hidden="true"></i> Featured
                            </span>
                        <?php endif; ?>
                        
                        <h3 class="job-title">
                            <a href="../view_post.php?id=<?php echo (int)$job['id']; ?>">
                                <?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </h3>
                        
                        <div class="job-meta">
                            <div class="meta-item">
                                <i class="fas fa-user-tie" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($job['author_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="far fa-calendar-alt" aria-hidden="true"></i>
                                <span><?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="far fa-clock" aria-hidden="true"></i>
                                <span><?php echo time_elapsed_string($job['created_at']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="far fa-eye" aria-hidden="true"></i>
                                <span><?php echo number_format($job['views']); ?> views</span>
                            </div>
                        </div>
                        
                        <div class="job-excerpt">
                            <?php 
                            $excerpt = strip_tags($job['content']);
                            $excerpt = mb_substr($excerpt, 0, 150, 'UTF-8');
                            echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8');
                            ?>...
                        </div>
                        
                        <footer class="job-footer">
                            <a href="../view_post.php?id=<?php echo (int)$job['id']; ?>" class="read-more">
                                <span>Read More</span>
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                            
                            <button class="share-btn" onclick="shareJob(<?php echo (int)$job['id']; ?>)" 
                                    aria-label="Share this job" title="Share">
                                <i class="fas fa-share-alt" aria-hidden="true"></i>
                            </button>
                        </footer>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-jobs">
                    <i class="fas fa-briefcase" aria-hidden="true"></i>
                    <h3>No Jobs Available</h3>
                    <p>Check back later for new opportunities or try adjusting your search.</p>
                    <?php if (!empty($search) || $sort !== 'newest'): ?>
                        <a href="jobs.php" class="btn-clear">
                            <i class="fas fa-times" aria-hidden="true"></i>
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination" role="navigation" aria-label="Pagination">
                <!-- Previous page -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>" 
                       aria-label="Go to previous page">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled" aria-disabled="true">
                        <i class="fas fa-chevron-left" aria-hidden="true"></i>
                    </span>
                <?php endif; ?>

                <!-- Page numbers -->
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1): ?>
                    <a href="?page=1&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>">1</a>
                    <?php if ($start > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active" aria-current="page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $total_pages; ?>
                    </a>
                <?php endif; ?>

                <!-- Next page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo urlencode($sort); ?>&search=<?php echo urlencode($search); ?>"
                       aria-label="Go to next page">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled" aria-disabled="true">
                        <i class="fas fa-chevron-right" aria-hidden="true"></i>
                    </span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </main>

    <script>
        // Mobile menu toggle
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            
            navLinks.classList.toggle('show');
            menuToggle.setAttribute('aria-expanded', !isExpanded);
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const navLinks = document.getElementById('navLinks');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
                navLinks.classList.remove('show');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Share job function with fallback
        function shareJob(jobId) {
            const url = window.location.origin + '/view_post.php?id=' + jobId;
            const title = 'Check out this job opportunity';
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(console.error);
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copied to clipboard!');
                }).catch(() => {
                    prompt('Copy this link:', url);
                });
            }
        }

        // Debounced search
        let searchTimeout;
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        }

        // Smooth scroll to top when clicking pagination
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('disabled')) {
                    e.preventDefault();
                    const url = this.href;
                    
                    // Scroll to top smoothly
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                    
                    // Navigate after scroll
                    setTimeout(() => {
                        window.location.href = url;
                    }, 300);
                }
            });
        });

        // Add loading state to search button
        const searchForm = document.querySelector('.search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                const btn = this.querySelector('.btn-search');
                if (btn) {
                    btn.innerHTML = '<span class="loading"></span> Searching...';
                    btn.disabled = true;
                }
            });
        }
    </script>
</body>
</html>