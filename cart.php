<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function cout($ok, $msg = '', $extra = []) {
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra, JSON_UNESCAPED_UNICODE); exit;
}

$U = current_user();
if (!$U) cout(false, 'سجّل دخول أولاً', ['login' => true]);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $in['action'] ?? '';

// حساب إجمالي السلة مع تطبيق الخصم
function cart_summary() {
    $items = [];
    $total = 0;
    $disc = promo_discount_pct();
    foreach ($_SESSION['cart'] as $key => $c) {
        $p = store_product($c['product_id']);
        if (!$p) { unset($_SESSION['cart'][$key]); continue; }
        $unit = $p['price'];
        if ($disc > 0) $unit = $p['price'] * (1 - $disc/100);
        $sub = round($unit * $c['qty']);
        $total += $sub;
        $items[] = [
            'key' => $key,
            'product_id' => $c['product_id'],
            'name' => $p['name'],
            'qty' => $c['qty'],
            'player_id' => $c['player_id'],
            'unit' => $unit,
            'price_orig' => $p['price'],
            'subtotal' => $sub,
            'available' => $p['available'],
        ];
    }
    return ['items' => $items, 'total' => $total, 'count' => count($items)];
}

// ===== إضافة منتج للسلة =====
if ($action === 'add') {
    $pid = (string)($in['product_id'] ?? '');
    $qty = max(1, (float)($in['qty'] ?? 1));
    $player = trim((string)($in['player_id'] ?? ''));

    $p = store_product($pid);
    if (!$p) cout(false, 'المنتج غير موجود');
    if (!$p['available']) cout(false, 'المنتج غير متوفر حالياً ❌');
    if ($qty < $p['qty_min']) cout(false, 'أقل كمية مسموحة: ' . $p['qty_min']);
    if ($p['qty_max'] > 0 && $qty > $p['qty_max']) cout(false, 'أكبر كمية مسموحة: ' . $p['qty_max']);
    if (!empty($p['params']) && $player === '') cout(false, 'مطلوب: ' . $p['params'][0]);

    // مفتاح فريد حسب المنتج + الـ ID (نفس المنتج بنفس الـ ID = نزيد الكمية)
    $key = md5($pid . '|' . $player);
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = ['product_id' => $pid, 'qty' => $qty, 'player_id' => $player];
    }
    $s = cart_summary();
    cout(true, 'تمت الإضافة للسلة 🛒', ['count' => $s['count']]);
}

