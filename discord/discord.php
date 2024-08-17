<?php
session_start(); // Ensure the session is started

require_once '../htdocs/includes/db.php'; // Adjust the path as needed

// Function to install or update the plugin's database schema
function install_discord_plugin_schema($pdo) {
    // Check if the plugin settings table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'plugin_settings'");
    if ($stmt->rowCount() === 0) {
        // Table doesn't exist, create it
        $pdo->exec("
            CREATE TABLE plugin_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_name VARCHAR(255) NOT NULL,
                setting_value TEXT NOT NULL,
                plugin_directory VARCHAR(255) NOT NULL,
                UNIQUE KEY (setting_name, plugin_directory)
            )
        ");
    }
}

// Get the logged-in username from the session
$username = $_SESSION['username'];

// Fetch the user role from the database
$stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if the user exists and is an admin
if (!$user || $user['role'] !== 'admin') {
    // Redirect to the index page if the user is not an admin
    header("Location: index.php");
    exit;
}

// Function to get a plugin setting value
function get_discord_plugin_setting($pdo, $settingName) {
    $stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE setting_name = ? AND plugin_directory = 'discord'");
    $stmt->execute([$settingName]);
    return $stmt->fetchColumn();
}

// Function to update a plugin setting value
function update_discord_plugin_setting($pdo, $settingName, $settingValue) {
    $stmt = $pdo->prepare("
        INSERT INTO plugin_settings (setting_name, setting_value, plugin_directory)
        VALUES (?, ?, 'discord')
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$settingName, $settingValue]);
}

// Function to send a message to a Discord webhook
function send_discord_webhook($webhookUrl, $message) {
    $data = ["content" => $message];
    $jsonData = json_encode($data);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Function to send a message via a Discord bot
function send_discord_bot_message($botToken, $channelId, $message) {
    $data = [
        "content" => $message,
        "tts" => false,
    ];
    $jsonData = json_encode($data);

    $url = "https://discord.com/api/v10/channels/{$channelId}/messages";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bot ' . $botToken
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Function to get bot information
function get_discord_bot_info($botToken) {
    $url = "https://discord.com/api/v10/users/@me";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bot ' . $botToken
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Function to display the plugin settings and content
function display_plugin_content($pdo) {
    install_discord_plugin_schema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $webhookUrl = $_POST['discord_webhook_url'] ?? '';
        $botToken = $_POST['discord_bot_token'] ?? '';
        $channelId = $_POST['discord_channel_id'] ?? '';
        $customMessage = $_POST['discord_custom_message'] ?? 'Default message from Discord Integration Plugin';

        if (!empty($webhookUrl)) {
            update_discord_plugin_setting($pdo, 'discord_webhook_url', $webhookUrl);
        }
        if (!empty($botToken)) {
            update_discord_plugin_setting($pdo, 'discord_bot_token', $botToken);
        }
        if (!empty($channelId)) {
            update_discord_plugin_setting($pdo, 'discord_channel_id', $channelId);
        }
        if (!empty($customMessage)) {
            update_discord_plugin_setting($pdo, 'discord_custom_message', $customMessage);
        }

        echo "<div class='alert alert-success'>Discord settings updated successfully.</div>";

        if (isset($_POST['test_webhook']) && !empty($webhookUrl)) {
            $testResponse = send_discord_webhook($webhookUrl, $customMessage);
            echo "<div class='alert alert-info'>Webhook Test Response: " . htmlspecialchars($testResponse) . "</div>";
        }
    }

    $webhookUrl = get_discord_plugin_setting($pdo, 'discord_webhook_url');
    $botToken = get_discord_plugin_setting($pdo, 'discord_bot_token');
    $channelId = get_discord_plugin_setting($pdo, 'discord_channel_id');
    $customMessage = get_discord_plugin_setting($pdo, 'discord_custom_message');

    $botInfo = null;
    if (!empty($botToken)) {
        $botInfo = get_discord_bot_info($botToken);
    }

    ?>
    <div class="container mt-4">
        <h2>Discord Integration Settings</h2>
        <form action="" method="POST">
            <div class="form-group mb-3">
                <label for="discord_webhook_url" class="form-label">Discord Webhook URL</label>
                <input type="url" name="discord_webhook_url" class="form-control" id="discord_webhook_url" value="<?php echo htmlspecialchars($webhookUrl); ?>" required>
                <small class="form-text text-muted">Enter your Discord webhook URL here.</small>
            </div>
            <div class="form-group mb-3">
                <label for="discord_bot_token" class="form-label">Discord Bot Token</label>
                <input type="text" name="discord_bot_token" class="form-control" id="discord_bot_token" value="<?php echo htmlspecialchars($botToken); ?>">
                <small class="form-text text-muted">Enter your Discord bot token here.</small>
            </div>
            <div class="form-group mb-3">
                <label for="discord_channel_id" class="form-label">Discord Channel ID</label>
                <input type="text" name="discord_channel_id" class="form-control" id="discord_channel_id" value="<?php echo htmlspecialchars($channelId); ?>">
                <small class="form-text text-muted">Enter the Discord channel ID where the bot will send messages.</small>
            </div>
            <div class="form-group mb-3">
                <label for="discord_custom_message" class="form-label">Custom Webhook Message</label>
                <input type="text" name="discord_custom_message" class="form-control" id="discord_custom_message" value="<?php echo htmlspecialchars($customMessage); ?>">
                <small class="form-text text-muted">Enter a custom message to send via the webhook.</small>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
            <button type="submit" name="test_webhook" class="btn btn-secondary">Test Webhook</button>
        </form>

        <?php if ($botInfo): ?>
            <div class="mt-4">
                <h3>Bot Information</h3>
                <p><strong>Bot Username:</strong> <?php echo htmlspecialchars($botInfo['username']); ?></p>
                <p><strong>Bot ID:</strong> <?php echo htmlspecialchars($botInfo['id']); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Example function to send a notification when an action occurs
function notify_discord_on_action($pdo, $actionDescription) {
    $webhookUrl = get_discord_plugin_setting($pdo, 'discord_webhook_url');
    $botToken = get_discord_plugin_setting($pdo, 'discord_bot_token');
    $channelId = get_discord_plugin_setting($pdo, 'discord_channel_id');
    $customMessage = get_discord_plugin_setting($pdo, 'discord_custom_message');

    $message = $customMessage . "\n" . "An action occurred: $actionDescription";

    if (!empty($webhookUrl)) {
        send_discord_webhook($webhookUrl, $message);
    }

    if (!empty($botToken) && !empty($channelId)) {
        send_discord_bot_message($botToken, $channelId, $message);
    }
}

?>
