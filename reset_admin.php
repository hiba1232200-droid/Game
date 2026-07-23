<?php
/**
 * ============================================================
 * استرجاع حساب الأدمن — للاستخدام مرة واحدة فقط
 * ============================================================
 * الاستعمال:
 *   /reset_admin.php?key=8OITRMzxq9tluNxUrG6GCKmMHel_ozkJ
 *
 * الحمايات:
 *  1) لا يعمل بدون المفتاح السري أعلاه.
 *  2) يتوقّف نهائياً بعد أول استخدام ناجح (علامة في القاعدة).
 *  3) يُرسل إشعار تلجرام فور استخدامه.
 *
 * ⚠️ احذف هذا الملف من الموقع فور انتهائك منه.
 * ============================================================
 */

require_once __DIR__ . '/db.php';

const RESET_KEY  = '8OITRMzxq9tluNxUrG6GCKmMHel_ozkJ';
const USED_FLAG  = 'admin_reset_used';

header('Content-Type: text/html; charset=UTF-8');

// --- 1) التحقق من المفتاح ---
if (($_GET['key'] ?? '') !== RESET_KEY) {
    http_response_code(404);
    exit('Not Found');
}

// --- 2) هل استُخدم سابقاً؟ ---
if (setting(USED_FLAG, '') === '1') {
    exit('<div style="font:16px system-ui;padding:24px;direction:rtl">
            ⛔ هذا الملف استُخدم من قبل ولم يعد يعمل.<br><br>
            إذا احتجت استرجاعاً جديداً، غيّر قيمة <b>RESET_KEY</b> داخل الملف،
            واحذف الإعداد <b>admin_reset_used</b> من قاعدة البيانات.
          </div>');
}

$msg = '';
$done = false;

// --- 3) تنفيذ الاسترجاع ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['pass'] ?? '';
    $conf  = $_POST['conf'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '❌ البريد غير صالح';
    } elseif (strlen($pass) < 8) {
        $msg = '❌ كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    } elseif ($pass !== $conf) {
        $msg = '❌ تأكيد كلمة المرور لا يطابق';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = db()->prepare("SELECT id FROM users WHERE email=?");
            $st->execute([$email]);
            $id = $st->fetchColumn();

            if ($id) {
                db()->prepare("UPDATE users SET role='admin', password=?, banned=0 WHERE id=?")
                    ->execute([$hash, $id]);
                $msg = '✅ تم تحديث الحساب وترقيته إلى أدمن';
            } else {
                db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')")
                    ->execute(['الأدمن', $email, $hash]);
                $msg = '✅ تم إنشاء حساب أدمن جديد';
            }

            set_setting(USED_FLAG, '1'); // إيقاف الملف نهائياً
            $done = true;

            try {
                notify_admin("🔑 <b>تم استرجاع حساب الأدمن</b>\nالبريد: " . e($email)
                           . "\nIP: " . (function_exists('client_ip') ? client_ip() : '-'));
            } catch (Exception $e) {}
        } catch (Exception $e) {
            $msg = '❌ خطأ: ' . e($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>استرجاع حساب الأدمن</title>
<style>
  body{font-family:system-ui,'Segoe UI',sans-serif;background:#0b0e1a;color:#eef0ff;
       display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px}
  .box{background:#181d3a;border:1px solid #262d55;border-radius:16px;padding:26px;max-width:420px;width:100%}
  h2{margin:0 0 6px;font-size:20px}
  p.hint{color:#9aa2c7;font-size:14px;line-height:1.8;margin:0 0 18px}
  label{display:block;font-size:13px;color:#9aa2c7;margin-bottom:5px}
  input{width:100%;padding:11px;border-radius:10px;border:1px solid #262d55;
        background:#0f1329;color:#fff;margin-bottom:12px;font-size:15px;box-sizing:border-box}
  button{width:100%;padding:13px;border:0;border-radius:10px;font-weight:800;font-size:16px;
         background:linear-gradient(135deg,#7c5cff,#22e0ff);color:#fff;cursor:pointer}
  .msg{padding:12px;border-radius:10px;margin-bottom:14px;font-weight:700;font-size:15px}
  .ok{background:rgba(47,208,114,.15);border:1px solid #2fd072}
  .err{background:rgba(255,93,108,.15);border:1px solid #ff5d6c}
  .warn{background:rgba(255,185,56,.12);border:1px solid #ffb938;color:#ffb938;
        padding:12px;border-radius:10px;font-size:14px;line-height:1.7;margin-top:16px}
</style>
</head>
<body>
<div class="box">
  <h2>🔑 استرجاع حساب الأدمن</h2>
  <p class="hint">أدخل بريداً وكلمة مرور جديدين. إن كان البريد موجوداً فسيُرقّى إلى أدمن، وإلا سيُنشأ حساب أدمن جديد.</p>

  <?php if ($msg): ?>
    <div class="msg <?= $done ? 'ok' : 'err' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <?php if ($done): ?>
    <p class="hint">سجّل الدخول الآن من صفحة الدخول بالبيانات التي أدخلتها.</p>
    <a href="/auth.php"><button type="button">الذهاب لتسجيل الدخول</button></a>
    <div class="warn">⚠️ مهم جداً: احذف الملف <b>reset_admin.php</b> من موقعك الآن.
      الملف توقّف عن العمل تلقائياً، لكن حذفه هو الأأمن.</div>
  <?php else: ?>
    <form method="post">
      <label>البريد الإلكتروني</label>
      <input name="email" type="email" required placeholder="admin@luxecard.store">
      <label>كلمة المرور الجديدة (8 أحرف على الأقل)</label>
      <input name="pass" type="password" required autocomplete="new-password">
      <label>تأكيد كلمة المرور</label>
      <input name="conf" type="password" required autocomplete="new-password">
      <button type="submit">إنشاء / استرجاع الأدمن</button>
    </form>
    <div class="warn">هذا الملف يعمل <b>مرة واحدة فقط</b>، ثم يتوقّف نهائياً.
      احذفه من الموقع بعد الانتهاء.</div>
  <?php endif; ?>
</div>
</body>
</html>
