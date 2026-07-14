<?php
// تتبّع الطلبات المعلّقة: يفحص FastCard ويحدّث الحالة + يبعت إشعار للأدمن
require_once __DIR__ . '/fastcard_api.php';

/**
 * يفحص الطلبات المعلّقة ويحدّثها.
 * @param int|null $userId إذا مُرّر، يفحص طلبات هذا المستخدم فقط. وإلا كل الطلبات المعلّقة.
 * @param int $limit عدد الطلبات
 * @return array ملخص [checked, accepted, rejected]
 */
function track_pending_orders($userId = null, $limit = 30) {
    $checked = 0; $accepted = 0; $rejected = 0;

    if ($userId) {
        $st = db()->prepare("SELECT * FROM orders WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT ?");
        $st->bindValue(1, $userId, PDO::PARAM_INT);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->execute();
    } else {
        $st = db()->prepare("SELECT * FROM orders WHERE status='pending' ORDER BY id DESC LIMIT ?");
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
    }
    $pending = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pending as $o) {
        $chk = fc_check_uuid($o['uuid']);
        if (!$chk || !$chk['status']) continue;
        $checked++;
        $s = strtolower($chk['status']);
        // تسطيح الأكواد لنصوص بسيطة
        $codeArr = [];
        if (!empty($chk['codes']) && is_array($chk['codes'])) {
            array_walk_recursive($chk['codes'], function($v) use (&$codeArr) {
                $v = trim((string)$v);
                if ($v !== '') $codeArr[] = $v;
            });
        }
        $codes = $codeArr ? json_encode($codeArr, JSON_UNESCAPED_UNICODE) : $o['codes'];

        if ($s === 'accept' || $s === 'completed') {
            db()->prepare("UPDATE orders SET status='accept', codes=?, fc_order_id=COALESCE(fc_order_id,?), updated_at=" . NOW_FN() . " WHERE id=?")
                ->execute([$codes, $chk['id'], $o['id']]);
            $accepted++;
            // إشعار المستخدم بتنفيذ طلبه
            $codeText = $codeArr ? ' الكود متوفر بصفحة طلباتي.' : '';
            notify_user($o['user_id'], 'تم تنفيذ طلبك بنجاح ✅',
                $o['product_name'] . ' ×' . $o['qty'] . ' — تم بنجاح.' . $codeText, '✅');
            // معلومات المستخدم للإشعار
            $u = db()->prepare("SELECT name,email FROM users WHERE id=?");
            $u->execute([$o['user_id']]);
            $usr = $u->fetch(PDO::FETCH_ASSOC);
            notify_admin("✅ <b>تم تنفيذ طلب</b>\n"
                . "الزبون: " . e($usr['name'] ?? '') . "\n"
                . "المنتج: " . e($o['product_name']) . " ×" . $o['qty'] . "\n"
                . ($o['player_id'] ? "ID: " . $o['player_id'] . "\n" : "")
                . "رقم الطلب: #" . $o['id']
                . ($codeArr ? "\nالكود: " . implode(' | ', $codeArr) : ""));
        } elseif ($s === 'reject' || $s === 'rejected') {
            db()->beginTransaction();
            db()->prepare("UPDATE orders SET status='reject', updated_at=" . NOW_FN() . " WHERE id=?")->execute([$o['id']]);
            db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$o['total'], $o['user_id']]);
            db()->commit();
            $rejected++;
            // إشعار المستخدم برفض طلبه وإعادة المبلغ
            notify_user($o['user_id'], 'تعذّر تنفيذ طلبك ❌',
                $o['product_name'] . ' — أُعيد المبلغ (' . number_format($o['total']) . ' ل.س) لمحفظتك.', '❌');
            $u = db()->prepare("SELECT name FROM users WHERE id=?");
            $u->execute([$o['user_id']]);
            $usr = $u->fetch(PDO::FETCH_ASSOC);
            notify_admin("❌ <b>طلب مرفوض</b>\n"
                . "الزبون: " . e($usr['name'] ?? '') . "\n"
                . "المنتج: " . e($o['product_name']) . " ×" . $o['qty'] . "\n"
                . "رقم الطلب: #" . $o['id'] . "\n"
                . "💰 أُعيد المبلغ (" . number_format($o['total']) . " ل.س) للزبون");
        }
    }
    return ['checked' => $checked, 'accepted' => $accepted, 'rejected' => $rejected];
}
