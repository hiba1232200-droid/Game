<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $msg, $extra = []) {
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE); exit;
}

$U = current_user();
if (!$U) out(false, 'سجّل دخول أولاً', ['login' => true]);

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$pid    = (string)($in['product_id'] ?? '');
$qty    = max(1, (float)($in['qty'] ?? 1));
$player = trim((string)($in['player_id'] ?? ''));

$p = store_product($pid);
if (!$p) out(false, 'المنتج غير موجود');
if (!$p['available']) out(false, 'المنتج غير متوفر حالياً ❌');

// حدود الكمية من API
if ($qty < $p['qty_min']) out(false, 'أقل كمية مسموحة: ' . $p['qty_min']);
if ($p['qty_max'] > 0 && $qty > $p['qty_max']) out(false, 'أكبر كمية مسموحة: ' . $p['qty_max']);

// منتجات specificPackage: الكمية من قائمة قيم محددة (تأتي من الواجهة كقيمة عشرية)
// نتركها كما هي - لا نثبّتها على 1

// إذا المنتج بيطلب معرف لاعب
if (!empty($p['params']) && $player === '') out(false, 'مطلوب: ' . $p['params'][0]);

// تطبيق خصم العرض بوقت محدود (إن وجد)
$unitPrice = $p['price'];
$disc = promo_discount_pct();
if ($disc > 0) $unitPrice = $p['price'] * (1 - $disc/100);
// نحسب الإجمالي ثم نقرّبه (مهم لمنتجات amount ذات سعر الوحدة العشري)
$total = round($unitPrice * $qty);
if ($total < 1) out(false, 'سعر هذا المنتج غير متوفر حالياً، حاول لاحقاً.');

// خصم مفعّل على حساب المستخدم (من صفحة "كود الخصم") — ينطبق تلقائياً لنفس الـ ID
$couponDisc = 0; $udId = 0; $udCode = '';
$ud = find_active_discount($U['id'], $player);
if ($ud) {
    $couponDisc = discount_value($ud['type'], $ud['amount'], $total);
    if ($couponDisc > 0) {
        $total  = max(1, $total - $couponDisc);
        $udId   = (int)$ud['id'];
        $udCode = (string)$ud['code'];
    }
}

if ($U['balance'] < $total) out(false, 'رصيد محفظتك غير كافٍ — المطلوب ' . number_format($total) . ' ل.س. اشحن محفظتك أولاً.');

// UUIDv4 حسب التوثيق (idempotency)
$b = random_bytes(16);
$b[6] = chr(ord($b[6]) & 0x0f | 0x40); $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));

// خصم الرصيد وحجز الطلب
db()->beginTransaction();
db()->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?")
    ->execute([$total, $U['id'], $total]);
