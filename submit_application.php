<?php
// submit_application.php
// === DB config ===
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "scholarship_db"; // niba wahinduye, shyiramo izina ryawe

// Connect
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Handle file uploads ---
$uploaded_docs = [];
$upload_dir = __DIR__ . '/uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['documents'])) {
    $allowed_ext = ['pdf','doc','docx','jpg','jpeg','png'];
    foreach ($_FILES['documents']['name'] as $key => $name) {
        $tmp = $_FILES['documents']['tmp_name'][$key];
        $error = $_FILES['documents']['error'][$key];
        $size = $_FILES['documents']['size'][$key];

        if ($error !== UPLOAD_ERR_OK) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue; // skip disallowed
        if ($size > 5 * 1024 * 1024) continue; // skip >5MB

        // Unique filename
        $safeName = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
        $target = $upload_dir . $safeName;

        if (move_uploaded_file($tmp, $target)) {
            $uploaded_docs[] = $safeName;
        }
    }
}

$documents_str = implode(",", $uploaded_docs);

// --- Prepare INSERT ---
// NOTE: Order must match the table columns exactly (except id, created_at)
$sql = "INSERT INTO applications (
    First_name, Middle_name, Last_name, email, phone,
    current_isibo, current_umudugudu, current_umurenge, current_intara, current_akarere, current_country,
    father_name, mother_name,
    father_isibo, father_umudugudu, father_umurenge, father_intara, father_akarere, father_country,
    mother_isibo, mother_umudugudu, mother_umurenge, mother_intara, mother_akarere, mother_country,
    isibo, umudugudu, umurenge, intara, akarere, country,
    primary_school, secondary_school, university, education_level, cover_letter,
    documents
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Build params in the same order
$params = [
    $_POST['First_name'] ?? '',
    $_POST['Middle_name'] ?? '',
    $_POST['Last_name'] ?? '',
    $_POST['email'] ?? '',
    $_POST['phone'] ?? '',

    $_POST['current_isibo'] ?? '',
    $_POST['current_umudugudu'] ?? '',
    $_POST['current_umurenge'] ?? '',
    $_POST['current_intara'] ?? '',
    $_POST['current_akarere'] ?? '',
    $_POST['current_country'] ?? '',

    $_POST['father_name'] ?? '',
    $_POST['mother_name'] ?? '',

    $_POST['father_isibo'] ?? '',
    $_POST['father_umudugudu'] ?? '',
    $_POST['father_umurenge'] ?? '',
    $_POST['father_intara'] ?? '',
    $_POST['father_akarere'] ?? '',
    $_POST['father_country'] ?? '',

    $_POST['mother_isibo'] ?? '',
    $_POST['mother_umudugudu'] ?? '',
    $_POST['mother_umurenge'] ?? '',
    $_POST['mother_intara'] ?? '',
    $_POST['mother_akarere'] ?? '',
    $_POST['mother_country'] ?? '',

    $_POST['isibo'] ?? '',
    $_POST['umudugudu'] ?? '',
    $_POST['umurenge'] ?? '',
    $_POST['intara'] ?? '',
    $_POST['akarere'] ?? '',
    $_POST['country'] ?? '',

    $_POST['primary_school'] ?? '',
    $_POST['secondary_school'] ?? '',
    $_POST['university'] ?? '',
    $_POST['education_level'] ?? '',
    $_POST['cover_letter'] ?? '',

    $documents_str
];

// Ensure we have 37 params (matching table)
if (count($params) !== 37) {
    die("Internal error: parameter count mismatch.");
}

// Prepare types string (37 times 's')
$types = str_repeat('s', count($params));

// mysqli_stmt::bind_param needs references for call_user_func_array
$refs = [];
foreach ($params as $key => $value) {
    $refs[$key] = &$params[$key];
}
array_unshift($refs, $types);

// Bind and execute
call_user_func_array([$stmt, 'bind_param'], $refs);

if ($stmt->execute()) {
    // Success - redirect or show message
    echo "<h2>Application submitted successfully!</h2>";
    echo "<p>Murakoze. Application yanyu yoherejwe.</p>";
    echo '<p><a href="index.html">Send another application</a></p>';
} else {
    echo "Execute failed: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
