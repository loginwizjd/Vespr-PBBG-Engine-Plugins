<?php
require_once '../htdocs/includes/db.php'; // Adjust the path as needed
require_once '../htdocs/assets/parsedown/Parsedown.php'; // Include Parsedown

$parsedown = new Parsedown(); // Initialize Parsedown

// Function to install or update the plugin's database schema
function install_plugin_schema($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'docs'");
    $stmt->execute();
    $tableExists = (bool) $stmt->fetchColumn();

    if ($tableExists) {
        return;
    }

    $sql = "
    CREATE TABLE IF NOT EXISTS docs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    ";
    $pdo->exec($sql);

    $settings = [
        ['docs_display_limit', '10', 'docs'],
        ['docs_enable_search', '1', 'docs'],
        ['docs_tables', 'docs', 'docs']
    ];

    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO plugin_settings (setting_name, setting_value, plugin_directory) VALUES (?, ?, ?)");
        $stmt->execute($setting);
    }
}

// Function to get a plugin setting value
function get_plugin_setting($pdo, $settingName, $pluginDir) {
    $stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE setting_name = ? AND plugin_directory = ?");
    $stmt->execute([$settingName, $pluginDir]);
    return $stmt->fetchColumn();
}

// Handle document creation
function create_document($pdo, $title, $content) {
    global $parsedown;
    $stmt = $pdo->prepare("INSERT INTO docs (title, content) VALUES (?, ?)");
    $stmt->execute([$title, $content]); // Store Markdown as plain text
}

// Handle document update
function update_document($pdo, $id, $title, $content) {
    $stmt = $pdo->prepare("UPDATE docs SET title = ?, content = ? WHERE id = ?");
    $stmt->execute([$title, $content, $id]); // Store Markdown as plain text
}

// Handle document deletion
function delete_document($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM docs WHERE id = ?");
    $stmt->execute([$id]);
}

