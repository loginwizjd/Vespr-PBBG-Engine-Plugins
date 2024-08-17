<?php
require_once '../htdocs/includes/db.php'; // Adjust the path as needed

function install_plugin_schema($pdo) {
    // Check if the schema has already been installed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'YOUR PLUGIN NAME'");
    $stmt->execute();
    $tableExists = (bool) $stmt->fetchColumn();

    // If the table already exists, don't run the schema installation
    if ($tableExists) {
        return;
    }

    // Create the table if it doesn't exist
    $sql = "
    CREATE TABLE IF NOT EXISTS YOURTABLE (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player VARCHAR(255) NOT NULL,
    );
    ";
    $pdo->exec($sql);

    // Array of settings to install
    $settings = [
        ['YOURADDON_SETTING_NAME', 'YOURADDON_SETTING', 'YOURADDON'], // REQUIRED FOR SETTINGS IN PLUGIN MANAGER

        ['YOURADDON_tables', 'YOURADDON', 'YOURADDON'] //Make sure same as directory name
        // Add more settings as needed
    ];

    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO plugin_settings (setting_name, setting_value, plugin_directory) VALUES (?, ?, ?)");
        $stmt->execute($setting);
    }
}

// Function to fetch user details
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
    $userDetails = get_current_user_details($pdo);

    //PLUGIN CONTENT

}
?>
