<?php
$servername = "localhost";
$username = "root";  // Replace
$password = "";      // Replace
$dbname = "jobs_scholarships";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Simulate sending reset link (in real app, generate token and email it)
        header("Location: index.html?success=reset_sent");
    } else {
        header("Location: index.html?error=email_not_found");
    }
    $stmt->close();
}
$conn->close();
?>