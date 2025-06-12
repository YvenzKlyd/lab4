<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE || $_SESSION['usertype'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .dashboard-links {
            margin: 20px 0;
        }
        .dashboard-links a {
            display: inline-block;
            margin: 5px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .dashboard-links a:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, Admin <?php echo $_SESSION['username']; ?>!</h2>
        <p>This is your admin dashboard. You have special privileges here.</p>
        
        <div class="dashboard-links">
            <a href="manage_users.php">Manage Users</a>
            <a href="file_management.php">File Management</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>