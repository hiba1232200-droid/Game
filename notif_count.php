<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
@start_session_once();
$uid = $_SESSION['uid'] ?? null;
if (!$uid) { echo json_encode(['count' => 0]); exit; }

$st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$st->execute([$uid]);
$count = (int)$st->fetchColumn();

// أحدث إشعار (للعرض كإشعار متصفح)
$latest = null;
$st = db()->prepare("SELECT id, title, body, icon FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $latest = [
        'id' => (int)$row['id'],
        'title' => ($row['icon'] ?? '🔔') . ' ' . $row['title'],
        'body' => $row['body'] ?? '',
    ];
}

echo json_encode(['count' => $count, 'latest' => $latest], JSON_UNESCAPED_UNICODE);
