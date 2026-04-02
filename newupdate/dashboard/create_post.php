<?php
// dashboard/create_post.php
require_once '../config/config.php';

if (!isAdmin() && !isCompany()) {
    redirect('../login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $description = $_POST['description'] ?? '';
    $location = sanitizeInput($_POST['location'] ?? '');
    $degree_required = sanitizeInput($_POST['degree_required'] ?? '');
    $qualifications = array_map('sanitizeInput', explode("\n", $_POST['qualifications'] ?? ''));
    $application_method = sanitizeInput($_POST['application_method'] ?? '');
    $application_value = sanitizeInput($_POST['application_value'] ?? '');
    $deadline = sanitizeInput($_POST['deadline'] ?? '');
    
    $company_id = isAdmin() ? ($_POST['company_id'] ?? 0) : $_SESSION['user_id'];
    
    if (empty($title) || empty($category) || empty($description) || empty($deadline)) {
        $error = 'Please fill in all required fields';
    } elseif ($application_method === 'email' && !filter_var($application_value, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($application_method === 'link' && !filter_var($application_value, FILTER_VALIDATE_URL)) {
        $error = 'Please enter a valid URL';
    } else {
        $pdo = getDB();
        $qualifications_json = json_encode(array_filter($qualifications));
        
        $stmt = $pdo->prepare("INSERT INTO opportunities (company_id, title, category, description, location, degree_required, qualifications, application_method, application_value, deadline) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$company_id, $title, $category, $description, $location, $degree_required, $qualifications_json, $application_method, $application_value, $deadline])) {
            $success = 'Opportunity posted successfully!';
            if (!isAdmin()) {
                redirect('company.php');
            }
        } else {
            $error = 'Failed to create opportunity';
        }
    }
}

if (isAdmin()) {
    $pdo = getDB();
    $companies = $pdo->query("SELECT id, name FROM companies WHERE status = 'approved' ORDER BY name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Opportunity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h3>Post New Opportunity</h3>
            </div>
            <div class="card-body">
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" id="postForm">
                    <?php if(isAdmin()): ?>
                        <div class="mb-3">
                            <label>Select Company</label>
                            <select name="company_id" class="form-control" required>
                                <option value="">Select Company</option>
                                <?php foreach($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Category *</label>
                            <select name="category" class="form-control" required>
                                <option value="job">Job</option>
                                <option value="scholarship">Scholarship</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Degree Required</label>
                            <input type="text" name="degree_required" class="form-control" placeholder="e.g., Bachelor's Degree">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Description *</label>
                        <div id="editor" style="height: 300px;"></div>
                        <textarea name="description" style="display:none;"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label>Qualifications (one per line)</label>
                        <textarea name="qualifications" class="form-control" rows="3" placeholder="Bachelor's degree in Computer Science&#10;3+ years experience&#10;Strong communication skills"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Application Method *</label>
                            <select name="application_method" id="appMethod" class="form-control" required>
                                <option value="email">Email</option>
                                <option value="link">External Link</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Application Value *</label>
                            <input type="text" name="application_value" id="appValue" class="form-control" required 
                                   placeholder="email@example.com or https://example.com/apply">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Deadline *</label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Post Opportunity</button>
                    <a href="<?php echo isAdmin() ? 'admin.php?tab=posts' : 'company.php'; ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'image', 'video'],
                    ['clean']
                ]
            }
        });
        
        document.getElementById('postForm').onsubmit = function() {
            var description = document.querySelector('textarea[name="description"]');
            description.value = quill.root.innerHTML;
        };
        
        document.getElementById('appMethod').addEventListener('change', function() {
            var input = document.getElementById('appValue');
            if(this.value === 'email') {
                input.placeholder = 'email@example.com';
                input.type = 'email';
            } else {
                input.placeholder = 'https://example.com/apply';
                input.type = 'url';
            }
        });
    </script>
</body>
</html>