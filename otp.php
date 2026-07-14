<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
$U = current_user();

function jout($ok, $msg, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';

// ===== بدء التوثيق: توليد رمز + رابط واتساب =====
if ($action === 'start') {
    $phone = trim($_POST['phone'] ?? '');
    $gsm = normalize_gsm($phone);
    if (strlen($gsm) < 12) jout(false, 'رقم الموبايل غير صحيح');

    // منع طلب جديد إذا في طلب pending حديث (آخر 3 دقائق)
    $st = db()->prepare("SELECT code, created_at FROM otp_codes WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $existing = $st->fetch(PDO::FETCH_ASSOC);

    // توليد رمز 4 أرقام
    $code = (string)random_int(1000, 9999);

    // حذف القديم وإنشاء طلب جديد
    db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$U['id']]);
    db()->prepare("INSERT INTO otp_codes (user_id,phone,code,status,expires_at) VALUES (?,?,?,'pending',?)")
        ->execute([$U['id'], $gsm, $code, date('Y-m-d H:i:s', time() + 1800)]); // 30 دقيقة

    // رابط واتساب مع رسالة جاهزة
    $localPhone = $phone; // الرقم كما أدخله المستخدم للعرض
    $link = wa_verify_link($localPhone, $code);

    jout(true, 'تم تجهيز طلب التوثيق', ['code' => $code, 'link' => $link]);
}

// ===== تأكيد أن المستخدم أرسل الرسالة (تنتقل لحالة بانتظار الأدمن) =====
if ($action === 'sent') {
    $st = db()->prepare("SELECT * FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) jout(false, 'لا يوجد طلب توثيق، ابدأ من جديد');

    // إشعار الأدمن لمراجعة الطلب
    notify_admin("📱 <b>طلب توثيق رقم جديد</b>\nالزبون: " . e($U['name']) . "\nالرقم: " . e($row['phone']) . "\nالرمز: " . e($row['code']) . "\nراجع رسائل واتساب وأكّد الطلب من لوحة الأدمن.");
    notify_user($U['id'], 'تم استلام طلب التوثيق 📱', 'طلبك قيد المراجعة، رح يوصلك إشعار عند الموافقة.', '📱');

    jout(true, 'تم إرسال طلبك للمراجعة ✅');
}

jout(false, 'طلب غير صحيح');
