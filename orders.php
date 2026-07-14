<?php
require_once __DIR__ . '/order_tracker.php';
require_login();
$U = current_user();

// تحديث حالات الطلبات المعلقة (مع إشعار الأدمن عند التنفيذ)
track_pending_orders($U['id'], 20);

$st = db()->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 50");
$st->execute([$U['id']]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

$labels = ['pending' => ['قيد التنفيذ ⏳', 'st-pending'], 'accept' => ['تم التنفيذ ✅', 'st-ok'], 'reject' => ['مرفوض — أُعيد المبلغ ❌', 'st-no']];

$pageTitle = 'طلباتي';
include __DIR__ . '/header.php'; ?>

<h1 class="section-title">طلباتي</h1>

<?php $vip = user_vip_info($U['id']); ?>
<div class="vip-card vip-<?= $vip['level'] ?>">
  <div class="vip-top">
    <div class="vip-badge-big"><?= vip_badge($vip['level']) ?></div>
    <div class="vip-spent">أنفقت: $<?= number_format($vip['spent_usd'], 2) ?></div>
  </div>
  <?php if ($vip['next_level']): ?>
    <?php $th = vip_thresholds(); $cur = $th[$vip['level']] ?? 0; $target = $th[$vip['next_level']];
      $prog = $target > 0 ? min(100, ($vip['spent_usd'] / $target) * 100) : 0; ?>
    <div class="vip-progress"><div class="vip-progress-bar" style="width:<?= $prog ?>%"></div></div>
    <div class="vip-next">باقي <b>$<?= number_format($vip['need_usd'], 2) ?></b> للوصول إلى <?= vip_badge($vip['next_level']) ?></div>
    <?php if ($vip['level'] >= 2): ?>
      <div class="vip-perk">🎁 تواصل مع الدعم للحصول على كود الخصم الخاص بك</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="vip-next">🏆 وصلت لأعلى مستوى! تواصل مع الدعم لكود الخصم الخاص بك</div>
  <?php endif; ?>
</div>
<?php if (!$orders): ?>
  <p class="empty">ما عندك طلبات بعد — تصفّح <a href="/index.php">الأقسام</a> واطلب أول منتج.</p>
<?php else: ?>
<div class="orders-list">
  <?php foreach ($orders as $o): [$txt, $cls] = $labels[$o['status']] ?? [$o['status'], '']; ?>
    <div class="card order-card">
      <div class="o-head">
        <b>#<?= $o['id'] ?></b>
        <span class="badge <?= $cls ?>"><?= $txt ?></span>
      </div>
      <div class="o-body">
        <div><?= e($o['product_name']) ?> × <?= $o['qty'] ?></div>
        <?php if ($o['player_id']): ?><div class="muted">ID: <?= e($o['player_id']) ?></div><?php endif; ?>

        <?php
          // الخط الزمني لحالة الطلب
          $step = 1; // 1=استلام
          if ($o['status'] === 'pending') $step = 2; // قيد التنفيذ
          elseif ($o['status'] === 'accept') $step = 3; // تم
          elseif ($o['status'] === 'reject') $step = -1; // مرفوض
        ?>
        <?php if ($step > 0): ?>
        <div class="otrack">
          <div class="otrack-step <?= $step >= 1 ? 'done' : '' ?>">
            <div class="otrack-dot">📥</div>
            <div class="otrack-lbl">استُلم</div>
          </div>
          <div class="otrack-line <?= $step >= 2 ? 'done' : '' ?>"></div>
          <div class="otrack-step <?= $step >= 2 ? ($step == 2 ? 'active' : 'done') : '' ?>">
            <div class="otrack-dot"><?= $step == 2 ? '⏳' : '⚙️' ?></div>
            <div class="otrack-lbl">قيد التنفيذ</div>
          </div>
          <div class="otrack-line <?= $step >= 3 ? 'done' : '' ?>"></div>
          <div class="otrack-step <?= $step >= 3 ? 'done' : '' ?>">
            <div class="otrack-dot">✅</div>
            <div class="otrack-lbl">تم</div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($o['status'] === 'pending'): ?>
          <div class="eta-note">⏱ الوقت المتوقع للتنفيذ: من دقيقة إلى 10 دقائق</div>
        <?php endif; ?>
        <?php
          $codes = $o['codes'] ? json_decode($o['codes'], true) : [];
          // تسطيح أي قيم متداخلة وتحويلها لنصوص
          $flatCodes = [];
          if (is_array($codes)) {
              array_walk_recursive($codes, function($v) use (&$flatCodes) {
                  $v = trim((string)$v);
                  if ($v !== '') $flatCodes[] = $v;
              });
          }
          if ($flatCodes): ?>
          <div class="codes-box">
            🎟 الكود:
            <?php foreach ($flatCodes as $c): ?>
              <b class="copyable" onclick="copyText('<?= e($c) ?>')"><?= e($c) ?> 📋</b>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="o-total"><?= number_format($o['total']) ?> ل.س</div>
        <div class="muted small"><?= e($o['created_at']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
  // هل في طلبات معلقة؟ نفعّل التحديث التلقائي
  $hasPending = false;
  foreach ($orders as $o) { if ($o['status'] === 'pending') { $hasPending = true; break; } }
?>
<?php if ($hasPending): ?>
<script>
  // تحديث تلقائي كل 30 ثانية طالما في طلب قيد التنفيذ
  setTimeout(function(){ location.reload(); }, 30000);
</script>
<?php endif; ?>

<?php include __DIR__ . '/footer.php';
