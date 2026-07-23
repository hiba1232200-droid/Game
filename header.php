<?php
require_once __DIR__ . '/db.php';
$U = current_user();

// وضع الصيانة: يظهر للزوّار فقط. يتجاوزها: (1) الأدمن المسجّل، (2) من معه مفتاح التجاوز بالجلسة، (3) صفحة الدخول.
$__reqPath = ($_SERVER['SCRIPT_NAME'] ?? '') . ' ' . ($_SERVER['PHP_SELF'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
$__isAuthPage = (strpos($__reqPath, 'auth') !== false || strpos($__reqPath, 'google_login') !== false);
$__isAdmin = ($U && ($U['role'] ?? '') === 'admin');
$__hasBypass = !empty($_SESSION['maint_bypass']);
if (maintenance_on() && !$__isAdmin && !$__hasBypass && !$__isAuthPage) {
    http_response_code(503);
    header('Retry-After: 3600');
    $mMsg = maintenance_msg();
    ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(STORE_NAME) ?> | صيانة</title>
<link rel="stylesheet" href="/style.css?v=20">
<style>
  .maint-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center}
  .maint-box{max-width:440px}
  .maint-ico{font-size:64px;margin-bottom:16px}
  .maint-box h1{font-size:26px;margin:0 0 12px}
  .maint-box p{color:var(--muted,#9aa);font-size:17px;line-height:1.8;margin:0 0 20px}
  .maint-store{color:var(--accent,#d4af37);font-weight:700;font-size:20px;margin-bottom:24px}
</style>
</head>
<body>
  <div class="maint-wrap"><div class="maint-box">
    <div class="maint-ico">🔧</div>
    <div class="maint-store"><?= e(STORE_NAME) ?></div>
    <h1>قيد الصيانة</h1>
    <p><?= e($mMsg) ?></p>
  </div></div>
</body>
</html><?php
    exit;
}
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(STORE_NAME) ?> | <?= e($pageTitle ?? 'الرئيسية') ?></title>
<meta name="description" content="<?= e(STORE_NAME . ' - ' . STORE_TAGLINE) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/style.css?v=20">
<link rel="stylesheet" href="/cyber-theme.css?v=7">
<!-- الخط يحمّل بدون حجب الصفحة (أسرع ظهور) -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet"></noscript>
<style>:root{--accent:<?= e(theme_accent()) ?>;--accent2:<?= e(theme_accent2()) ?>}</style>
</head>
<body>
<div id="cyberLoader" aria-hidden="true">
  <div class="cy-ring"></div>
  <div class="cy-load-name">LUXE CARD</div>
</div>
<div class="cyber-aurora" aria-hidden="true"></div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<?php
// نافذة الواتساب: تظهر عند فتح الموقع، وتختفي أثناء التنقّل الداخلي.
// المنطق الفعلي بالـ JavaScript (sessionStorage) لأنه الأدق لهذا السلوك.
$__showWaPopup = (wa_popup_on() && wa_popup_link());
?>
<?php if ($__showWaPopup): ?>
<div class="wa-popup-overlay" id="waPopup" style="position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--card,#141824);max-width:400px;width:100%;border-radius:18px;padding:26px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5)">
    <div style="font-size:52px;margin-bottom:10px">📢</div>
    <h2 style="margin:0 0 8px;font-size:22px">خلّيك في قلب الحدث!</h2>
    <p style="color:var(--muted,#9aa);font-size:16px;line-height:1.9;margin:0 0 22px"><?= e(wa_popup_text()) ?></p>
    <a href="<?= e(wa_popup_link()) ?>" target="_blank" rel="noopener"
       style="display:block;background:#25D366;color:#fff;font-weight:700;padding:14px;border-radius:12px;text-decoration:none;font-size:17px;margin-bottom:10px">
       💬 انضم إلى القناة
    </a>
    <button onclick="document.getElementById('waPopup').style.display='none'"
       style="display:block;width:100%;background:transparent;color:var(--muted,#9aa);border:1px solid var(--border,#2a2f3e);padding:12px;border-radius:12px;font-size:16px;cursor:pointer">
       إغلاق
    </button>
  </div>
</div>
<script>
(function(){
  try {
    // إذا ما في علامة بهذا التبويب => زيارة جديدة => أظهر النافذة
    if (!sessionStorage.getItem('waSeen')) {
      var p = document.getElementById('waPopup');
      if (p) p.style.display = 'flex';
      sessionStorage.setItem('waSeen', '1');
    }
  } catch(e) {
    // لو sessionStorage غير متاح، أظهرها دائماً
    var p2 = document.getElementById('waPopup');
    if (p2) p2.style.display = 'flex';
  }
})();
</script>
<?php endif; ?>

<aside class="sidebar" id="sidebar">
  <div class="sb-head">
    <div class="logo-txt"><img src="/logo.svg?v=1" class="logo-img" alt=""><span class="logo-name"><?= e(STORE_NAME) ?></span></div>
  </div>
  <?php if ($U): ?>
    <div class="sb-user">
      <div class="sb-name">👤 <?= e($U['name']) ?></div>
      <div class="sb-balance">المحفظة: <b class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</b></div>
      <a class="btn btn-sm" href="/wallet.php">شحن المحفظة</a>
    </div>
  <?php else: ?>
    <div class="sb-user">
      <div class="sb-name">تسجيل الدخول</div>
      <p class="muted">سجّل دخول أو أنشئ حساب جديد للمتابعة</p>
      <a class="btn btn-sm" href="/auth.php">تسجيل الدخول</a>
    </div>
  <?php endif; ?>
  <nav class="sb-nav">
    <a href="/index.php">🏠 الرئيسية</a>
    <a href="/index.php?page=search">🔍 بحث عن منتج</a>
    <a href="/assistant.php">🤖 المساعد الذكي</a>
    <?php if ($U): ?>
      <a href="/account.php">👤 حسابي</a>
      <a href="/orders.php">🧾 طلباتي</a>
      <a href="/notifications.php">🔔 الإشعارات</a>
      <a href="/index.php?page=favs">❤ المفضلة</a>
      <a href="/index.php?page=cart">🛒 السلة</a>
      <a href="/index.php?page=wheel">🎡 عجلة الحظ</a>
      <a href="/wallet.php">💳 المحفظة</a>
      <a href="/coupon.php">🎁 كود الخصم</a>
      <?php if ($U['role'] === 'admin'): ?><a href="/admin.php">🛠 لوحة الأدمن</a><?php endif; ?>
      <a href="/auth.php?logout=1">🚪 تسجيل الخروج</a>
    <?php endif; ?>
    <a href="/index.php?page=about">ℹ️ من نحن</a>
    <a href="/contact.php">📞 تواصل معنا</a>
    <a href="/faq.php">❓ الأسئلة الشائعة</a>
    <a href="/index.php?page=terms">📄 سياسة الاسترجاع</a>
  </nav>
  <button class="theme-toggle" onclick="toggleTheme()">🌙 / ☀️ تبديل الوضع</button>
  <button class="theme-toggle" onclick="toggleCurrency()">💱 العملة: <?= display_currency() === 'usd' ? 'دولار $' : 'ليرة ل.س' ?></button>
  <div class="sb-social">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</aside>

<header class="topbar">
  <button class="burger" onclick="toggleSidebar()">☰</button>
  <?php
    $_uri = $_SERVER['REQUEST_URI'] ?? '';
    $_isHome = ($_uri === '/' || $_uri === '/index.php' || strpos($_uri, '/index.php?page=home') === 0
                || (strpos($_uri, '/index.php') === 0 && strpos($_uri, 'page=') === false && strpos($_uri, 'cat=') === false));
  ?>
  <?php if (!$_isHome): ?><button class="back-btn" onclick="goBack()" title="رجوع">‹</button><?php endif; ?>
  <a class="logo-txt" href="/index.php"><img src="/logo.svg?v=1" class="logo-img" alt=""><span class="logo-name"><?= e(STORE_NAME) ?></span></a>
  <div class="top-actions">
    <a class="icon-btn" href="/index.php?page=search" title="بحث">🔍</a>
    <?php if ($U): ?>
      <a class="icon-btn cart-icon" href="/index.php?page=cart" title="السلة">🛒<span class="cart-badge" id="cartBadge" style="display:none">0</span></a>
      <a class="icon-btn notif-bell" href="/notifications.php" title="الإشعارات">🔔<span class="notif-badge" id="notifBadge" style="display:none">0</span></a>
      <a class="balance-pill" href="/wallet.php">💳 <span class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</span></a>
    <?php else: ?>
      <a class="btn btn-sm" href="/auth.php">دخول</a>
    <?php endif; ?>
  </div>
</header>
<script>const USD_RATE = <?= usd_rate() ?>; const CUR = '<?= display_currency() ?>';</script>
<?php
// خصومات الأسعار الدائمة المفعّلة للمستخدم (لإظهارها تلقائياً على المنتجات)
$myDiscounts = [];
if ($U) {
    $st = db()->prepare("SELECT ud.player_id AS player_id, c.type AS type, c.amount AS amount
        FROM user_discounts ud JOIN coupons c ON c.id = ud.coupon_id
        WHERE ud.user_id=? AND ud.status='active' AND c.active=1 ORDER BY ud.id DESC");
    $st->execute([$U['id']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $myDiscounts[] = ['player_id' => (string)$r['player_id'], 'type' => $r['type'], 'amount' => (float)$r['amount']];
    }
}
?>
<script>const MY_DISCOUNTS = <?= json_encode($myDiscounts, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>const PROMO_DISCOUNT_PCT = <?= json_encode(promo_discount_pct()) ?>;</script>
<main class="container">
