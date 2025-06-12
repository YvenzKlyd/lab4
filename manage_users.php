<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE || $_SESSION['usertype'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    // Prevent admin from changing their own role
    if ($user_id == $_SESSION['id']) {
        $message = "You cannot change your own role!";
    } else {
        $sql = "UPDATE users SET usertype = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            $message = "User role updated successfully!";
        } else {
            $message = "Error updating user role: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all users
$sql = "SELECT id, username, usertype, reg_date FROM users ORDER BY reg_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .user-table th, .user-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .user-table th {
            background-color: #f5f5f5;
        }
        .message {
            color: #5cb85c;
            margin-bottom: 20px;
        }
        .error {
            color: #d9534f;
        }
    </style>
</head>
<body>
    <div class="container" style="width: 800px;">
        <h2>Manage Users</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Current Role</th>
                    <th>Registration Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['usertype']); ?></td>
                    <td><?php echo htmlspecialchars($row['reg_date']); ?></td>
                    <td>
                        <?php if ($row['id'] != $_SESSION['id']): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <select name="new_role">
                                <option value="user" <?php echo $row['usertype'] == 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $row['usertype'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <input type="submit" value="Update Role">
                        </form>
                        <?php else: ?>
                        <em>Current User</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <p><a href="admin_dashboard.php">Back to Dashboard</a></p>
    </div>
</body>
</html>
