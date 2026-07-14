<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
$U = current_user();

function vout($ok, $msg) {
    echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') vout(false, 'طلب غير صحيح');

// إذا موثّق مسبقاً
if (!empty($U['id_verified'])) vout(false, 'حسابك موثّق مسبقاً');

// التأكد من وجود طلب معلّق
$st = db()->prepare("SELECT status FROM id_verifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$U['id']]);
$last = $st->fetchColumn();
if ($last === 'pending') vout(false, 'لديك طلب توثيق قيد المراجعة بالفعل');

// استقبال الصورتين (base64 من المتصفح بعد الضغط)
$img = $_POST['image'] ?? '';
$imgBack = $_POST['image_back'] ?? '';
if (strpos($img, 'data:image/') !== 0) vout(false, 'الرجاء اختيار صورة الوجه الأمامي للهوية');
if (strpos($imgBack, 'data:image/') !== 0) vout(false, 'الرجاء اختيار صورة الوجه الخلفي للهوية');
// حد أقصى للحجم (~2MB لكل صورة بعد الترميز)
if (strlen($img) > 2800000) vout(false, 'حجم الصورة الأمامية كبير، حاول بصورة أصغر');
if (strlen($imgBack) > 2800000) vout(false, 'حجم الصورة الخلفية كبير، حاول بصورة أصغر');

// حذف الطلبات المرفوضة القديمة
db()->prepare("DELETE FROM id_verifications WHERE user_id=? AND status='rejected'")->execute([$U['id']]);
db()->prepare("INSERT INTO id_verifications (user_id,image,image_back,status) VALUES (?,?,?,'pending')")
    ->execute([$U['id'], $img, $imgBack]);

notify_admin("🪪 <b>طلب توثيق هوية جديد</b>\nالزبون: " . e($U['name']) . "\nالإيميل: " . e($U['email']) . "\nراجع الطلب من لوحة الأدمن.");
notify_user($U['id'], 'تم استلام طلب التوثيق 🪪', 'طلبك قيد المراجعة، رح يوصلك إشعار عند الموافقة.', '🪪');

vout(true, 'تم إرسال صورة هويتك للمراجعة ✅');
