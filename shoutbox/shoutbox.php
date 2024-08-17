<?php
require_once '../htdocs/includes/db.php'; // Adjust the path as needed

// Function to install or update the plugin's database schema
function install_plugin_schema($pdo) {
    // Check if the schema has already been installed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'shoutbox'");
    $stmt->execute();
    $tableExists = (bool) $stmt->fetchColumn();

    // If the table already exists, don't run the schema installation
    if ($tableExists) {
        return;
    }

    // Create the shoutbox table if it doesn't exist
    $sql = "
    CREATE TABLE IF NOT EXISTS shoutbox (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_name VARCHAR(255) NOT NULL,
        avatar_url VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    $pdo->exec($sql);

    // Array of settings to install
    $settings = [
        ['chat_delay', '5', 'shoutbox'], // Chat delay setting
        ['max_messages', '5', 'shoutbox'], // Max messages to display
        ['enable_emojis', '1', 'shoutbox'], // Enable emojis (1 = true, 0 = false)
        ['max_message_length', '200', 'shoutbox'], // Max length of a single message
        ['shoutbox_tables', 'shoutbox', 'shoutbox'] // DONT TOUCH
        // Add more settings as needed
    ];

    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO plugin_settings (setting_name, setting_value, plugin_directory) VALUES (?, ?, ?)");
        $stmt->execute($setting);
    }
}

// Function to get the chat delay setting
function get_chat_delay($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE setting_name = 'chat_delay' AND plugin_directory = 'shoutbox'");
    $stmt->execute();
    return (int) $stmt->fetchColumn() ?: 5; // Default to 5 seconds if not set
}

// Function to get the current user's details
function get_current_user_details($pdo) {
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Function to display the plugin content
function display_plugin_content($pdo) {
    install_plugin_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $userDetails = get_current_user_details($pdo);
        $playerName = $userDetails['username'];
        $avatarUrl = $userDetails['avatar'];
        $message = $_POST['message'];

        $stmt = $pdo->prepare("INSERT INTO shoutbox (player_name, avatar_url, message) VALUES (?, ?, ?)");
        $stmt->execute([$playerName, $avatarUrl, $message]);
    }

    $chatDelay = get_chat_delay($pdo);

    // Fetch only the most recent 5 messages
    $stmt = $pdo->prepare("SELECT * FROM shoutbox ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $messages = $stmt->fetchAll();

    ?>
    <style>
        .shoutbox-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #292102; /* Dark brown */
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .shoutbox-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .shoutbox-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #f9d857; /* Light yellow */
        }
        .shoutbox-messages {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column-reverse; /* New messages appear at the bottom */
        }
        .shoutbox-message {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #b0afad; /* Light grey */
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        .shoutbox-message img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .shoutbox-message-content {
            flex: 1;
        }
        .shoutbox-message-content strong {
            display: block;
            color: #f9d857; /* Light yellow */
        }
        .shoutbox-message-content small {
            display: block;
            color: #5f5633; /* Dark olive */
            font-size: 0.875rem;
        }
        .shoutbox-form {
            display: flex;
            flex-direction: column;
        }
        .shoutbox-form textarea {
            resize: none;
            height: 80px;
            background-color: #b0afad; /* Light grey */
            color: #292102; /* Dark brown */
            border: 1px solid #5f5633; /* Dark olive */
        }
        .shoutbox-form button {
            align-self: flex-end;
            background-color: #f9d857; /* Light yellow */
            color: #292102; /* Dark brown */
            border: none;
        }
        .shoutbox-form button:hover {
            background-color: #e0c74d; /* Slightly darker light yellow */
        }
    </style>

    <div class="shoutbox-container">
        <div class="shoutbox-header">
            <h2>Shoutbox</h2>
        </div>

        <!-- Form to send a new message -->
        <form action="" method="post" class="shoutbox-form">
            <input type="hidden" name="action" value="send_message">
            <div class="mb-3">
                <textarea id="message" name="message" class="form-control" placeholder="Type your message here..." required></textarea>
            </div><br />
            <button type="submit" class="btn btn-primary">Send</button><br />
        </form>

        <!-- Chat messages -->
        <div id="shoutbox-messages" class="shoutbox-messages">
            <?php foreach ($messages as $msg): ?>
                <div class="shoutbox-message">
                    <img src="../uploads/avatars/<?php echo htmlspecialchars($msg['avatar_url']); ?>" alt="Avatar">
                    <div class="shoutbox-message-content">
                        <strong><?php echo htmlspecialchars($msg['player_name']); ?></strong>
                        <small><?php echo htmlspecialchars($msg['created_at']); ?></small>
                        <p><?php echo htmlspecialchars($msg['message']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function fetchChatMessages() {
            fetch('fetch_messages.php')
                .then(response => response.json())
                .then(data => {
                    const messageContainer = document.getElementById('shoutbox-messages');
                    messageContainer.innerHTML = '';

                    data.forEach(msg => {
                        const messageElement = document.createElement('div');
                        messageElement.className = 'shoutbox-message';
                        messageElement.innerHTML = `
                            <img src="../uploads/avatars/${msg.avatar_url}" alt="Avatar">
                            <div class="shoutbox-message-content">
                                <strong>${msg.player_name}</strong>
                                <small>${msg.created_at}</small>
                                <p>${msg.message}</p>
                            </div>
                        `;
                        messageContainer.appendChild(messageElement);
                    });

                    // Scroll to the bottom to show the latest messages
                    messageContainer.scrollTop = messageContainer.scrollHeight;
                });
        }

        const chatDelay = <?php echo json_encode(get_chat_delay($pdo)); ?> * 1000;
        setInterval(fetchChatMessages, chatDelay);
    </script>
    <?php
}
