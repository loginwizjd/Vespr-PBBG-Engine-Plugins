<?php
// inventory_functions.php

// Function to install or update the plugin's database schema
function install_inventory_plugin_schema($pdo) {
    // Create tables if they don't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            type INT NOT NULL,
            effect_type VARCHAR(255),
            effect_value INT,
            UNIQUE KEY (name)
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_stats (
            user_id INT PRIMARY KEY,
            hp INT DEFAULT 100,
            attack INT DEFAULT 10,
            defense INT DEFAULT 10,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_inventory (
            user_id INT,
            item_id INT,
            quantity INT,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (item_id) REFERENCES items(id),
            PRIMARY KEY (user_id, item_id)
        )
    ");
    
    // Initialize user stats for all users
    initialize_user_stats($pdo);
}

// Function to create a new item
function create_item($pdo, $name, $description, $type, $initial_quantity, $effect_type, $effect_value) {
    $stmt = $pdo->prepare("
        INSERT INTO items (name, description, type, effect_type, effect_value)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description), type = VALUES(type), effect_type = VALUES(effect_type), effect_value = VALUES(effect_value)
    ");
    $stmt->execute([$name, $description, $type, $effect_type, $effect_value]);

    // Initialize the item quantity for the admin or predefined user (e.g., on user sign-up)
    $itemId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = ? WHERE item_id = ?");
    $stmt->execute([$initial_quantity, $itemId]);
}

// Function to assign an item to a user
function assign_item_to_user($pdo, $userId, $itemId, $quantity) {
    $stmt = $pdo->prepare("
        INSERT INTO user_inventory (user_id, item_id, quantity) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ");
    $stmt->execute([$userId, $itemId, $quantity]);
}

// Function to get a user's inventory
function get_user_inventory($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.name, i.description, i.type, ui.quantity
        FROM user_inventory ui
        JOIN items i ON ui.item_id = i.id
        WHERE ui.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to consume an item
function consume_item($pdo, $userId, $itemId, $quantity) {
    $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
    $stmt->execute([$userId, $itemId]);
    $inventoryItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventoryItem || $inventoryItem['quantity'] < $quantity) {
        throw new Exception("Not enough items to consume.");
    }

    $newQuantity = $inventoryItem['quantity'] - $quantity;
    if ($newQuantity > 0) {
        $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = ? WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$newQuantity, $userId, $itemId]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
    }

    apply_item_effects($pdo, $userId, $itemId, 'consume');
}

// Function to delete items from a user's inventory
function delete_item_from_user($pdo, $userId, $itemId, $quantity) {
    $stmt = $pdo->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
    $stmt->execute([$userId, $itemId]);
    $inventoryItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inventoryItem || $inventoryItem['quantity'] < $quantity) {
        throw new Exception("Not enough items to delete.");
    }

    $newQuantity = $inventoryItem['quantity'] - $quantity;
    if ($newQuantity > 0) {
        $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = ? WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$newQuantity, $userId, $itemId]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);
    }

    remove_item_effects($pdo, $userId, $itemId, 'unequip');
}

// Apply item effects (e.g., update user stats)
function apply_item_effects($pdo, $userId, $itemId, $action) {
    $stmt = $pdo->prepare("SELECT effect_type, effect_value FROM items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $stmt = $pdo->prepare("SELECT * FROM user_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userStats) {
            throw new Exception("User stats not found.");
        }

        switch ($item['effect_type']) {
            case 'hp':
                if ($action === 'consume') {
                    $newHp = min(100, $userStats['hp'] + $item['effect_value']);
                    $stmt = $pdo->prepare("UPDATE user_stats SET hp = ? WHERE user_id = ?");
                    $stmt->execute([$newHp, $userId]);
                }
                break;
            case 'attack':
                if ($action === 'equip') {
                    $newAttack = $userStats['attack'] + $item['effect_value'];
                    $stmt = $pdo->prepare("UPDATE user_stats SET attack = ? WHERE user_id = ?");
                    $stmt->execute([$newAttack, $userId]);
                }
                break;
            case 'defense':
                if ($action === 'equip') {
                    $newDefense = $userStats['defense'] + $item['effect_value'];
                    $stmt = $pdo->prepare("UPDATE user_stats SET defense = ? WHERE user_id = ?");
                    $stmt->execute([$newDefense, $userId]);
                }
                break;
        }
    }
}

// Remove item effects (e.g., undo stat changes)
function remove_item_effects($pdo, $userId, $itemId, $action) {
    $stmt = $pdo->prepare("SELECT effect_type, effect_value FROM items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        $stmt = $pdo->prepare("SELECT * FROM user_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userStats) {
            throw new Exception("User stats not found.");
        }

        switch ($item['effect_type']) {
            case 'attack':
                if ($action === 'unequip') {
                    $newAttack = $userStats['attack'] - $item['effect_value'];
                    $stmt = $pdo->prepare("UPDATE user_stats SET attack = ? WHERE user_id = ?");
                    $stmt->execute([$newAttack, $userId]);
                }
                break;
            case 'defense':
                if ($action === 'unequip') {
                    $newDefense = $userStats['defense'] - $item['effect_value'];
                    $stmt = $pdo->prepare("UPDATE user_stats SET defense = ? WHERE user_id = ?");
                    $stmt->execute([$newDefense, $userId]);
                }
                break;
        }
    }
}

// Function to initialize user stats for all users
function initialize_user_stats($pdo) {
    // Get all user IDs
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $userId = $user['id'];

        // Check if user stats already exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_stats WHERE user_id = ?");
        $stmt->execute([$userId]);
        $count = $stmt->fetchColumn();

        if ($count == 0) {
            // Insert default user stats
            $stmt = $pdo->prepare("INSERT INTO user_stats (user_id, hp, attack, defense) VALUES (?, 100, 10, 10)");
            $stmt->execute([$userId]);
        }
    }
}
?>
