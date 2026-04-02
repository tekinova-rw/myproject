<?php
// post/internships.php
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch all internships
$stmt = $pdo->prepare("
    SELECT posts.*, users.name AS author_name, users.email AS author_email 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    WHERE category = 'internship' 
    ORDER BY 
        CASE 
            WHEN deadline >= CURDATE() THEN 0 
            ELSE 1 
        END,
        deadline ASC,
        created_at DESC
");
$stmt->execute();
$internships = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
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

// Time remaining (kept but currently unused in display)
function getTimeRemaining($deadline) {
    if (empty($deadline)) return null;
    
    $deadline_ts = strtotime($deadline);
    $now = time();
    $diff = $deadline_ts - $now;
    
    if ($diff < 0) {
        return ['expired' => true, 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0];
    }
    
    return [
        'expired' => false,
        'days'    => floor($diff / 86400),
        'hours'   => floor(($diff % 86400) / 3600),
        'minutes' => floor(($diff % 3600) / 60),
        'seconds' => $diff % 60
    ];
}

// Safe truncate + escape
function truncateText($text, $length = 160) {
    if (empty($text)) return '';
    $text = strip_tags($text);
    if (mb_strlen($text) <= $length) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars(mb_substr($text, 0, $length), ENT_QUOTES, 'UTF-8') . '...';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Internships - New_update</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
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
            color: #0d6efd;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav-links a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #0d6efd;
        }

        .login-btn {
            background: #0d6efd;
            color: white !important;
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
        }

        .login-btn:hover {
            background: #0b5ed7;
        }

        .page-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0099ff 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-filter-bar {
            max-width: 1200px;
            margin: 1.5rem auto;
            padding: 0 1rem;
        }

        .search-box {
            background: white;
            border-radius: 8px;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .search-box i {
            color: #0d6efd;
        }

        .search-box input {
            flex: 1;
            border: none;
            padding: 0.5rem 0;
            font-size: 1rem;
            outline: none;
        }

        .filter-bar {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: white;
            padding: 0.6rem 1.5rem;
            border-radius: 5px;
            border: 1px solid #ddd;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .internships-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .internship-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: all 0.25s ease;
        }

        .internship-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .internship-image {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0d6efd, #0099ff);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.2rem;
        }

        .internship-content {
            flex: 1;
        }

        .internship-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.6rem;
        }

        .internship-header h3 {
            color: #212529;
            font-size: 1.32rem;
            font-weight: 600;
            margin: 0;
        }

        .internship-badge {
            background: #0d6efd;
            color: white;
            padding: 0.25rem 0.85rem;
            border-radius: 4px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .internship-badge.paid {
            background: #28a745;
        }

        .internship-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.4rem;
            color: #6c757d;
            font-size: 0.87rem;
            margin-bottom: 0.8rem;
        }

        .meta-item i {
            color: #0d6efd;
            margin-right: 0.35rem;
            width: 14px;
            text-align: center;
        }

        .internship-description {
            color: #555;
            font-size: 0.94rem;
            margin-bottom: 0.9rem;
            line-height: 1.55;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .internship-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-bottom: 0.9rem;
        }

        .tag {
            background: #f8f9fa;
            color: #0d6efd;
            padding: 0.25rem 0.7rem;
            border-radius: 4px;
            font-size: 0.74rem;
            font-weight: 500;
        }

        .tag.paid {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .tag.remote {
            background: #e3f2fd;
            color: #1976d2;
        }

        .deadline {
            background: #fff3e0;
            color: #e67e22;
            padding: 0.35rem 0.9rem;
            border-radius: 6px;
            font-size: 0.84rem;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin-top: 0.6rem;
            flex-wrap: wrap;
        }

        .deadline.urgent {
            background: #fee2e2;
            color: #dc2626;
        }

        .readmore-btn {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 0.55rem 1.3rem;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            transition: all 0.25s;
            margin-top: 0.6rem;
        }

        .readmore-btn:hover {
            background: #0b5ed7;
            transform: translateY(-1px);
        }

        .empty-state {
            background: white;
            border-radius: 8px;
            padding: 4.5rem 2rem;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4.5rem;
            color: #0d6efd;
            margin-bottom: 1.2rem;
        }

        .empty-state h3 {
            color: #212529;
            margin-bottom: 1rem;
        }

        .footer {
            background: white;
            border-top: 1px solid #e9ecef;
            padding: 3.5rem 5%;
            margin-top: 5rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.5rem;
        }

        .footer-section h3 {
            color: #212529;
            font-size: 1.15rem;
            margin-bottom: 1.1rem;
        }

        .footer-section p, .footer-section a {
            color: #6c757d;
            text-decoration: none;
            transition: color 0.3s;
            margin-bottom: 0.6rem;
            display: block;
        }

        .footer-section a:hover {
            color: #0d6efd;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2.5rem;
            margin-top: 2.5rem;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 0.92rem;
        }

        @media (max-width: 768px) {
            .navbar { flex-direction: column; text-align: center; }
            .nav-links { justify-content: center; gap: 1.2rem; }
            .page-header h1 { font-size: 1.9rem; }
            .page-header p { font-size: 1.05rem; }
            .internship-card { flex-direction: column; text-align: center; }
            .internship-image { margin: 0 auto; }
            .internship-header { flex-direction: column; align-items: center; }
            .internship-meta { justify-content: center; }
            .internship-tags { justify-content: center; }
            .deadline { justify-content: center; width: 100%; }
            .filter-bar { gap: 0.6rem; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }

        @media (max-width: 480px) {
            .page-header { padding: 2.2rem 1rem; }
            .page-header h1 { font-size: 1.6rem; }
            .internship-card { padding: 1.2rem; }
            .internship-header h3 { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <a href="../index.php" class="logo">new_update</a>
        <div class="nav-links">
            <a href="../index.php">HOME</a>
            <a href="jobs.php">JOBS</a>
            <a href="scholarships.php">SCHOLARSHIPS</a>
            <a href="internships.php" class="active">INTERNSHIPS</a>
            <a href="news.php">NEWS</a>
            <?php if (isLoggedIn()): ?>
                <a href="../dashboard/<?= isAdmin() ? 'admin.php' : 'user.php' ?>">DASHBOARD</a>
                <a href="../logout.php">LOGOUT</a>
            <?php else: ?>
                <a href="../login.php" class="login-btn">LOGIN</a>
                <a href="../register.php">REGISTER</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="page-header">
        <h1>💼 Internship Opportunities</h1>
        <p>Kickstart your career with valuable internship experience at top companies in Rwanda</p>
    </div>

    <div class="search-filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search internships by title, company, or description..." autocomplete="off">
        </div>
        <div class="filter-bar">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="active">Active</button>
            <button class="filter-btn" data-filter="paid">Paid</button>
            <button class="filter-btn" data-filter="remote">Remote</button>
        </div>
    </div>

    <div class="container">
        <div class="internships-grid" id="internshipsGrid">
            <?php if (empty($internships)): ?>
                <div class="empty-state">
                    <i class="fas fa-laptop-code"></i>
                    <h3>No Internships Available</h3>
                    <p>There are currently no internship opportunities posted.</p>
                    <?php if (isLoggedIn()): ?>
                        <a href="../dashboard/create_post.php" class="readmore-btn" style="margin-top:1.2rem; text-decoration:none;">
                            <i class="fas fa-plus"></i> Post an Internship
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($internships as $internship):
                    $content    = $internship['content'] ?? '';
                    $title      = $internship['title'] ?? '(No title)';
                    $post_id    = (int)($internship['id'] ?? 0);
                    $author     = $internship['author_name'] ?? 'Unknown';
                    $views      = (int)($internship['views'] ?? 0);
                    $created_at = $internship['created_at'] ?? null;
                    $deadline   = $internship['deadline'] ?? null;

                    $content_lower = strtolower($content);
                    $title_lower   = strtolower($title);
                    $is_paid   = strpos($content_lower, 'paid') !== false || strpos($content_lower, 'stipend') !== false || strpos($title_lower, 'paid') !== false;
                    $is_remote = strpos($content_lower, 'remote') !== false || strpos($content_lower, 'work from home') !== false || strpos($title_lower, 'remote') !== false;

                    $time_remaining = getTimeRemaining($deadline);
                    $deadline_status = '';
                    if ($time_remaining && !$time_remaining['expired'] && $time_remaining['days'] < 1) {
                        $deadline_status = 'urgent';
                    }

                    $short_desc    = truncateText($content, 160);
                    $created_date  = $created_at ? date('M d, Y', strtotime($created_at)) : 'Date not set';
                    $deadline_date = $deadline   ? date('M d, Y', strtotime($deadline))   : '';
                    $deadline_iso  = $deadline   ? date('Y-m-d', strtotime($deadline))    : '';
                ?>
                    <div class="internship-card" data-deadline-iso="<?= htmlspecialchars($deadline_iso) ?>">
                        <div class="internship-image">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="internship-content">
                            <div class="internship-header">
                                <h3><?= htmlspecialchars($title) ?></h3>
                                <span class="internship-badge <?= $is_paid ? 'paid' : '' ?>">
                                    <?= $is_paid ? '💰 Paid' : 'Internship' ?>
                                </span>
                            </div>
                            <div class="internship-meta">
                                <span class="meta-item"><i class="fas fa-building"></i> <?= htmlspecialchars($author) ?></span>
                                <span class="meta-item"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($created_date) ?></span>
                                <span class="meta-item"><i class="fas fa-eye"></i> <?= number_format($views) ?> views</span>
                            </div>
                            <div class="internship-tags">
                                <?php if ($is_paid): ?>
                                    <span class="tag paid"><i class="fas fa-money-bill-wave"></i> Paid</span>
                                <?php endif; ?>
                                <?php if ($is_remote): ?>
                                    <span class="tag remote"><i class="fas fa-home"></i> Remote</span>
                                <?php endif; ?>
                            </div>
                            <p class="internship-description"><?= $short_desc ?></p>
                            <button class="readmore-btn" data-post-id="<?= $post_id ?>">
                                <i class="fas fa-eye"></i> View Full Details & Apply
                            </button>
                            <?php if ($deadline_date): ?>
                                <div class="deadline <?= $deadline_status ?>">
                                    <i class="fas fa-hourglass-half"></i>
                                    Deadline: <?= htmlspecialchars($deadline_date) ?>
                                    <?php if ($deadline_status === 'urgent'): ?>
                                        <span style="background:#dc2626;color:white;padding:0.2rem 0.5rem;border-radius:4px;margin-left:0.5rem;">Urgent</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>New_update is your premier platform for finding jobs, scholarships, and internships in Rwanda. We connect talented individuals with amazing opportunities.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <a href="../index.php">Home</a>
                <a href="jobs.php">Jobs</a>
                <a href="scholarships.php">Scholarships</a>
                <a href="internships.php">Internships</a>
                <a href="news.php">News</a>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fas fa-envelope"></i> info@newupdate.rw</p>
                <p><i class="fas fa-phone"></i> +250 788 123 456</p>
                <p><i class="fas fa-map-marker-alt"></i> Kigali, Rwanda</p>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div style="display:flex; gap:1.2rem; margin-top:1rem; flex-wrap:wrap;">
                    <a href="#" style="color:#0d6efd; font-size:1.6rem; text-decoration:none;"><i class="fab fa-facebook"></i></a>
                    <a href="#" style="color:#0d6efd; font-size:1.6rem; text-decoration:none;"><i class="fab fa-twitter"></i></a>
                    <a href="#" style="color:#0d6efd; font-size:1.6rem; text-decoration:none;"><i class="fab fa-linkedin"></i></a>
                    <a href="#" style="color:#0d6efd; font-size:1.6rem; text-decoration:none;"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date('Y') ?> New_update. All rights reserved. | Find Jobs, Scholarships & Internships in Rwanda</p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        const cards       = document.querySelectorAll('.internship-card');

        // Live search
        if (searchInput && cards.length) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                cards.forEach(card => {
                    const title   = card.querySelector('h3')?.textContent.toLowerCase() || '';
                    const company = card.querySelector('.meta-item:first-child')?.textContent.toLowerCase() || '';
                    const desc    = card.querySelector('.internship-description')?.textContent.toLowerCase() || '';
                    const match   = title.includes(term) || company.includes(term) || desc.includes(term);
                    card.style.display = match ? '' : 'none';
                });
            });
        }

        // Filter buttons
        const filterBtns = document.querySelectorAll('.filter-btn');
        if (filterBtns.length && cards.length) {
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    const today  = new Date().toISOString().split('T')[0];

                    cards.forEach(card => {
                        const dl = card.dataset.deadlineIso || '';
                        const isActive = dl && dl >= today;
                        const isPaid   = card.querySelector('.tag.paid') !== null;
                        const isRemote = card.querySelector('.tag.remote') !== null;

                        let show = true;

                        if (filter === 'active')  show = isActive;
                        if (filter === 'paid')    show = isPaid;
                        if (filter === 'remote')  show = isRemote;

                        card.style.display = show ? '' : 'none';
                    });
                });
            });
        }

        // Card navigation – only via button
        document.querySelectorAll('.readmore-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.dataset.postId;
                if (postId) {
                    window.location.href = `../view_post.php?id=${encodeURIComponent(postId)}`;
                }
            });
        });
    });
    </script>
</body>
</html>