<?php

require_once '../htdocs/includes/db.php'; // Adjust the path as needed

// Function to install plugin settings
function install_plugin_settings($pdo) {
    // Check if the settings have already been inserted
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM plugin_settings WHERE plugin_directory = 'usersonline'");
    $stmt->execute();
    $settingsExist = $stmt->fetchColumn() > 0;

    if (!$settingsExist) {
        $settings = [
            ['user_online_time_span', '60', 'usersonline'], // Time span for considering users as online (default 60 minutes)
            ['refresh_interval', '30', 'usersonline'], // Refresh interval in seconds (default 30 seconds)
            ['display_limit', '10', 'usersonline'], // Display limit for the number of users (default 10)
        ];

        foreach ($settings as $setting) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO plugin_settings (setting_name, setting_value, plugin_directory) VALUES (?, ?, ?)");
            $stmt->execute($setting);
        }
    }
}

// Function to get a plugin setting value
function get_plugin_setting($pdo, $settingName, $pluginDir) {
    $stmt = $pdo->prepare("SELECT setting_value FROM plugin_settings WHERE setting_name = ? AND plugin_directory = ?");
    $stmt->execute([$settingName, $pluginDir]);
    return $stmt->fetchColumn();
}

// Function to fetch online users based on the time span setting
function fetch_online_users($pdo, $timeSpan, $limit) {
    $stmt = $pdo->prepare("
        SELECT u.username, u.avatar, ua.timestamp 
        FROM user_activity ua
        JOIN users u ON ua.user_id = u.id
        WHERE ua.timestamp >= NOW() - INTERVAL :timeSpan MINUTE
        ORDER BY ua.timestamp DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':timeSpan', (int)$timeSpan, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to display the plugin content
function display_plugin_content($pdo) {
    install_plugin_settings($pdo);

    // Get plugin settings
    $timeSpan = get_plugin_setting($pdo, 'user_online_time_span', 'usersonline');
    $refreshInterval = get_plugin_setting($pdo, 'refresh_interval', 'usersonline');
    $displayLimit = get_plugin_setting($pdo, 'display_limit', 'usersonline');

    // Fetch online users
    $onlineUsers = fetch_online_users($pdo, $timeSpan, $displayLimit);

    // Display content
    echo "<div id='users-online' class='users-online-container'>";
    echo "<h2>Users Online (Last $timeSpan minutes)</h2>";
    echo "<ul class='users-online-list'>";
    foreach ($onlineUsers as $user) {
        echo "<li class='user-online-item'>";
        echo "<img src='../uploads/avatars/" . htmlspecialchars($user['avatar']) . "' alt='Avatar' class='user-avatar'>";
        echo "<div class='user-details'>";
        echo "<span class='username'>" . htmlspecialchars($user['username']) . "</span>";
        echo "<span class='last-active'>Last active at " . htmlspecialchars($user['timestamp']) . "</span>";
        echo "</div>";
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";

    // CSS Styling
    echo "
    <style>
        .users-online-container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .users-online-container h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 20px;
        }
        
        .users-online-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .user-online-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .username {
            font-size: 1rem;
            font-weight: bold;
            color: #333;
        }
        
        .last-active {
            font-size: 0.875rem;
            color: #777;
        }
    </style>
    ";

    // JavaScript to refresh the list at the set interval
    echo "
    <script>
        function refreshOnlineUsers() {
            // Fetch the updated online users list
            fetch('fetch_online_users.php')
                .then(response => response.json())
                .then(data => {
                    const usersOnlineDiv = document.getElementById('users-online');
                    let content = '<h2>Users Online (Last $timeSpan minutes)</h2><ul class=\"users-online-list\">';
                    data.forEach(user => {
                        content += '<li class=\"user-online-item\">' +
                                   '<img src=\"../uploads/avatars/' + user.avatar + '\" alt=\"Avatar\" class=\"user-avatar\">' +
                                   '<div class=\"user-details\">' +
                                   '<span class=\"username\">' + user.username + '</span>' +
                                   '<span class=\"last-active\">Last active at ' + user.timestamp + '</span>' +
                                   '</div></li>';
                    });
                    content += '</ul>';
                    usersOnlineDiv.innerHTML = content;
                });
        }

        setInterval(refreshOnlineUsers, $refreshInterval * 1000);
    </script>
    ";
}
?>