// تكلفة فاست كارد بالليرة (لحساب الربح لاحقاً بدقة، حتى لو تغيّر سعر الصرف)
$costSyp = round((float)$p['cost'] * usd_rate() * $qty);
$st = db()->prepare("INSERT INTO orders (user_id,product_id,product_name,qty,player_id,price,total,uuid,cost_syp)
    VALUES (?,?,?,?,?,?,?,?,?)");
$st->execute([$U['id'], $pid, $p['name'], $qty, $player, $p['price'], $total, $uuid, $costSyp]);
$orderId = last_id('orders');
db()->commit();

// إرسال الطلب — POST حسب التوثيق
$r = fc_new_order($pid, $qty, $player, $uuid);
$d = is_array($r['data']) ? $r['data'] : [];
$success = ($d['status'] ?? '') === 'OK';

if (!$success) {
    // فشل → إعادة المبلغ للزبون
    db()->beginTransaction();
    db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$total, $U['id']]);
    db()->prepare("UPDATE orders SET status='reject', updated_at=" . NOW_FN() . " WHERE id=?")->execute([$orderId]);
    db()->commit();

    $apiMsg = strtolower($d['message'] ?? $d['msg'] ?? '');
    $httpCode = $r['code'] ?? 0;
    // كشف نقص الرصيد أو عدم توفر الخدمة (رصيد المورّد، فشل اتصال، رسالة balance)
    $isUnavailable = ($httpCode === 0)
        || strpos($apiMsg, 'balance') !== false
        || strpos($apiMsg, 'insufficient') !== false
        || strpos($apiMsg, 'credit') !== false
        || strpos($apiMsg, 'رصيد') !== false;

    // إشعار الأدمن بالفشل (مهم — حتى تعرف وتشحن رصيد المورّد)
    notify_admin("⚠️ <b>فشل تنفيذ طلب</b>\nالمستخدم: " . e($U['name'])
        . "\nالمنتج: " . e($p['name']) . " ×$qty"
        . ($player ? "\nID: $player" : "")
        . "\nالإجمالي: " . number_format($total) . " ل.س"
        . "\nالسبب: " . ($isUnavailable ? 'رصيد المورّد غير كافٍ / الخدمة غير متوفرة' : ('فشل — ' . ($apiMsg ?: "HTTP $httpCode")))
        . "\n💰 تمت إعادة المبلغ للزبون");

    if ($isUnavailable) {
        out(false, 'الخدمة غير متوفرة حالياً، يرجى المحاولة مرة أخرى لاحقاً. تمت إعادة المبلغ لمحفظتك. 🙏');
    }
    out(false, 'تعذّر تنفيذ الطلب وتمت إعادة المبلغ لمحفظتك.' . ($apiMsg ? ' (' . $apiMsg . ')' : ''));
}

$od = $d['data'] ?? [];
$fcId   = $od['order_id'] ?? null;
$fcStat = $od['status'] ?? 'processing';
// تسطيح الأكواد لنصوص بسيطة
$codes = [];
if (is_array($od['replay_api'] ?? null)) {
    array_walk_recursive($od['replay_api'], function($v) use (&$codes) {
        $v = trim((string)$v);
        if ($v !== '') $codes[] = $v;
    });
}

$newStatus = ($fcStat === 'accept' || $fcStat === 'completed') ? 'accept' : 'pending';
db()->prepare("UPDATE orders SET fc_order_id=?, status=?, codes=?, updated_at=" . NOW_FN() . " WHERE id=?")
    ->execute([$fcId, $newStatus, $codes ? json_encode($codes, JSON_UNESCAPED_UNICODE) : null, $orderId]);

// ملاحظة: خصم الأسعار دائم — لا نعطّله بعد الشراء، يبقى فعّالاً لكل عملية لنفس الـ ID

$msg = 'تم إرسال طلبك بنجاح ✅ — تابع حالته من صفحة "طلباتي"';
if ($codes) $msg = 'تم تنفيذ طلبك ✅ الكود موجود بصفحة "طلباتي"';

// وقت التنفيذ المتوقع
$eta = $newStatus === 'accept' ? 'تم التنفيذ فوراً ⚡' : 'الوقت المتوقع للتنفيذ: من دقيقة إلى 10 دقائق ⏱';

// إشعار المستخدم بالطلب
notify_user($U['id'],
    $newStatus === 'accept' ? 'تم تنفيذ طلبك بنجاح ✅' : 'تم استلام طلبك 🛒',
    e($p['name']) . ' ×' . $qty . ($newStatus === 'accept' ? ' — تم بنجاح.' : ' — قيد التنفيذ، الوقت المتوقع من دقيقة إلى 10 دقائق ⏱'),
    $newStatus === 'accept' ? '✅' : '🛒');

// إشعار الأدمن بالطلب الجديد
notify_admin("🛒 <b>طلب جديد</b>\nالمستخدم: " . e($U['name']) . " (#" . $U['id'] . ")\nالمنتج: " . e($p['name']) . " ×$qty\n" . ($player ? "ID: $player\n" : "") . ($couponDisc > 0 ? "خصم مفعّل: $udCode (−" . number_format($couponDisc) . " ل.س)\n" : "") . "الإجمالي: " . number_format($total) . " ل.س\nالحالة: " . ($newStatus === 'accept' ? 'تم التنفيذ ✅' : 'قيد التنفيذ ⏳'));

out(true, $msg, ['order_id' => $orderId, 'eta' => $eta, 'status' => $newStatus]);
