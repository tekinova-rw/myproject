<?php
session_start();
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
    $pass = $_POST['password'];

    $sql = "SELECT name, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($name, $hashed_pass);

    if ($stmt->fetch() && password_verify($pass, $hashed_pass)) {
        $_SESSION['user'] = $name;
        header("Location: index.html?success=login");
    } else {
        header("Location: index.html?error=invalid_credentials");
    }
    $stmt->close();
}
$conn->close();
?>