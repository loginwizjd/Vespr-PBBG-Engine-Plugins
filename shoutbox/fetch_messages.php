<?php
require_once '../htdocs/includes/db.php'; // Adjust the path as needed

header('Content-Type: application/json');

// Fetch latest messages
$messages = $pdo->query("SELECT * FROM shoutbox ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Return messages as JSON
echo json_encode($messages);
