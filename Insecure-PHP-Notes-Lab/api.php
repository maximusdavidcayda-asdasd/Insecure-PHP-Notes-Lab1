<?php
session_start();
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

// 1. Require authentication
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];


$db = new PDO(
    'mysql:host=localhost;dbname=notes_lab;charset=utf8mb4',
    getenv('LAB_DB_USER') ?: 'root',
    getenv('LAB_DB_PASSWORD') ?: ''
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ?');
    $stmt->execute([$loggedInUserId]);
    echo json_encode($stmt->fetchAll());
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id']) || !isset($data['title']) || !isset($data['body'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }


    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$data['title'], $data['body'], $data['id'], $loggedInUserId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Note not found or permission denied']);
        exit;
    }

    echo json_encode(['updated' => $stmt->rowCount()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not supported']);