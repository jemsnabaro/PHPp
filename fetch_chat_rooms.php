<?php
session_start();
require 'db.php';

if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get logged-in user ID
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$user_id = $user['id'];

// Fetch chat rooms where user is part of the match
$sql = "
    SELECT 
        cr.id AS chat_room_id,
        cr.match_id,
        u2.id AS other_user_id,
        u2.email AS other_user_email,
        p2.profile_image,
        cr.created_at
    FROM chat_rooms cr
    JOIN matches m ON cr.match_id = m.id
    JOIN users u2 ON m.user2_id = u2.id
    LEFT JOIN profiles p2 ON p2.user_id = u2.id
    WHERE m.user1_id = ?

    UNION ALL

    SELECT 
        cr.id AS chat_room_id,
        cr.match_id,
        u1.id AS other_user_id,
        u1.email AS other_user_email,
        p1.profile_image,
        cr.created_at
    FROM chat_rooms cr
    JOIN matches m ON cr.match_id = m.id
    JOIN users u1 ON m.user1_id = u1.id
    LEFT JOIN profiles p1 ON p1.user_id = u1.id
    WHERE m.user2_id = ?

    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load conversations']);
    exit();
}

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$rooms = [];
while ($row = $result->fetch_assoc()) {
    $rooms[] = $row;
}

header('Content-Type: application/json');
echo json_encode($rooms);
?>
