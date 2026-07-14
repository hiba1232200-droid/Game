<?php
require_once __DIR__ . '/db.php';
require_login();
$U = current_user();

// تعليم الكل كمقروء عند فتح الصفحة
db()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$U['id']]);

$st = db()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50");
$st->execute([$U['id']]);
$notifs = $st->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'الإشعارات';
include __DIR__ . '/header.php'; ?>

<div class="notif-page">
  <h1 class="section-title">🔔 الإشعارات</h1>

  <?php if (!$notifs): ?>
    <div class="card"><p class="empty">ما في إشعارات بعد. رح تشوف هون تحديثات طلباتك وإيداعاتك.</p></div>
  <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifs as $n): ?>
        <div class="notif-item">
          <div class="notif-icon"><?= e($n['icon']) ?></div>
          <div class="notif-content">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <?php if ($n['body']): ?><div class="notif-body"><?= e($n['body']) ?></div><?php endif; ?>
            <div class="notif-time"><?= e($n['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php';
