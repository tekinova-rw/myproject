<?php
// view_post.php
require_once 'config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pdo = getDB();
$stmt = $pdo->prepare("SELECT o.*, c.name as company_name, c.logo as company_logo, c.email as company_email, c.website 
                       FROM opportunities o 
                       JOIN companies c ON o.company_id = c.id 
                       WHERE o.id = ? AND o.status = 'active'");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    redirect('index.php');
}

$timeData = timeRemaining($post['deadline']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Opportunity Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .company-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .content-area {
            font-size: 16px;
            line-height: 1.8;
        }
        .apply-btn {
            position: sticky;
            bottom: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Opportunity Hub</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>
                    <?php if(isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="company-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="uploads/<?php echo htmlspecialchars($post['company_logo']); ?>" 
                         class="img-fluid rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                </div>
                <div class="col-md-10">
                    <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                    <h4><?php echo htmlspecialchars($post['company_name']); ?></h4>
                    <div class="mt-2">
                        <span class="badge bg-light text-dark"><?php echo ucfirst($post['category']); ?></span>
                        <span class="badge bg-light text-dark">📍 <?php echo htmlspecialchars($post['location']); ?></span>
                        <span class="badge bg-light text-dark">🎓 <?php echo htmlspecialchars($post['degree_required']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-body content-area">
                        <h3>About this Opportunity</h3>
                        <div class="ql-editor">
                            <?php echo htmlspecialchars_decode($post['description']); ?>
                        </div>
                        
                        <hr>
                        
                        <h4>Qualifications</h4>
                        <?php 
                        $qualifications = json_decode($post['qualifications'], true);
                        if($qualifications && is_array($qualifications)): ?>
                            <ul>
                                <?php foreach($qualifications as $qual): ?>
                                    <li><?php echo htmlspecialchars($qual); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <strong>⏰ Deadline:</strong> <?php echo date('F j, Y, g:i a', strtotime($post['deadline'])); ?>
                            <?php if(!$timeData['expired']): ?>
                                <br><strong>⏱️ Time Remaining:</strong> <span id="countdown"></span>
                            <?php else: ?>
                                <br><strong>❌ This opportunity has expired</strong>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(!$timeData['expired']): ?>
                            <div class="apply-btn">
                                <?php if($post['application_method'] === 'email'): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($post['application_value']); ?>?subject=Application for <?php echo urlencode($post['title']); ?>" 
                                       class="btn btn-success btn-lg w-100">
                                        📧 Apply via Email
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($post['application_value']); ?>" 
                                       target="_blank" class="btn btn-success btn-lg w-100">
                                        🔗 Apply Now (External Link)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCountdown() {
            const deadline = new Date('<?php echo $post['deadline']; ?>').getTime();
            const now = new Date().getTime();
            const distance = deadline - now;
            
            if (distance < 0) {
                document.getElementById('countdown').innerHTML = 'Expired';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('countdown').innerHTML = 
                `${days}d ${hours}h ${minutes}m ${seconds}s`;
        }
        
        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
</body>
</html>