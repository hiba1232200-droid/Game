<?php
require_once __DIR__ . '/db.php'; // يحمّل config.php (التوكن + notify_user)
http_response_code(200);

$token = admin_bot_token();
$adminChat = (string)admin_chat_id();
if ($token === '') { echo 'ok'; exit; }

// تحقق السر (Telegram يرسله بالهيدر) لمنع أي طلب مزوّر
$secret = substr(hash('sha256', 'wh' . $token), 0, 32);
$hdr = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($hdr !== $secret) { echo 'ok'; exit; }

$update = json_decode(file_get_contents('php://input'), true);
$msg = $update['message'] ?? null;
if (!$msg) { echo 'ok'; exit; }

$chatId = (string)($msg['chat']['id'] ?? '');
$text   = trim((string)($msg['text'] ?? ''));
// فقط محادثة الأدمن
if ($adminChat !== '' && $chatId !== $adminChat) { echo 'ok'; exit; }

function tg_send($token, $chat, $text) {
    $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML']),
    ]);
    @curl_exec($ch); @curl_close($ch);
}

// ===== موافقة/رفض إيداع USDT عبر الرد على رسالة الطلب =====
// نتعرّف على الطلب من "USDT#رقم" داخل الرسالة المردود عليها، والرد يكون "موافق" أو "رفض"
$replySrc = $msg['reply_to_message']['text'] ?? '';
if ($replySrc && preg_match('/USDT#(\d+)/', $replySrc, $um)) {
    $reqId = (int)$um[1];
    $decision = null;
    if (preg_match('~(موافق|قبول|approve|ok|نعم)~ui', $text)) $decision = 'approve';
    elseif (preg_match('~(رفض|reject|no|لا)~ui', $text)) $decision = 'reject';
    if ($decision !== null) {
        try {
            $st = db()->prepare("SELECT * FROM usdt_requests WHERE id=?");
            $st->execute([$reqId]);
            $req = $st->fetch(PDO::FETCH_ASSOC);
            if (!$req) { tg_send($token, $chatId, "❌ طلب USDT رقم #$reqId غير موجود"); echo 'ok'; exit; }
            if (($req['status'] ?? '') !== 'pending') {
                tg_send($token, $chatId, "⚠️ طلب USDT #$reqId تمت معالجته مسبقاً (" . $req['status'] . ")"); echo 'ok'; exit;
            }
            $ruid = (int)$req['user_id'];
            if ($decision === 'reject') {
                db()->prepare("UPDATE usdt_requests SET status='rejected' WHERE id=?")->execute([$reqId]);
                notify_user($ruid, 'طلب إيداع USDT ❌', 'لم يتم تأكيد تحويل USDT الخاص بك. إذا كنت متأكداً من التحويل، تواصل مع الدعم مع رقم العملية.', '🪙');
                tg_send($token, $chatId, "❌ تم رفض طلب USDT #$reqId");
                echo 'ok'; exit;
            }
            // موافقة: احسب القيمة بالليرة (بسعر شام كاش) وأضفها مع البونصات والإحالة
            $base = round((float)$req['usdt_amount'] * usd_rate_shamcash());
            $bonus = 0;
            $promoPct = function_exists('promo_deposit_pct') ? promo_deposit_pct() : 0;
            $promoBonus = $promoPct > 0 ? round($base * $promoPct / 100) : 0;
            $bonus += $promoBonus;
            $refBonus = function_exists('process_referral_topup') ? process_referral_topup($ruid, $base) : 0;
            $bonus += $refBonus;
            $total = $base + $bonus;
            db()->beginTransaction();
            db()->prepare("UPDATE usdt_requests SET status='approved' WHERE id=?")->execute([$reqId]);
            db()->prepare("INSERT INTO topups (user_id,tx_id,amount) VALUES (?,?,?)")
                ->execute([$ruid, (string)$req['tx_id'], $total]);
            db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$total, $ruid]);
            db()->commit();
            notify_user($ruid, 'تم شحن محفظتك 💰', 'أُضيف ' . number_format($total) . ' ل.س' . ($bonus > 0 ? ' (منها ' . number_format($bonus) . ' مكافأة)' : '') . ' لمحفظتك عبر USDT.', '💰');
            tg_send($token, $chatId, "✅ تمت الموافقة على USDT #$reqId — أُضيف " . number_format($total) . " ل.س لرصيد المستخدم #$ruid");
        } catch (Exception $e) {
            try { db()->rollBack(); } catch (Exception $e2) {}
            tg_send($token, $chatId, "⚠️ صار خطأ بمعالجة طلب USDT #$reqId");
        }
        echo 'ok'; exit;
    }
}