// ===== حذف عنصر =====
if ($action === 'remove') {
    $key = (string)($in['key'] ?? '');
    if (isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
    $s = cart_summary();
    cout(true, 'تم الحذف', ['count' => $s['count'], 'total' => $s['total']]);
}

// ===== تعديل الكمية =====
if ($action === 'update') {
    $key = (string)($in['key'] ?? '');
    $qty = max(1, (float)($in['qty'] ?? 1));
    if (isset($_SESSION['cart'][$key])) {
        $p = store_product($_SESSION['cart'][$key]['product_id']);
        if ($p) {
            if ($qty < $p['qty_min']) cout(false, 'أقل كمية: ' . $p['qty_min']);
            if ($p['qty_max'] > 0 && $qty > $p['qty_max']) cout(false, 'أكبر كمية: ' . $p['qty_max']);
        }
        $_SESSION['cart'][$key]['qty'] = $qty;
    }
    $s = cart_summary();
    cout(true, '', ['total' => $s['total'], 'count' => $s['count']]);
}

// ===== عدد عناصر السلة =====
if ($action === 'count') {
    cout(true, '', ['count' => count($_SESSION['cart'])]);
}

// ===== شراء كل السلة (Checkout) =====
if ($action === 'checkout') {
    $s = cart_summary();
    if (!$s['items']) cout(false, 'السلة فارغة');
    if ($U['balance'] < $s['total']) {
        cout(false, 'رصيد محفظتك غير كافٍ — المطلوب ' . number_format($s['total']) . ' ل.س. اشحن محفظتك أولاً.');
    }

    $done = []; $failed = []; $failedKeys = [];

    foreach ($s['items'] as $it) {
        $p = store_product($it['product_id']);
        if (!$p || !$p['available']) { $failed[] = $it['name'] . ' (غير متوفر)'; $failedKeys[] = $it['key']; continue; }

        $total = $it['subtotal'];
        // تأكد من الرصيد قبل كل عملية
        $cur = db()->query("SELECT balance FROM users WHERE id=" . (int)$U['id'])->fetchColumn();
        if ($cur < $total) { $failed[] = $it['name'] . ' (رصيد غير كافٍ)'; $failedKeys[] = $it['key']; continue; }

        // UUIDv4
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40); $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));

        // خصم وحجز
        $costSyp = round((float)$p['cost'] * usd_rate() * $it['qty']);
        db()->beginTransaction();
        db()->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?")
            ->execute([$total, $U['id'], $total]);
        db()->prepare("INSERT INTO orders (user_id,product_id,product_name,qty,player_id,price,total,uuid,cost_syp)
            VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$U['id'], $it['product_id'], $p['name'], $it['qty'], $it['player_id'], $p['price'], $total, $uuid, $costSyp]);
        $orderId = last_id('orders');
        db()->commit();

        // تنفيذ الطلب
        $r = fc_new_order($it['product_id'], $it['qty'], $it['player_id'], $uuid);
        $d = is_array($r['data']) ? $r['data'] : [];
        $success = ($d['status'] ?? '') === 'OK';

        if (!$success) {
            // فشل → إعادة المبلغ
            db()->beginTransaction();
            db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$total, $U['id']]);
            db()->prepare("UPDATE orders SET status='reject', updated_at=" . NOW_FN() . " WHERE id=?")->execute([$orderId]);
            db()->commit();
            $failed[] = $it['name']; $failedKeys[] = $it['key'];
            notify_admin("⚠️ <b>فشل عنصر بطلب سلة</b>\nالمستخدم: " . e($U['name']) . "\nالمنتج: " . e($p['name']) . " ×{$it['qty']}\n💰 أُعيد المبلغ");
            continue;
        }

        $od = $d['data'] ?? [];
        $fcId = $od['order_id'] ?? null;
        $fcStat = $od['status'] ?? 'processing';
        $codes = [];
        if (is_array($od['replay_api'] ?? null)) {
            array_walk_recursive($od['replay_api'], function($v) use (&$codes) {
                $v = trim((string)$v); if ($v !== '') $codes[] = $v;
            });
        }
        $newStatus = ($fcStat === 'accept' || $fcStat === 'completed') ? 'accept' : 'pending';
        db()->prepare("UPDATE orders SET fc_order_id=?, status=?, codes=?, updated_at=" . NOW_FN() . " WHERE id=?")
            ->execute([$fcId, $newStatus, $codes ? json_encode($codes, JSON_UNESCAPED_UNICODE) : null, $orderId]);

        $done[] = $p['name'];
    }

    // إبقاء العناصر الفاشلة فقط بالسلة (نحذف الناجحة)
    $newCart = [];
    foreach ($_SESSION['cart'] as $k => $v) {
        if (in_array($k, $failedKeys, true)) $newCart[$k] = $v;
    }
    $_SESSION['cart'] = $newCart;

    if (!$done && $failed) {
        cout(false, 'تعذّر تنفيذ الطلبات: ' . implode('، ', $failed) . '. تمت إعادة المبالغ.');
    }

    // إشعار الأدمن بطلب السلة
    notify_admin("🛒 <b>طلب سلة جديد</b>\nالمستخدم: " . e($U['name']) . "\nنجح: " . count($done) . " منتج\n" . ($failed ? "فشل: " . count($failed) : '') . "\nالإجمالي: " . number_format($s['total']) . " ل.س");

    $msg = '✅ تم تنفيذ ' . count($done) . ' منتج بنجاح';
    if ($failed) $msg .= ' — فشل ' . count($failed) . ' (أُعيد مبلغها)';
    $msg .= '. تابع من صفحة "طلباتي".';
    cout(true, $msg, ['done' => count($done), 'failed' => count($failed)]);
}

cout(false, 'طلب غير صحيح');
