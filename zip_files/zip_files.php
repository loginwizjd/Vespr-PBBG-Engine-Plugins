<?php

require_once '../htdocs/includes/db.php'; // Adjust the path as needed
session_start();

// Function to install plugin schema
function install_plugin_schema($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS zip_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        author VARCHAR(255),
        version VARCHAR(50),
        file_path VARCHAR(255) NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    $pdo->exec($sql);
}

// Ensure schema is installed
install_plugin_schema($pdo);

// Function to handle file upload
function handle_file_upload($pdo) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $author = $_POST['author'];
        $version = $_POST['version'];
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['file']['tmp_name'];
            $fileName = $_FILES['file']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['zip'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = 'uploads/';
                $dest_path = $uploadFileDir . $fileName;
                
                // Check if file already exists
                $stmt = $pdo->prepare("SELECT id, file_path FROM zip_files WHERE name = ?");
                $stmt->execute([$name]);
                $existingFile = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingFile) {
                    // File exists, update it
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Delete the old file
                        if (file_exists($existingFile['file_path'])) {
                            unlink($existingFile['file_path']);
                        }
                        
                        // Update the database record
                        $stmt = $pdo->prepare("UPDATE zip_files SET description = ?, author = ?, version = ?, file_path = ?, upload_date = CURRENT_TIMESTAMP WHERE id = ?");
                        $stmt->execute([$description, $author, $version, $dest_path, $existingFile['id']]);
                        return "File updated successfully.";
                    } else {
                        return "There was an error uploading the file.";
                    }
                } else {
                    // New file, insert it
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $stmt = $pdo->prepare("INSERT INTO zip_files (name, description, author, version, file_path) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $description, $author, $version, $dest_path]);
                        return "File uploaded successfully.";
                    } else {
                        return "There was an error uploading the file.";
                    }
                }
            } else {
                return "Upload failed. Allowed file types: zip.";
            }
        } else {
            return "No file uploaded or file upload error.";
        }
    }
    return null;
}

// Function to display plugin content
function display_plugin_content($pdo) {
    // Handle file upload
    $uploadMessage = handle_file_upload($pdo);
    
    // Fetch available ZIP files
    $stmt = $pdo->query("SELECT * FROM zip_files ORDER BY upload_date DESC");
    $zipFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // HTML and CSS
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'Admin - Manage ZIP Files' : 'Available ZIP Files'; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f4f4f9;
                color: #333;
            }
            
            .container {
                margin-top: 20px;
            }
            
            .upload-form-container,
            .zip-files-container {
                max-width: 800px;
                margin: 20px auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            .upload-form-container h2,
            .zip-files-container h2 {
                margin-bottom: 20px;
                font-size: 1.5rem;
                color: #007bff;
            }
            
            .upload-form-container .form-label,
            .zip-files-container .zip-file-item h5 {
                font-weight: bold;
                color: #333;
            }
            
            .zip-file-item {
                border-bottom: 1px solid #ddd;
                padding: 10px 0;
            }

            .zip-file-item:last-child {
                border-bottom: none;
            }

            .zip-file-item h5 {
                margin: 0;
                font-size: 1.2rem;
                color: #333;
            }

            .zip-file-item p {
                margin: 5px 0;
                color: #333;
            }
            
            .zip-file-item .btn-primary {
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <!-- Admin Interface -->
            <div class="upload-form-container">
                <h2 class="text-center">Upload or Update Plugin File</h2>
                <?php if ($uploadMessage): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($uploadMessage); ?></div>
                <?php endif; ?>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="author" class="form-label">Author</label>
                        <input type="text" id="author" name="author" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="version" class="form-label">Version</label>
                        <input type="text" id="version" name="version" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label">ZIP File</label>
                        <input type="file" id="file" name="file" class="form-control" accept=".zip" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Upload</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Shared Interface for Admins and Regular Users -->
        <div class="zip-files-container">
            <h2 class="text-center">Available Plugins</h2>
            <?php if (!empty($zipFiles)): ?>
                <?php foreach ($zipFiles as $file): ?>
                    <div class="zip-file-item">
                        <h5><?php echo htmlspecialchars($file['name']); ?></h5>
                        <p><?php echo htmlspecialchars($file['description']); ?></p>
                        <p><strong>Author:</strong> <?php echo htmlspecialchars($file['author']); ?></p>
                        <p><strong>Version:</strong> <?php echo htmlspecialchars($file['version']); ?></p>
                        <a href="<?php echo htmlspecialchars($file['file_path']); ?>" class="btn btn-primary">Download</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No ZIP files available.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