$uid = 0; $reply = '';
// أمر إنهاء/حذف المحادثة: "/end 45"  أو  Reply بـ "/end"
$endUid = 0;
if (preg_match('~^/end\s+(\d+)~u', $text, $m)) {
    $endUid = (int)$m[1];
} elseif ($text === '/end' && !empty($msg['reply_to_message']['text']) && preg_match('/#(\d+)/', $msg['reply_to_message']['text'], $m)) {
    $endUid = (int)$m[1];
}
if ($endUid) {
    try {
        db()->prepare("DELETE FROM support_messages WHERE user_id=?")->execute([$endUid]);
        notify_user($endUid, 'انتهت محادثة الدعم 🎧', 'تم إنهاء المحادثة من قبل فريق الدعم. تقدر تبلّش محادثة جديدة وقت ما بدك.', '🎧');
        tg_send($token, $chatId, "✅ تم إنهاء وحذف محادثة المستخدم #$endUid");
    } catch (Exception $e) {
        tg_send($token, $chatId, "⚠️ تعذّر إنهاء المحادثة");
    }
    echo 'ok'; exit;
}

// (1) ردّ (Reply) على رسالة الطلب — نستخرج رقم المستخدم منها (#45)
if (!empty($msg['reply_to_message']['text']) && preg_match('/#(\d+)/', $msg['reply_to_message']['text'], $m)) {
    $uid = (int)$m[1]; $reply = $text;
}
// (2) صيغة مباشرة: "45: نص الرد"  أو  "/r 45 نص الرد"
if (!$uid && $text !== '') {
    if (preg_match('~^/r\s+(\d+)\s+([\s\S]+)~u', $text, $m))      { $uid = (int)$m[1]; $reply = trim($m[2]); }
    elseif (preg_match('~^(\d+)\s*[:：]\s*([\s\S]+)~u', $text, $m)) { $uid = (int)$m[1]; $reply = trim($m[2]); }
}

if (!$uid || $reply === '') {
    if ($text !== '' && strncmp($text, '/start', 6) !== 0) {
        tg_send($token, $chatId, "للرد على زبون:\n• اعمل <b>Reply</b> على رسالة طلبه، واكتب ردّك.\n• أو اكتب: <code>45: نص الرد</code> (45 = رقم المستخدم).\n\nلإنهاء محادثة زبون: <code>/end 45</code> أو ردّ بـ <code>/end</code> على رسالته.");
    }
    echo 'ok'; exit;
}

try {
    $st = db()->prepare("SELECT name FROM users WHERE id=?");
    $st->execute([$uid]);
    $name = $st->fetchColumn();
} catch (Exception $e) { $name = false; }

if ($name === false) { tg_send($token, $chatId, "❌ ما في مستخدم رقمو #$uid"); echo 'ok'; exit; }

try {
    db()->prepare("INSERT INTO support_messages (user_id,sender,body,read_user,read_admin) VALUES (?, 'admin', ?, 0, 1)")
        ->execute([$uid, mb_substr($reply, 0, 2000)]);
    notify_user($uid, 'رد من الدعم 🧑‍💼', mb_substr($reply, 0, 140), '🎧');
    tg_send($token, $chatId, "✅ وصل ردّك للزبون " . $name . " (#$uid)");
} catch (Exception $e) {
    tg_send($token, $chatId, "⚠️ صار خطأ بإرسال الرد");
}
echo 'ok';
