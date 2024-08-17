<?php
session_start(); // Ensure the session is started

require_once '../htdocs/includes/db.php'; // Adjust the path as needed
require_once 'inventory_functions.php'; // Include the plugin functions

// Check if the user is logged in and is an admin
$username = $_SESSION['username'] ?? '';
$stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    // Redirect to the index page if the user is not an admin
    header("Location: index.php");
    exit;
}

// Function to check if the schema is initialized
function is_schema_initialized($pdo) {
    // Check for the existence of a specific table
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'inventory'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Handle error if needed
        return false;
    }
}

// Initialize or update the database schema if not already initialized
if (!is_schema_initialized($pdo)) {
    try {
        install_inventory_plugin_schema($pdo);
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error during schema initialization: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_item'])) {
            // Validate and sanitize inputs here
            create_item(
                $pdo,
                $_POST['item_name'],
                $_POST['item_description'],
                $_POST['item_type'],
                $_POST['item_initial_quantity'],
                $_POST['item_effect_type'],
                $_POST['item_effect_value']
            );
            echo "<div class='alert alert-success'>Item created successfully.</div>";
        }

        if (isset($_POST['assign_item'])) {
            // Validate and sanitize inputs here
            assign_item_to_user(
                $pdo,
                $_POST['user_id'],
                $_POST['item_id'],
                $_POST['quantity']
            );
            echo "<div class='alert alert-success'>Item assigned successfully.</div>";
        }

        if (isset($_POST['action_item'])) {
            // Validate and sanitize inputs here
            $action = $_POST['action_type'];
            $quantity = $_POST['quantity_action'];
            if ($action === 'consume') {
                consume_item($pdo, $_POST['user_id_action'], $_POST['item_id_action'], $quantity);
            } elseif ($action === 'delete') {
                delete_item_from_user($pdo, $_POST['user_id_action'], $_POST['item_id_action'], $quantity);
            }
            echo "<div class='alert alert-success'>Item action executed successfully.</div>";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25; // Items per page
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM users");
$stmt->execute();
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalUsers / $limit);

$stmt = $pdo->prepare("
    SELECT * FROM users
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display plugin content
function display_plugin_content($pdo) {
    global $page, $totalPages, $users; // Access global variables
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Inventory Management</title>
        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.min.js"></script>
    </head>
    <body>
    <div class="container mt-4">
        <h2>Inventory Management</h2>

        <!-- Guide for users -->
        <div class="alert alert-info">
            <h5>Guide</h5>
            <ul>
                <li><strong>Effect Type:</strong> This refers to the category of the item's effect, e.g., "hp", "attack", "defense" are the default ones.</li>
                <li><strong>Effect Value:</strong> This is the numerical value associated with the effect type, such as the amount of health restored or damage increased.</li>
            </ul>
        </div>

        <!-- Form to create a new item -->
        <h3>Create New Item</h3>
        <form action="" method="POST">
            <div class="form-group mb-3">
                <label for="item_name" class="form-label">Item Name</label>
                <input type="text" name="item_name" class="form-control" id="item_name" required>
            </div>
            <div class="form-group mb-3">
                <label for="item_description" class="form-label">Item Description</label>
                <textarea name="item_description" class="form-control" id="item_description"></textarea>
            </div>
            <div class="form-group mb-3">
                <label for="item_type" class="form-label">Item Type</label>
                <select name="item_type" class="form-control" id="item_type" required>
                    <option value="1">Equipment</option>
                    <option value="2">Consumable</option>
                    <option value="3">Currency</option>
                    <option value="4">Crafting Item</option>
                </select>
            </div>
            <div class="form-group mb-3">
                <label for="item_effect_type" class="form-label">Effect Type</label>
                <input type="text" name="item_effect_type" class="form-control" id="item_effect_type">
            </div>
            <div class="form-group mb-3">
                <label for="item_effect_value" class="form-label">Effect Value</label>
                <input type="number" name="item_effect_value" class="form-control" id="item_effect_value">
            </div>
            <div class="form-group mb-3">
                <label for="item_initial_quantity" class="form-label">Initial Quantity</label>
                <input type="number" name="item_initial_quantity" class="form-control" id="item_initial_quantity" value="0" required>
            </div>
            <button type="submit" name="create_item" class="btn btn-primary">Create Item</button>
        </form>

        <!-- Form to assign an item to a user -->
        <h3>Assign Item to User</h3>
        <form action="" method="POST">
            <div class="form-group mb-3">
                <label for="user_id" class="form-label">User ID</label>
                <input type="number" name="user_id" class="form-control" id="user_id" required>
            </div>
            <div class="form-group mb-3">
                <label for="item_id" class="form-label">Item ID</label>
                <input type="number" name="item_id" class="form-control" id="item_id" required>
            </div>
            <div class="form-group mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" id="quantity" value="1" required>
            </div>
            <button type="submit" name="assign_item" class="btn btn-primary">Assign Item</button>
        </form>

        <!-- Form to handle item actions -->
        <h3>Handle User Inventory</h3>
        <form action="" method="POST">
            <div class="form-group mb-3">
                <label for="user_id_action" class="form-label">User ID</label>
                <input type="number" name="user_id_action" class="form-control" id="user_id_action" required>
            </div>
            <div class="form-group mb-3">
                <label for="item_id_action" class="form-label">Item ID</label>
                <input type="number" name="item_id_action" class="form-control" id="item_id_action" required>
            </div>
            <div class="form-group mb-3">
                <label for="quantity_action" class="form-label">Quantity</label>
                <input type="number" name="quantity_action" class="form-control" id="quantity_action" value="1" required>
            </div>
            <div class="form-group mb-3">
                <label for="action_type" class="form-label">Action Type</label>
                <select name="action_type" class="form-control" id="action_type" required>
                    <option value="consume">Consume</option>
                    <option value="equip">Equip</option>
                    <option value="delete">Delete</option>
                </select>
            </div>
            <button type="submit" name="action_item" class="btn btn-primary">Execute Action</button>
        </form>

        <!-- Pagination controls -->
        <div class="mt-4 mb-4">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>

        <!-- User list as collapsible cards -->
        <h3>User List</h3>
        <div class="accordion" id="userAccordion">
            <?php foreach ($users as $user): ?>
                <?php
                $inventory = get_user_inventory($pdo, $user['id']);
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php echo $user['id']; ?>">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $user['id']; ?>" aria-expanded="true" aria-controls="collapse<?php echo $user['id']; ?>">
                            User ID: <?php echo htmlspecialchars($user['id']); ?> - <?php echo htmlspecialchars($user['username']); ?>
                        </button>
                    </h2>
                    <div id="collapse<?php echo $user['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $user['id']; ?>" data-bs-parent="#userAccordion">
                        <div class="accordion-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                            <td>
                                                <!-- Action buttons -->
                                                <form action="" method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id_action" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="item_id_action" value="<?php echo $item['id']; ?>">
                                                    <input type="number" name="quantity_action" value="1" min="1" required>
                                                    <button type="submit" name="action_item" value="consume" class="btn btn-success btn-sm">Consume</button>
                                                    <button type="submit" name="action_item" value="delete" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    </body>
    </html>
    <?php
}
?>
