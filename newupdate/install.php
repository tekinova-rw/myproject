<?php
// install.php - Run this once to setup database
require_once 'config/config.php';

$pdo = getDB();

// Read and execute SQL file
$sql = file_get_contents('database.sql');

try {
    // Execute multiple queries
    $pdo->exec("USE opportunity_hub");
    
    // Split queries by semicolon
    $queries = explode(';', $sql);
    
    foreach($queries as $query) {
        $query = trim($query);
        if(!empty($query) && !preg_match('/^CREATE DATABASE/', $query)) {
            try {
                $pdo->exec($query);
            } catch(PDOException $e) {
                // Ignore "already exists" errors
                if(strpos($e->getMessage(), 'already exists') === false) {
                    echo "Error: " . $e->getMessage() . "<br>";
                }
            }
        }
    }
    
    echo "<div style='color:green; padding:20px;'>";
    echo "<h2>✅ Database Installation Complete!</h2>";
    echo "<p>All tables have been created successfully.</p>";
    echo "<p><strong>Admin Credentials:</strong></p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Email: admin@opportunityhub.com</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<a href='login.php' class='btn btn-primary'>Go to Login</a>";
    echo "</div>";
    
} catch(PDOException $e) {
    echo "<div style='color:red; padding:20px;'>";
    echo "<h2>❌ Installation Failed!</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>