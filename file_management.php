<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

// Handle file upload (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    // Check if user is admin
    if ($_SESSION['usertype'] !== 'admin') {
        $error = "Only administrators can upload files!";
    } else {
        $file = $_FILES['file'];
        $filename = basename($file['name']);
        $upload_dir = 'uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filepath = $upload_dir . time() . '_' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save file information to database
            $sql = "INSERT INTO files (filename, filepath, owner_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $filename, $filepath, $_SESSION['id']);
            
            if ($stmt->execute()) {
                $message = "File uploaded successfully!";
            } else {
                $error = "Error saving file information: " . $stmt->error;
                unlink($filepath); // Delete the uploaded file if database insert fails
            }
            $stmt->close();
        } else {
            $error = "Error uploading file!";
        }
    }
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['file_id']) && isset($_POST['user_id'])) {
    $file_id = $_POST['file_id'];
    $user_id = $_POST['user_id'];
    $can_read = isset($_POST['can_read']) ? 1 : 0;
    $can_write = isset($_POST['can_write']) ? 1 : 0;
    
    // Check if user owns the file
    $sql = "SELECT owner_id FROM files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    
    if ($file && $file['owner_id'] == $_SESSION['id']) {
        $sql = "INSERT INTO file_permissions (file_id, user_id, can_read, can_write) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE can_read = ?, can_write = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiii", $file_id, $user_id, $can_read, $can_write, $can_read, $can_write);
        
        if ($stmt->execute()) {
            $message = "Permissions updated successfully!";
        } else {
            $error = "Error updating permissions: " . $stmt->error;
        }
    } else {
        $error = "You don't have permission to modify this file's permissions!";
    }
    $stmt->close();
}

// Get user's files
$sql = "SELECT f.*, u.username as owner_name 
        FROM files f 
        JOIN users u ON f.owner_id = u.id 
        WHERE f.owner_id = ? 
        ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$my_files = $stmt->get_result();

// Get files shared with user
$sql = "SELECT f.*, u.username as owner_name, fp.can_read, fp.can_write 
        FROM files f 
        JOIN users u ON f.owner_id = u.id 
        JOIN file_permissions fp ON f.id = fp.file_id 
        WHERE fp.user_id = ? 
        ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$shared_files = $stmt->get_result();

// Get all users for permission management
$sql = "SELECT id, username FROM users WHERE id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>File Management</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        .file-section {
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .file-table th, .file-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .file-table th {
            background-color: #f5f5f5;
        }
        .permission-form {
            display: inline-block;
            margin: 5px;
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
        <h2>File Management</h2>
        
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <!-- File Upload Form (Admin Only) -->
        <?php if ($_SESSION['usertype'] === 'admin'): ?>
        <div class="file-section">
            <h3>Upload New File</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="file" required>
                <input type="submit" value="Upload">
            </form>
        </div>
        <?php endif; ?>

        <!-- My Files Section -->
        <div class="file-section">
            <h3>My Files</h3>
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Owner</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($file = $my_files->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td><?php echo htmlspecialchars($file['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                        <td>
                            <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank">View</a>
                            <?php if ($_SESSION['usertype'] === 'admin'): ?>
                            <form method="POST" class="permission-form">
                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                <select name="user_id">
                                    <?php while($user = $users->fetch_assoc()): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <?php $users->data_seek(0); // Reset users result pointer ?>
                                </select>
                                <label>
                                    <input type="checkbox" name="can_read" value="1"> Read
                                </label>
                                <label>
                                    <input type="checkbox" name="can_write" value="1"> Write
                                </label>
                                <input type="submit" value="Set Permissions">
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Shared Files Section -->
        <div class="file-section">
            <h3>Files Shared With Me</h3>
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Owner</th>
                        <th>Upload Date</th>
                        <th>Permissions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($file = $shared_files->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['filename']); ?></td>
                        <td><?php echo htmlspecialchars($file['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($file['created_at']); ?></td>
                        <td>
                            <?php
                            $permissions = [];
                            if ($file['can_read']) $permissions[] = 'Read';
                            if ($file['can_write']) $permissions[] = 'Write';
                            echo implode(', ', $permissions);
                            ?>
                        </td>
                        <td>
                            <?php if ($file['can_read']): ?>
                                <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <p><a href="<?php echo $_SESSION['usertype'] == 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>">Back to Dashboard</a></p>
    </div>
</body>
</html> 