<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$U = current_user();
if (!$U) { echo json_encode(['ok' => false, 'login' => true]); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = (string)($_GET['action'] ?? ($in['action'] ?? 'fetch'));

// open: بداية محادثة جديدة — نحذف المحادثة القديمة (تنحذف لما يطلع ويرجع يفوت)
if ($action === 'open') {
    db()->prepare("DELETE FROM support_messages WHERE user_id=?")->execute([$U['id']]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'send') {
    $body = trim((string)($in['message'] ?? ''));
    if ($body === '') { echo json_encode(['ok' => false, 'msg' => 'اكتب رسالتك']); exit; }
    db()->prepare("INSERT INTO support_messages (user_id,sender,body,read_user,read_admin) VALUES (?, 'user', ?, 1, 0)")
        ->execute([$U['id'], mb_substr($body, 0, 1000)]);
    notify_admin("🎧 <b>رسالة دعم</b>\nالاسم: " . e($U['name']) . " (#" . $U['id'] . ")\n─────────\n" . e(mb_substr($body, 0, 300)) . "\n\n↩️ ردّ (Reply) على هالرسالة للرد على الزبون");
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// fetch: الرسائل بعد آخر id معروف (للتحديث الحي)
$after = (int)($_GET['after'] ?? ($in['after'] ?? 0));
$st = db()->prepare("SELECT id, sender, body, created_at FROM support_messages WHERE user_id=? AND id > ? ORDER BY id ASC");
$st->execute([$U['id'], $after]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// علّم رسائل الأدمن كمقروءة من المستخدم
if ($rows) {
    db()->prepare("UPDATE support_messages SET read_user=1 WHERE user_id=? AND sender='admin'")->execute([$U['id']]);
}

echo json_encode(['ok' => true, 'messages' => $rows], JSON_UNESCAPED_UNICODE);
