<?php
// Database connection
$host = "localhost";
$dbname = "scholarship_db";
$username = "root"; // change if needed
$password = "";     // change if needed

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle file uploads
$uploadedFiles = [];
if (!empty($_FILES['documents']['name'][0])) {
  $uploadDir = "uploads/";
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
  }

  foreach ($_FILES['documents']['tmp_name'] as $key => $tmp_name) {
    $fileName = basename($_FILES['documents']['name'][$key]);
    $targetFile = $uploadDir . time() . "_" . $fileName;

    if (move_uploaded_file($tmp_name, $targetFile)) {
      $uploadedFiles[] = $targetFile;
    }
  }
}

// Convert uploaded files to string
$uploadedFilesString = implode(", ", $uploadedFiles);

// Prepare and insert data
$sql = "INSERT INTO applications (
  First_name, Middle_name, Last_name, email, phone,
  current_isibo, current_umudugudu, current_umurenge, current_intara, current_akarere, current_country,
  father_name, mother_name, father_isibo, father_umudugudu, father_umurenge, father_intara, father_akarere, father_country,
  mother_isibo, mother_umudugudu, mother_umurenge, mother_intara, mother_akarere, mother_country,
  isibo, umudugudu, umurenge, intara, akarere, country,
  primary_school, secondary_school, university, education_level, cover_letter, uploaded_files
) VALUES (
  ?, ?, ?, ?, ?,
  ?, ?, ?, ?, ?, ?,
  ?, ?, ?, ?, ?, ?, ?, ?,
  ?, ?, ?, ?, ?, ?,
  ?, ?, ?, ?, ?, ?,
  ?, ?, ?, ?, ?, ?
)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
  "ssssssssssssssssssssssssssssssssss",
  $_POST['First_name'],
  $_POST['Middle_name'],
  $_POST['Last_name'],
  $_POST['email'],
  $_POST['phone'],
  $_POST['current_isibo'],
  $_POST['current_umudugudu'],
  $_POST['current_umurenge'],
  $_POST['current_intara'],
  $_POST['current_akarere'],
  $_POST['current_country'],
  $_POST['father_name'],
  $_POST['mother_name'],
  $_POST['father_isibo'],
  $_POST['father_umudugudu'],
  $_POST['father_umurenge'],
  $_POST['father_intara'],
  $_POST['father_akarere'],
  $_POST['father_country'],
  $_POST['mother_isibo'],
  $_POST['mother_umudugudu'],
  $_POST['mother_umurenge'],
  $_POST['mother_intara'],
  $_POST['mother_akarere'],
  $_POST['mother_country'],
  $_POST['isibo'],
  $_POST['umudugudu'],
  $_POST['umurenge'],
  $_POST['intara'],
  $_POST['akarere'],
  $_POST['country'],
  $_POST['primary_school'],
  $_POST['secondary_school'],
  $_POST['university'],
  $_POST['education_level'],
  $_POST['cover_letter'],
  $uploadedFilesString
);

if ($stmt->execute()) {
  echo "<h2 style='text-align:center;color:green;'>🎉 Application Submitted Successfully!</h2>";
  echo "<p style='text-align:center;'><a href='index.html'>Go Back to Form</a></p>";
} else {
  echo "<h2 style='color:red;text-align:center;'>Error: " . $stmt->error . "</h2>";
}

$stmt->close();
$conn->close();
?>
