<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
$U = current_user();

function wout($ok, $msg = '', $extra = []) {
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE); exit;
}

// هل العجلة مفعّلة؟
if (setting('wheel_active', '1') !== '1') wout(false, 'العجلة غير متاحة حالياً');

// الجوائز (قيمة بالـ ل.س) + الوزن (احتمال الظهور). الأوزان الأكبر = أكثر شيوعاً
// ملاحظة: 0 يعني "حظ أوفر" (ما ربح)
// كل الفئات تبقى ظاهرة في العجلة، لكن النتيجة الفعلية محصورة بـ "100 ل.س" أو "حظ أوفر" فقط
// (الفئات الأخرى وزنها 0 = تظهر في العجلة لكن لا يمكن ربحها)
$prizes = [
    ['value' => 0,    'label' => 'حظ أوفر', 'weight' => 55],
    ['value' => 100,  'label' => '100 ل.س', 'weight' => 45],
    ['value' => 250,  'label' => '250 ل.س', 'weight' => 0],
    ['value' => 500,  'label' => '500 ل.س', 'weight' => 0],
    ['value' => 1000, 'label' => '1000 ل.س', 'weight' => 0],
    ['value' => 2500, 'label' => '2500 ل.س', 'weight' => 0],
];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// حساب آخر دوران ومتى يحق الدوران التالي
function last_spin($uid) {
    $st = db()->prepare("SELECT created_at FROM wheel_spins WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]);
    return $st->fetchColumn();
}
function can_spin($uid) {
    $last = last_spin($uid);
    if (!$last) return [true, 0];
    $diff = time() - strtotime($last);
    $wait = 86400 - $diff; // 24 ساعة
    return [$wait <= 0, max(0, $wait)];
}

// ===== فحص الحالة =====
if ($action === 'status') {
    [$ok, $wait] = can_spin($U['id']);
    wout(true, '', ['can_spin' => $ok, 'wait' => $wait, 'prizes' => array_map(fn($p) => $p['label'], $prizes)]);
}

// ===== الدوران =====
if ($action === 'spin') {
    [$ok, $wait] = can_spin($U['id']);
    if (!$ok) {
        $h = floor($wait / 3600); $m = floor(($wait % 3600) / 60);
        wout(false, "لازم تستنى $h ساعة و $m دقيقة للدوران التالي", ['wait' => $wait]);
    }

    // اختيار جائزة حسب الوزن
    $totalWeight = array_sum(array_column($prizes, 'weight'));
    $rand = random_int(1, $totalWeight);
    $acc = 0; $idx = 0;
    foreach ($prizes as $i => $p) {
        $acc += $p['weight'];
        if ($rand <= $acc) { $idx = $i; break; }
    }
    $prize = $prizes[$idx];

    // تسجيل الدوران
    db()->prepare("INSERT INTO wheel_spins (user_id, prize) VALUES (?,?)")
        ->execute([$U['id'], $prize['value']]);

    // إضافة الرصيد إذا ربح
    if ($prize['value'] > 0) {
        db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
            ->execute([$prize['value'], $U['id']]);
        notify_user($U['id'], 'مبروك! ربحت من العجلة 🎉',
            'ربحت ' . number_format($prize['value']) . ' ل.س من عجلة الحظ. تمت إضافتها لمحفظتك.', '🎁');
    }

    wout(true, $prize['value'] > 0 ? 'مبروك! ربحت ' . $prize['label'] . ' 🎉' : 'حظ أوفر المرة الجاية 🍀',
        ['index' => $idx, 'value' => $prize['value'], 'label' => $prize['label']]);
}

wout(false, 'طلب غير صحيح');
