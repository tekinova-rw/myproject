<?php
// news.php
require_once 'config/config.php';

$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
$news = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Latest News - Opportunity Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .news-card {
            margin-bottom: 30px;
            transition: transform 0.3s;
        }
        .news-card:hover {
            transform: translateY(-5px);
        }
        .news-image {
            height: 200px;
            object-fit: cover;
        }
        .news-content {
            font-size: 16px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Opportunity Hub</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="news.php">News</a></li>
                    <?php if(isLoggedIn()): ?>
                        <?php if(isAdmin()): ?>
                            <li class="nav-item"><a class="nav-link" href="dashboard/admin.php">Admin Panel</a></li>
                        <?php elseif(isCompany()): ?>
                            <li class="nav-item"><a class="nav-link" href="dashboard/company.php">Dashboard</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register-company.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-center mb-5">Latest News & Updates</h1>
        
        <div class="row">
            <?php foreach($news as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card news-card h-100">
                        <?php if(!empty($item['image'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top news-image" alt="News image">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                            <p class="text-muted small">📅 <?php echo date('F j, Y', strtotime($item['created_at'])); ?></p>
                            <div class="news-content">
                                <?php echo substr(strip_tags($item['content']), 0, 150); ?>...
                            </div>
                            <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#newsModal<?php echo $item['id']; ?>">
                                Read More →
                            </button>
                        </div>
                    </div>
                </div>

                <!-- News Modal -->
                <div class="modal fade" id="newsModal<?php echo $item['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">Published: <?php echo date('F j, Y', strtotime($item['created_at'])); ?></p>
                                <div class="ql-editor">
                                    <?php echo htmlspecialchars_decode($item['content']); ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($news)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">No news articles available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>