// Fetch all documents with pagination
function fetch_documents($pdo, $limit, $offset) {
    $stmt = $pdo->prepare("SELECT * FROM docs ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch a single document by ID
function fetch_document_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM docs WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Check if the current user is an admin
function is_admin() {
    // Implement your actual user role check here
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Build URL with query parameters
function build_url_with_params($params = []) {
    $query = http_build_query(array_merge($_GET, $params));
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . $query;
}

// Display plugin content
function display_plugin_content($pdo) {
    global $parsedown;

    install_plugin_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create']) && is_admin()) {
            create_document($pdo, $_POST['title'], $_POST['content']);
            header("Location: " . build_url_with_params()); // Redirect to preserve existing query parameters
            exit;
        }

        if (isset($_POST['update']) && is_admin()) {
            update_document($pdo, $_POST['id'], $_POST['title'], $_POST['content']);
            header("Location: " . build_url_with_params()); // Redirect to preserve existing query parameters
            exit;
        }

        if (isset($_POST['delete']) && is_admin()) {
            delete_document($pdo, $_POST['id']);
            header("Location: " . build_url_with_params()); // Redirect to preserve existing query parameters
            exit;
        }
    }

    // Debugging: Verify if the setting is retrieved correctly
    $docsPerPage = (int)get_plugin_setting($pdo, 'docs_display_limit', 'docs');
    if ($docsPerPage <= 0) {
        $docsPerPage = 10; // Default value if setting is not found or invalid
    }
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $docsPerPage;

    // Debugging: Print $page, $offset, and $docsPerPage to check pagination
    error_log("Page: $page, Offset: $offset, Docs Per Page: $docsPerPage");

    $documents = fetch_documents($pdo, $docsPerPage, $offset);

    // Debugging: Check if documents are fetched
    if (empty($documents)) {
        error_log("No documents found.");
    }

    // Get the base URL and append the create_new parameter
    $createNewUrl = build_url_with_params(['create_new' => 'true']);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Documentation Manager</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.1.0/css/bootstrap.min.css">
        <style>
            body {
                background-color: #f9d857; /* Background color */
            }
            .docs-container {
                width: 100%;
                max-width: 900px;
                margin: 0 auto;
                padding: 20px;
                background-color: #292102; /* Container background color */
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }
            .docs-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                color: #f9d857; /* Header color */
            }
            .docs-list {
                margin-bottom: 20px;
            }
            .doc-card {
                margin-bottom: 20px;
                border: 1px solid #b0afad; /* Card border color */
                border-radius: 8px;
                overflow: hidden;
                background-color: #292102; /* Card background color */
                color: #b0afad; /* Text color inside card */
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .doc-card-body {
                padding: 20px;
            }
            .doc-card-title {
                font-size: 1.5rem;
                margin-bottom: 10px;
                color: #f9d857; /* Card title color */
            }
            .doc-card-content {
                margin-bottom: 15px;
            }
            .btn-primary {
                background-color: #5f5633; /* Button background color */
                border-color: #5f5633; /* Button border color */
            }
            .btn-primary:hover {
                background-color: #4e4428; /* Button hover background color */
                border-color: #4e4428; /* Button hover border color */
            }
            .btn-danger {
                background-color: #b03a2e; /* Danger button background color */
                border-color: #b03a2e; /* Danger button border color */
            }
            .btn-danger:hover {
                background-color: #a02e1b; /* Danger button hover background color */
                border-color: #a02e1b; /* Danger button hover border color */
            }
            .pagination .page-link {
                background-color: #292102; /* Pagination background color */
                color: #b0afad; /* Pagination text color */
            }
            .pagination .page-link:hover {
                background-color: #5f5633; /* Pagination hover background color */
                color: #f9d857; /* Pagination hover text color */
            }
            .pagination .page-item.disabled .page-link {
                background-color: #292102;
                color: #b0afad;
            }
        </style>
    </head>
    <body>

    <div class="docs-container">
        <div class="docs-header">
            <h1>Documentation Manager</h1>
            <?php if (is_admin()): ?>
                <a href="<?php echo htmlspecialchars($createNewUrl); ?>" class="btn btn-primary">Create New Document</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['view'])): ?>
            <?php
            $doc = fetch_document_by_id($pdo, $_GET['view']);
            ?>
            <?php if ($doc): ?>
                <div class="doc-card">
                    <div class="doc-card-body">
                        <h3 class="doc-card-title"><?php echo htmlspecialchars($doc['title']); ?></h3>
                        <div class="doc-card-content"><?php echo $parsedown->text($doc['content']); ?></div>
                        <a href="<?php echo build_url_with_params(['view' => null, 'edit' => null, 'create_new' => null]); ?>" class="btn btn-secondary">Back to List</a>
                        <?php if (is_admin()): ?>
                            <a href="<?php echo build_url_with_params(['edit' => $doc['id']]); ?>" class="btn btn-primary">Edit</a>
                            <form action="<?php echo build_url_with_params(); ?>" method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p>Document not found.</p>
            <?php endif; ?>

        <?php elseif (isset($_GET['create_new']) && is_admin()): ?>
            <div class="doc-card">
                <div class="doc-card-body">
                    <h3 class="doc-card-title">Create New Document</h3>
                    <form action="<?php echo build_url_with_params(); ?>" method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" id="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Content (Markdown)</label>
                            <textarea name="content" class="form-control" id="content" rows="5" required></textarea>
                        </div>
                        <button type="submit" name="create" class="btn btn-primary">Create</button>
                        <a href="<?php echo build_url_with_params(['view' => null, 'edit' => null, 'create_new' => null]); ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>

        <?php elseif (isset($_GET['edit']) && is_admin()): ?>
            <?php
            $doc = fetch_document_by_id($pdo, $_GET['edit']);
            ?>
            <?php if ($doc): ?>
                <div class="doc-card">
                    <div class="doc-card-body">
                        <h3 class="doc-card-title">Edit Document</h3>
                        <form action="<?php echo build_url_with_params(); ?>" method="POST">
                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" id="title" value="<?php echo htmlspecialchars($doc['title']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content (Markdown)</label>
                                <textarea name="content" class="form-control" id="content" rows="5" required><?php echo htmlspecialchars($doc['content']); ?></textarea>
                            </div>
                            <button type="submit" name="update" class="btn btn-primary">Update</button>
                            <a href="<?php echo build_url_with_params(['view' => null, 'edit' => null, 'create_new' => null]); ?>" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <p>Document not found.</p>
            <?php endif; ?>

        <?php else: ?>
            <div class="docs-list">
                <?php foreach ($documents as $doc): ?>
                    <div class="doc-card">
                        <div class="doc-card-body">
                            <h3 class="doc-card-title"><?php echo htmlspecialchars($doc['title']); ?></h3>
                            <div class="doc-card-content"><?php echo $parsedown->text(mb_strimwidth($doc['content'], 0, 200, '...')); ?></div>
                            <a href="<?php echo build_url_with_params(['view' => $doc['id']]); ?>" class="btn btn-secondary">Read More</a>
                            <?php if (is_admin()): ?>
                                <a href="<?php echo build_url_with_params(['edit' => $doc['id']]); ?>" class="btn btn-primary">Edit</a>
                                <form action="<?php echo build_url_with_params(); ?>" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM docs");
            $totalDocs = $stmt->fetchColumn();
            $totalPages = ceil($totalDocs / $docsPerPage);
            ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo build_url_with_params(['page' => $page - 1]); ?>">Previous</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Previous</span>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo build_url_with_params(['page' => $i]); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo build_url_with_params(['page' => $page + 1]); ?>">Next</a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Next</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

        <?php endif; ?>
    </div>

    </body>
    </html>
    <?php
}

?>
