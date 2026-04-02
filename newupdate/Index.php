<?php
// index.php
require_once 'config/config.php';

autoManageExpiredContent();

$pdo = getDB();
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

if ($category !== 'all') {
    $stmt = $pdo->prepare("SELECT o.*, c.name as company_name, c.logo as company_logo 
                           FROM opportunities o 
                           JOIN companies c ON o.company_id = c.id 
                           WHERE o.status = 'active' AND o.category = ? AND c.status = 'approved'
                           ORDER BY o.created_at DESC");
    $stmt->execute([$category]);
} else {
    $stmt = $pdo->prepare("SELECT o.*, c.name as company_name, c.logo as company_logo 
                           FROM opportunities o 
                           JOIN companies c ON o.company_id = c.id 
                           WHERE o.status = 'active' AND c.status = 'approved'
                           ORDER BY o.created_at DESC");
    $stmt->execute();
}
$opportunities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunity Hub - Jobs, Scholarships & Internships</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .countdown-timer {
            font-size: 14px;
            color: #e74c3c;
            font-weight: bold;
        }
        .opportunity-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .opportunity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
        .category-badge {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .qualification-preview {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
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
                    <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>
                    <?php if(isLoggedIn()): ?>
                        <?php if(isAdmin()): ?>
                            <li class="nav-item"><a class="nav-link" href="dashboard/admin.php">Admin Panel</a></li>
                        <?php elseif(isCompany()): ?>
                            <li class="nav-item"><a class="nav-link" href="dashboard/company.php">Company Dashboard</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="register-company.php">Register Company</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-center mb-4">Explore Opportunities</h1>
        
        <!-- Category Filters -->
        <div class="row mb-4">
            <div class="col-md-6 mx-auto">
                <div class="btn-group w-100" role="group">
                    <a href="?category=all" class="btn <?php echo $category == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="?category=job" class="btn <?php echo $category == 'job' ? 'btn-primary' : 'btn-outline-primary'; ?>">Jobs</a>
                    <a href="?category=scholarship" class="btn <?php echo $category == 'scholarship' ? 'btn-primary' : 'btn-outline-primary'; ?>">Scholarships</a>
                    <a href="?category=internship" class="btn <?php echo $category == 'internship' ? 'btn-primary' : 'btn-outline-primary'; ?>">Internships</a>
                </div>
            </div>
        </div>

        <!-- Opportunities Grid -->
        <div class="row">
            <?php foreach($opportunities as $opp): 
                $timeData = timeRemaining($opp['deadline']);
                $qualifications = json_decode($opp['qualifications'], true) ?: [];
                $firstQualification = !empty($qualifications) ? $qualifications[0] : '';
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card opportunity-card">
                        <div class="card-body">
                            <span class="badge bg-primary category-badge">
                                <?php echo ucfirst($opp['category']); ?>
                            </span>
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($opp['title']); ?></h5>
                                    <h6 class="card-subtitle text-muted"><?php echo htmlspecialchars($opp['company_name']); ?></h6>
                                </div>
                                <img src="uploads/<?php echo htmlspecialchars($opp['company_logo']); ?>" 
                                     class="company-logo" alt="Company logo">
                            </div>
                            <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($opp['location']); ?></p>
                            <p><strong>🎓 Degree:</strong> <?php echo htmlspecialchars($opp['degree_required']); ?></p>
                            <?php if($firstQualification): ?>
                                <div class="qualification-preview">
                                    <strong>✨ Key Qualification:</strong> <?php echo htmlspecialchars($firstQualification); ?>
                                </div>
                            <?php endif; ?>
                            <div class="countdown-timer mt-2" data-deadline="<?php echo $opp['deadline']; ?>">
                                <?php if(!$timeData['expired']): ?>
                                    ⏰ Time Remaining: <span class="timer" data-deadline="<?php echo $opp['deadline']; ?>">
                                        <?php echo $timeData['text']; ?>
                                    </span>
                                <?php else: ?>
                                    ❌ Expired
                                <?php endif; ?>
                            </div>
                            <div class="mt-3">
                                <a href="view_post.php?id=<?php echo $opp['id']; ?>" class="btn btn-primary w-100">Apply Now →</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if(empty($opportunities)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">No opportunities found at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCountdowns() {
            document.querySelectorAll('.timer').forEach(function(element) {
                const deadline = new Date(element.dataset.deadline).getTime();
                const now = new Date().getTime();
                const distance = deadline - now;
                
                if (distance < 0) {
                    element.innerHTML = 'Expired';
                    element.closest('.countdown-timer').innerHTML = '❌ Expired';
                    return;
                }
                
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                element.innerHTML = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            });
        }
        
        setInterval(updateCountdowns, 1000);
        updateCountdowns();
    </script>
</body>
</html>