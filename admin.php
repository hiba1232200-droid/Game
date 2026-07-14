<?php
require_once __DIR__ . '/fastcard_api.php';
$U = require_admin();
$tab = $_GET['tab'] ?? 'stats';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['usd_rate'])) {
        set_setting('usd_rate', (float)$_POST['usd_rate']);
        clear_products_cache(); // فوري: بلا هذا، الأسعار القديمة تضل ظاهرة لحد ساعة
        $msg = 'تم حفظ سعر صرف تسعير الألعاب ✅ (الأسعار تحدّثت فوراً)';
    }
    if (isset($_POST['usd_rate_shamcash'])) {
        set_setting('usd_rate_shamcash', (float)$_POST['usd_rate_shamcash']);
        $msg = 'تم حفظ سعر صرف شحن شام كاش بالدولار ✅';
    }
    // إعدادات إشعارات تلجرام
    if (isset($_POST['tg_token'])) {
        set_setting('tg_token', trim($_POST['tg_token']));
        set_setting('tg_chat_id', trim($_POST['tg_chat_id'] ?? ''));
        $msg = 'تم حفظ إعدادات تلجرام ✅';
    }
    if (isset($_POST['save_referral'])) {
        set_setting('ref_enabled', isset($_POST['ref_enabled']) ? '1' : '0');
        set_setting('ref_commission_pct', (float)($_POST['ref_commission_pct'] ?? 5));
        set_setting('ref_signup_gift', (float)($_POST['ref_signup_gift'] ?? 5000));
        set_setting('ref_first_topup_pct', (float)($_POST['ref_first_topup_pct'] ?? 15));
        $msg = 'تم حفظ إعدادات الإحالة ✅';
    }
    if (isset($_POST['save_maintenance'])) {
        set_setting('maintenance', isset($_POST['maintenance']) ? '1' : '0');
        if (isset($_POST['maintenance_msg'])) set_setting('maintenance_msg', trim($_POST['maintenance_msg']));
        $msg = isset($_POST['maintenance']) ? '🔧 تم تفعيل وضع الصيانة — الموقع مخفي عن الزوّار الآن' : '✅ تم إيقاف وضع الصيانة — الموقع عاد للعمل';
    }
    if (isset($_POST['save_wa_popup'])) {
        set_setting('wa_popup_on', isset($_POST['wa_popup_on']) ? '1' : '0');
        set_setting('wa_popup_link', trim($_POST['wa_popup_link'] ?? ''));
        if (isset($_POST['wa_popup_text'])) set_setting('wa_popup_text', trim($_POST['wa_popup_text']));
        $msg = 'تم حفظ إعدادات نافذة الواتساب ✅';
    }
    if (isset($_POST['save_theme'])) {
        $a1 = trim($_POST['theme_accent'] ?? '#ffb938');
        $a2 = trim($_POST['theme_accent2'] ?? '#7c5cff');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $a1)) $a1 = '#ffb938';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $a2)) $a2 = '#7c5cff';
        set_setting('theme_accent', $a1);
        set_setting('theme_accent2', $a2);
        $msg = '🎨 تم حفظ ألوان الموقع ✅';
    }
    if (isset($_POST['change_admin_pass'])) {
        $cur = $_POST['current_pass'] ?? '';
        $new = $_POST['new_pass'] ?? '';
        $conf = $_POST['confirm_pass'] ?? '';
        // اجلب كلمة مرور الأدمن الحالي من القاعدة للتحقق
        $st = db()->prepare("SELECT password FROM users WHERE id=?");
        $st->execute([$U['id']]);
        $curHash = $st->fetchColumn();
        if (!$curHash || !password_verify($cur, $curHash)) {
            $msg = '❌ كلمة المرور الحالية غير صحيحة';
        } elseif (strlen($new) < 8) {
            $msg = '❌ كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
        } elseif ($new !== $conf) {
            $msg = '❌ تأكيد كلمة المرور لا يطابق';
        } else {
            db()->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $U['id']]);
            $msg = '✅ تم تغيير كلمة المرور بنجاح — استخدمها في الدخول القادم';
        }
    }
    if (isset($_POST['tg_test'])) {
        if (tg_enabled()) {
            $ok = notify_admin("✅ <b>اختبار ناجح</b>\nإشعارات " . STORE_NAME . " تعمل بشكل صحيح.");
            $msg = $ok ? 'تم إرسال رسالة اختبار — تحقق من تلجرام 📩' : 'فشل الإرسال — تأكد من صحة التوكن ومعرّف المحادثة، وأنك ضغطت Start مع البوت ❌';
        } else {
            $msg = 'احفظ التوكن ومعرّف المحادثة أولاً ثم اختبر ⚠️';
        }
    }
    if (isset($_POST['profit_percent'])) {
        set_setting('profit_percent', (float)$_POST['profit_percent']);
        clear_products_cache(); // إبطال الكاش (المفتاح القديم 'fc_products' كان خاطئ وما كان يعمل شي)
        $msg = 'تم حفظ هامش الربح ✅ (الأسعار تحدّثت فوراً)';
    }
    if (isset($_POST['add_balance_user'], $_POST['add_balance_amount'])) {
        db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
            ->execute([(float)$_POST['add_balance_amount'], (int)$_POST['add_balance_user']]);
        $msg = 'تم تعديل الرصيد ✅';
    }
    // حظر / فك حظر مستخدم (ما عدا الأدمن)
    if (isset($_POST['toggle_ban'])) {
        $uid = (int)$_POST['toggle_ban'];
        db()->prepare("UPDATE users SET banned = 1 - COALESCE(banned,0) WHERE id=? AND role <> 'admin'")->execute([$uid]);
        $msg = 'تم تحديث حالة المستخدم ✅';
    }
    // إرسال رسالة لمستخدم (أو لكل المستخدمين)
    if (isset($_POST['send_msg'])) {
        $title = trim($_POST['msg_title'] ?? '');
        $body  = trim($_POST['msg_body'] ?? '');
        $all   = !empty($_POST['msg_all']);
        $uid   = (int)($_POST['msg_user'] ?? 0);
        if ($title === '' && $body === '') {
            $msg = 'اكتب نص الرسالة أولاً';
        } elseif ($all) {
            $ids = db()->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) notify_user((int)$id, $title !== '' ? $title : 'رسالة من الإدارة', $body, '📩');
            $msg = 'تم إرسال الرسالة لكل المستخدمين ✅ (' . count($ids) . ' مستخدم)';
        } elseif ($uid > 0) {
            $chk = db()->prepare("SELECT name FROM users WHERE id=?");
            $chk->execute([$uid]);
            $name = $chk->fetchColumn();
            if ($name !== false) {
                notify_user($uid, $title !== '' ? $title : 'رسالة من الإدارة', $body, '📩');
                $msg = 'تم إرسال الرسالة إلى ' . e($name) . ' (#' . $uid . ') ✅';
            } else {
                $msg = 'ما في مستخدم بهالرقم ❌';
            }
        } else {
            $msg = 'حط رقم المستخدم أو اختر "إرسال للجميع"';
        }
    }
    // رد الدعم على رسالة مستخدم
    if (isset($_POST['support_reply'])) {
        $uid  = (int)($_POST['support_user_id'] ?? 0);
        $body = trim($_POST['support_body'] ?? '');
        if ($uid > 0 && $body !== '') {
            db()->prepare("INSERT INTO support_messages (user_id,sender,body,read_user,read_admin) VALUES (?, 'admin', ?, 0, 1)")
                ->execute([$uid, mb_substr($body, 0, 2000)]);
            notify_user($uid, 'رد من الدعم 🧑‍💼', mb_substr($body, 0, 140), '🎧');
            $msg = 'تم إرسال الرد ✅';
        } else {
            $msg = 'اكتب نص الرد';
        }
    }
    // رفع صورة لقسم/منتج
    if (isset($_POST['save_img'])) {
        $iid = trim($_POST['img_item_id'] ?? '');
        if ($iid === '') {
            $msg = 'حدد رقم القسم/المنتج';
        } elseif (empty($_FILES['img_file']['tmp_name'])) {
            $msg = 'اختر صورة';
        } else {
            $f = $_FILES['img_file'];
            $info = @getimagesize($f['tmp_name']);
            $mime = $info['mime'] ?? '';
            $okMimes = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
            if (!in_array($mime, $okMimes, true)) {
                $msg = 'الملف لازم يكون صورة (PNG / JPG / WEBP / GIF)';
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $msg = 'حجم الصورة كبير — الحد الأقصى 2 ميغا';
            } else {
                $data = base64_encode(file_get_contents($f['tmp_name']));
                if (is_pg()) {
                    db()->prepare("INSERT INTO item_images (item_id,mime,data,updated_at) VALUES (?,?,?,NOW())
                        ON CONFLICT (item_id) DO UPDATE SET mime=EXCLUDED.mime, data=EXCLUDED.data, updated_at=NOW()")
                        ->execute([$iid, $mime, $data]);
                } else {
                    db()->prepare("INSERT OR REPLACE INTO item_images (item_id,mime,data,updated_at) VALUES (?,?,?,datetime('now'))")
                        ->execute([$iid, $mime, $data]);
                }
                $msg = 'تم حفظ الصورة ✅';
            }
        }
    }
    if (isset($_POST['del_img'])) {
        db()->prepare("DELETE FROM item_images WHERE item_id=?")->execute([(string)$_POST['del_img']]);
        $msg = 'تم حذف الصورة ✅';
    }
    // ربط بوت تلجرام لاستقبال ردود الدعم
    if (isset($_POST['tg_setup'])) {
        $token = admin_bot_token();
        if ($token === '') {
            $msg = 'ما في توكن بوت — اضبط ADMIN_BOT_TOKEN أولاً';
        } else {
            $secret = substr(hash('sha256', 'wh' . $token), 0, 32);
            $url = site_url() . '/telegram_webhook.php';
            $ch = curl_init("https://api.telegram.org/bot$token/setWebhook");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['url' => $url, 'secret_token' => $secret, 'allowed_updates' => json_encode(['message'])]),
            ]);
            $res = @curl_exec($ch); @curl_close($ch);
            $msg = ($res && strpos($res, '"ok":true') !== false)
                ? 'تم ربط البوت بتلجرام ✅ صرت تقدر ترد من تلجرام'
                : ('فشل الربط: ' . ($res ?: 'تعذّر الاتصال'));
        }
    }
    if (isset($_POST['sync_products'])) {
        clear_products_cache(); // يصفّي كل الأقسام الفرعية، مش بس القسم الرئيسي
        store_products(true); fc_content(0, true);
        $msg = 'تمت مزامنة المنتجات من FastCard ✅';
    }
    // إضافة كوبون
    if (isset($_POST['add_coupon'])) {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $type = ($_POST['coupon_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $amt = (float)($_POST['coupon_amount'] ?? 0);
        $maxu = (int)($_POST['coupon_maxuses'] ?? 0);
        $forUser = (int)($_POST['coupon_user'] ?? 0); // 0 = للجميع، رقم = خاص بمستخدم
        if ($code && $amt > 0) {
            try {
                db()->prepare("INSERT INTO coupons (code,type,amount,max_uses,user_id) VALUES (?,?,?,?,?)")
                    ->execute([$code, $type, $amt, $maxu, $forUser]);
                $msg = 'تم إضافة كود الخصم ✅' . ($forUser ? " (خاص بالمستخدم #$forUser)" : '');
            } catch (Exception $e) { $msg = 'الكود موجود مسبقاً'; }
        } else { $msg = 'تأكد من الكود والقيمة'; }
    }
    // إضافة كود خصم على الأسعار (مربوط بـ ID لاعب محدد)
    if (isset($_POST['add_price_coupon'])) {
        $code = strtoupper(trim($_POST['pc_code'] ?? ''));
        $type = ($_POST['pc_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $amt  = (float)($_POST['pc_amount'] ?? 0);
        $maxu = (int)($_POST['pc_maxuses'] ?? 0);
        $playerId = trim($_POST['pc_player'] ?? '');
        if ($code && $amt > 0) {
            try {
                db()->prepare("INSERT INTO coupons (code,type,amount,max_uses,user_id,scope,player_id) VALUES (?,?,?,?,0,'price',?)")
                    ->execute([$code, $type, $amt, $maxu, $playerId]);
                $msg = 'تم إضافة كود خصم الأسعار ✅' . ($playerId !== '' ? " (مربوط بـ ID: $playerId)" : ' (لأي ID)');
            } catch (Exception $e) { $msg = 'الكود موجود مسبقاً'; }
        } else { $msg = 'تأكد من الكود والقيمة'; }
    }
    if (isset($_POST['toggle_coupon'])) {
        db()->prepare("UPDATE coupons SET active = 1 - active WHERE id=?")->execute([(int)$_POST['toggle_coupon']]);
        $msg = 'تم التحديث ✅';
    }
    if (isset($_POST['del_coupon'])) {
        db()->prepare("DELETE FROM coupons WHERE id=?")->execute([(int)$_POST['del_coupon']]);
        $msg = 'تم حذف الكود ✅';
    }
    // حفظ العرض
    if (isset($_POST['save_promo'])) {
        set_setting('promo_active', isset($_POST['promo_active']) ? '1' : '0');
        set_setting('promo_type', in_array($_POST['promo_type'] ?? '', ['discount','deposit','banner']) ? $_POST['promo_type'] : 'banner');
        set_setting('promo_value', (string)(float)($_POST['promo_value'] ?? 0));
        set_setting('promo_title', trim($_POST['promo_title'] ?? 'عرض خاص'));
        $mode = $_POST['promo_time_mode'] ?? 'manual';
        if ($mode === 'datetime' && !empty($_POST['promo_end_date'])) {
            set_setting('promo_end', (string)strtotime($_POST['promo_end_date']));
        } elseif ($mode === 'hours' && !empty($_POST['promo_hours'])) {
            set_setting('promo_end', (string)(time() + (int)$_POST['promo_hours'] * 3600));
        } else {
            set_setting('promo_end', '');
        }
        $msg = 'تم حفظ إعدادات العرض ✅';
    }
    if (isset($_POST['stop_promo'])) {
        set_setting('promo_active', '0');
        $msg = 'تم إيقاف العرض ✅';
    }
    // إضافة سلايد
    if (isset($_POST['add_slide'])) {
        $img = trim($_POST['slide_image'] ?? '');
        $link = trim($_POST['slide_link'] ?? '');
        $sort = (int)($_POST['slide_sort'] ?? 0);
        if ($img) {
            db()->prepare("INSERT INTO slides (image,link,sort) VALUES (?,?,?)")->execute([$img, $link, $sort]);
            $msg = 'تم إضافة الصورة ✅';
        } else { $msg = 'أدخل رابط الصورة'; }
    }
    if (isset($_POST['del_slide'])) {
        db()->prepare("DELETE FROM slides WHERE id=?")->execute([(int)$_POST['del_slide']]);
        $msg = 'تم حذف الصورة ✅';
    }
    if (isset($_POST['approve_id'])) {
        $vid = (int)$_POST['approve_id'];
        $row = db()->prepare("SELECT user_id FROM id_verifications WHERE id=?");
        $row->execute([$vid]);
        $uid = (int)$row->fetchColumn();
        if ($uid) {
            db()->prepare("UPDATE id_verifications SET status='approved' WHERE id=?")->execute([$vid]);
            db()->prepare("UPDATE users SET id_verified=1 WHERE id=?")->execute([$uid]);
            notify_user($uid, 'تم توثيق هويتك ✅', 'تمت الموافقة على طلب التوثيق. حسابك الآن موثّق بالهوية.', '✅');
            $msg = 'تمت الموافقة على التوثيق ✅';
        }
    }
    if (isset($_POST['reject_id'])) {
        $vid = (int)$_POST['reject_id'];
        $row = db()->prepare("SELECT user_id FROM id_verifications WHERE id=?");
        $row->execute([$vid]);
        $uid = (int)$row->fetchColumn();
        if ($uid) {
            db()->prepare("UPDATE id_verifications SET status='rejected' WHERE id=?")->execute([$vid]);
            notify_user($uid, 'طلب التوثيق مرفوض ❌', 'لم تتم الموافقة على صورة هويتك. يرجى رفع صورة أوضح والمحاولة مجدداً.', '🪪');
            $msg = 'تم رفض الطلب';
        }
    }
    if (isset($_POST['approve_phone'])) {
        $oid = (int)$_POST['approve_phone'];
        $row = db()->prepare("SELECT user_id, phone FROM otp_codes WHERE id=?");
        $row->execute([$oid]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            db()->prepare("UPDATE users SET phone=?, phone_verified=1 WHERE id=?")->execute([$r['phone'], $r['user_id']]);
            db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$r['user_id']]);
            notify_user($r['user_id'], 'تم توثيق رقمك ✅', 'تمت الموافقة على توثيق رقم موبايلك. حسابك الآن أكثر أماناً.', '✅');
            $msg = 'تم توثيق الرقم ✅';
        }
    }
    if (isset($_POST['reject_phone'])) {
        $oid = (int)$_POST['reject_phone'];
        $row = db()->prepare("SELECT user_id FROM otp_codes WHERE id=?");
        $row->execute([$oid]);
        $uid = (int)$row->fetchColumn();
        if ($uid) {
            db()->prepare("DELETE FROM otp_codes WHERE user_id=?")->execute([$uid]);
            notify_user($uid, 'طلب توثيق الرقم مرفوض ❌', 'لم نستلم رسالة تأكيد على واتساب. حاول مجدداً وأرسل الرسالة كما هي.', '📱');
            $msg = 'تم رفض الطلب';
        }
    }
}

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));
$dateCol = is_pg() ? "created_at::date" : "date(created_at)";
$stats = [
    'users'   => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'orders'  => db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'sales'   => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept'")->fetchColumn(),
    'pending' => db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn(),
];
// مبيعات زمنية (الطلبات المنفّذة)
$salesToday = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol = '$today'")->fetchColumn();
$salesWeek  = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol >= '$weekAgo'")->fetchColumn();
$salesMonth = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol >= '$monthAgo'")->fetchColumn();
$ordersToday = db()->query("SELECT COUNT(*) FROM orders WHERE $dateCol = '$today'")->fetchColumn();
$topupsTotal = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups")->fetchColumn();
$topupsToday = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups WHERE $dateCol = '$today'")->fetchColumn();
// أكثر 5 منتجات مبيعاً
$topProducts = db()->query("SELECT product_name, COUNT(*) cnt, COALESCE(SUM(total),0) revenue FROM orders WHERE status='accept' GROUP BY product_name ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ===== بيانات تبويب "أرباحي" =====
// الربح = مجموع (السعر النهائي − تكلفة فاست كارد) للطلبات المنفّذة
// للطلبات القديمة (cost_syp = 0 لأنها سُجّلت قبل هذا التحديث) نقدّر التكلفة
// من هامش الربح: التكلفة ≈ الإجمالي ÷ (1 + الهامش%). هذا تقدير معقول للقديم فقط.
$profitData = null;
if ($tab === 'profit') {
    // نحسب فقط الطلبات الجديدة التي خُزّنت تكلفتها الفعلية (cost_syp > 0).
    // الطلبات القديمة قبل تفعيل القسم تُتجاهَل تماماً — أرباح دقيقة 100% تبدأ من اليوم.
    $onlyNew = "AND COALESCE(cost_syp,0) > 0";
    $profExpr = "COALESCE(SUM(total),0) - COALESCE(SUM(cost_syp),0)";

    $rangeProfit = function($where) use ($profExpr, $onlyNew) {
        $row = db()->query("SELECT COALESCE(SUM(total),0) sales, COALESCE(SUM(cost_syp),0) cost,
            ($profExpr) profit, COUNT(*) cnt FROM orders WHERE status='accept' $onlyNew $where")->fetch(PDO::FETCH_ASSOC);
        return [
            'sales'  => (float)($row['sales'] ?? 0),
            'cost'   => (float)($row['cost'] ?? 0),
            'profit' => (float)($row['profit'] ?? 0),
            'cnt'    => (int)($row['cnt'] ?? 0),
        ];
    };
    $profitData = [
        'today' => $rangeProfit("AND $dateCol = '$today'"),
        'week'  => $rangeProfit("AND $dateCol >= '$weekAgo'"),
        'month' => $rangeProfit("AND $dateCol >= '$monthAgo'"),
        'all'   => $rangeProfit(""),
    ];
    // أكثر المنتجات ربحاً (من الطلبات الجديدة فقط)
    $profitData['top'] = db()->query("SELECT product_name,
        COUNT(*) cnt,
        COALESCE(SUM(total),0) revenue,
        ($profExpr) profit
        FROM orders WHERE status='accept' $onlyNew
        GROUP BY product_name ORDER BY profit DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    // ربح آخر 7 أيام (رسم بياني)
    $profitData['daily'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $r = $rangeProfit("AND $dateCol = '$d'");
        $profitData['daily'][] = ['label' => date('m/d', strtotime($d)), 'profit' => $r['profit']];
    }
}

// مبيعات آخر 7 أيام (للرسم البياني)
$daily = [];
if ($tab === 'stats') {
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $sum = db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='accept' AND $dateCol = '$d'")->fetchColumn();
        $cnt = db()->query("SELECT COUNT(*) FROM orders WHERE status='accept' AND $dateCol = '$d'")->fetchColumn();
        $daily[] = ['date' => $d, 'label' => date('m/d', strtotime($d)), 'sum' => (float)$sum, 'cnt' => (int)$cnt];
    }
}
$dailyMax = 1;
foreach ($daily as $dd) { if ($dd['sum'] > $dailyMax) $dailyMax = $dd['sum']; }

$fcProfile = $tab === 'stats' ? fc_profile() : null;
$fcBalance = is_array($fcProfile) ? ($fcProfile['balance'] ?? $fcProfile['data']['balance'] ?? null) : null;

$pageTitle = 'لوحة الأدمن';
include __DIR__ . '/header.php'; ?>

<h1 class="section-title">لوحة الأدمن 🛠</h1>
<div class="tabs">
  <a class="<?= $tab === 'stats' ? 'on' : '' ?>" href="?tab=stats">إحصائيات</a>
  <a class="<?= $tab === 'profit' ? 'on' : '' ?>" href="?tab=profit">أرباحي 💵</a>
  <a class="<?= $tab === 'orders' ? 'on' : '' ?>" href="?tab=orders">الطلبات</a>
  <a class="<?= $tab === 'topups' ? 'on' : '' ?>" href="?tab=topups">الإيداعات</a>
  <a class="<?= $tab === 'users' ? 'on' : '' ?>" href="?tab=users">المستخدمين</a>
  <a class="<?= $tab === 'support' ? 'on' : '' ?>" href="?tab=support">الدعم 🎧</a>
  <a class="<?= $tab === 'images' ? 'on' : '' ?>" href="?tab=images">الصور 🖼️</a>
  <a class="<?= $tab === 'coupons' ? 'on' : '' ?>" href="?tab=coupons">كوبونات</a>
  <a class="<?= $tab === 'slides' ? 'on' : '' ?>" href="?tab=slides">السلايدر</a>
  <a class="<?= $tab === 'idverify' ? 'on' : '' ?>" href="?tab=idverify">توثيق الهوية</a>
  <a class="<?= $tab === 'phoneverify' ? 'on' : '' ?>" href="?tab=phoneverify">توثيق الأرقام</a>
  <a class="<?= $tab === 'promo' ? 'on' : '' ?>" href="?tab=promo">العروض</a>
  <a class="<?= $tab === 'settings' ? 'on' : '' ?>" href="?tab=settings">الإعدادات</a>
</div>
<?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>

<?php if ($tab === 'stats'): ?>
  <!-- الأرباح الزمنية -->
  <div class="grid stats-grid">
    <div class="card stat highlight"><div class="n"><?= number_format($salesToday) ?></div><div>💰 مبيعات اليوم (ل.س)</div></div>
    <div class="card stat"><div class="n"><?= number_format($salesWeek) ?></div><div>📅 آخر 7 أيام</div></div>
    <div class="card stat"><div class="n"><?= number_format($salesMonth) ?></div><div>📆 آخر 30 يوم</div></div>
    <div class="card stat"><div class="n"><?= $ordersToday ?></div><div>🛒 طلبات اليوم</div></div>
  </div>

  <!-- إجماليات -->
  <div class="grid stats-grid" style="margin-top:14px">
    <div class="card stat"><div class="n"><?= $stats['users'] ?></div><div>👥 مستخدم</div></div>
    <div class="card stat"><div class="n"><?= $stats['orders'] ?></div><div>📦 إجمالي الطلبات</div></div>
    <div class="card stat"><div class="n"><?= number_format($stats['sales']) ?></div><div>✅ إجمالي المبيعات</div></div>
    <div class="card stat"><div class="n"><?= $stats['pending'] ?></div><div>⏳ قيد التنفيذ</div></div>
    <div class="card stat"><div class="n"><?= number_format($topupsTotal) ?></div><div>💳 إجمالي الإيداعات</div></div>
    <div class="card stat"><div class="n"><?= number_format($topupsToday) ?></div><div>💵 إيداعات اليوم</div></div>
    <?php if ($fcBalance !== null): ?>
      <div class="card stat <?= (float)$fcBalance < 5 ? 'warn' : '' ?>"><div class="n"><?= number_format((float)$fcBalance, 2) ?></div><div>🔋 رصيدك في FastCard ($)</div></div>
    <?php endif; ?>
  </div>

  <!-- رسم بياني: مبيعات آخر 7 أيام -->
  <?php if ($daily): ?>
  <div class="card" style="margin-top:14px">
    <h3>📈 مبيعات آخر 7 أيام</h3>
    <div class="chart7">
      <?php foreach ($daily as $dd): $h = $dailyMax > 0 ? max(4, ($dd['sum'] / $dailyMax) * 100) : 4; ?>
        <div class="chart7-col">
          <div class="chart7-val"><?= $dd['sum'] > 0 ? number_format($dd['sum']/1000, 1).'k' : '0' ?></div>
          <div class="chart7-bar" style="height:<?= $h ?>%" title="<?= number_format($dd['sum']) ?> ل.س — <?= $dd['cnt'] ?> طلب"></div>
          <div class="chart7-lbl"><?= $dd['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- أكثر المنتجات مبيعاً -->
  <?php if ($topProducts): ?>
  <div class="card" style="margin-top:14px">
    <h3>🏆 أكثر المنتجات مبيعاً</h3>
    <table class="tbl">
      <tr><th>المنتج</th><th>عدد المبيعات</th><th>الإيرادات</th></tr>
      <?php foreach ($topProducts as $i => $tp): ?>
        <tr>
          <td><?= ['🥇','🥈','🥉','4️⃣','5️⃣'][$i] ?? '' ?> <?= e($tp['product_name']) ?></td>
          <td><b><?= $tp['cnt'] ?></b></td>
          <td><?= number_format($tp['revenue']) ?> ل.س</td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'profit'):
  $pd = $profitData; ?>
  <h3 style="margin:6px 0 14px">💵 أرباحي — صافي الربح بعد خصم تكلفة فاست كارد</h3>

  <!-- بطاقات الربح الزمنية -->
  <div class="grid stats-grid">
    <div class="card stat highlight"><div class="n"><?= number_format($pd['today']['profit']) ?></div><div>💰 ربح اليوم (ل.س)</div></div>
    <div class="card stat"><div class="n"><?= number_format($pd['week']['profit']) ?></div><div>📅 ربح آخر 7 أيام</div></div>
    <div class="card stat"><div class="n"><?= number_format($pd['month']['profit']) ?></div><div>📆 ربح آخر 30 يوم</div></div>
    <div class="card stat"><div class="n"><?= number_format($pd['all']['profit']) ?></div><div>🏦 إجمالي الربح الكلي</div></div>
  </div>

  <!-- تفصيل: المبيعات مقابل التكلفة مقابل الربح -->
  <div class="card" style="margin-top:14px">
    <h3>📊 التفصيل الكامل (الطلبات المنفّذة)</h3>
    <table class="tbl">
      <tr><th>الفترة</th><th>المبيعات</th><th>تكلفة فاست كارد</th><th>صافي الربح</th><th>الطلبات</th><th>هامش</th></tr>
      <?php
      $rows = ['اليوم' => $pd['today'], 'آخر 7 أيام' => $pd['week'], 'آخر 30 يوم' => $pd['month'], 'الكل' => $pd['all']];
      foreach ($rows as $label => $r):
        $margin = $r['sales'] > 0 ? ($r['profit'] / $r['sales'] * 100) : 0; ?>
        <tr>
          <td><b><?= $label ?></b></td>
          <td><?= number_format($r['sales']) ?></td>
          <td style="color:var(--danger,#e05)"><?= number_format($r['cost']) ?></td>
          <td style="color:#28c76f"><b><?= number_format($r['profit']) ?></b></td>
          <td><?= $r['cnt'] ?></td>
          <td><?= number_format($margin, 1) ?>%</td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="muted small" style="margin-top:8px">💡 كل المبالغ بالليرة السورية. الربح = سعر بيعك − تكلفة فاست كارد الفعلية.</p>
  </div>

  <!-- رسم بياني: ربح آخر 7 أيام -->
  <?php
  $pmax = 1; foreach ($pd['daily'] as $dd) { if ($dd['profit'] > $pmax) $pmax = $dd['profit']; }
  if ($pd['daily']): ?>
  <div class="card" style="margin-top:14px">
    <h3>📈 ربح آخر 7 أيام</h3>
    <div class="chart7">
      <?php foreach ($pd['daily'] as $dd): $h = $pmax > 0 ? max(4, ($dd['profit'] / $pmax) * 100) : 4; ?>
        <div class="chart7-col">
          <div class="chart7-val"><?= $dd['profit'] > 0 ? number_format($dd['profit']/1000, 1).'k' : '0' ?></div>
          <div class="chart7-bar" style="height:<?= $h ?>%" title="<?= number_format($dd['profit']) ?> ل.س"></div>
          <div class="chart7-lbl"><?= $dd['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- أكثر المنتجات ربحاً -->
  <?php if (!empty($pd['top'])): ?>
  <div class="card" style="margin-top:14px">
    <h3>🏆 أكثر المنتجات ربحاً</h3>
    <table class="tbl">
      <tr><th>#</th><th>المنتج</th><th>المبيعات</th><th>عدد الطلبات</th><th>صافي الربح</th></tr>
      <?php foreach ($pd['top'] as $i => $tp): ?>
        <tr>
          <td><?= ['🥇','🥈','🥉'][$i] ?? ($i+1) ?></td>
          <td><?= e($tp['product_name']) ?></td>
          <td><?= number_format((float)$tp['revenue']) ?></td>
          <td><b><?= (int)$tp['cnt'] ?></b></td>
          <td style="color:#28c76f"><b><?= number_format((float)$tp['profit']) ?></b> ل.س</td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <p class="muted small" style="margin-top:12px">
    ✅ هذا القسم يعرض الأرباح الدقيقة فقط للطلبات الجديدة (التي تُخزَّن تكلفتها الفعلية لحظة الشراء).
    الطلبات القديمة قبل تفعيل القسم غير محسوبة هنا — الأرباح تبدأ من اليوم.
  </p>

<?php elseif ($tab === 'orders'):
  $orders = db()->query("SELECT o.*, u.name uname FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $statusLabels = ['accept' => '✅ تم', 'pending' => '⏳ قيد التنفيذ', 'reject' => '❌ مرفوض']; ?>
  <div class="card">
    <h3>الطلبات (<?= count($orders) ?>)</h3>
    <input type="text" id="ordSearch" placeholder="🔍 ابحث بالاسم أو المنتج أو ID..." onkeyup="filterRows('ordSearch','ordersTable')" style="margin-bottom:12px">
    <table class="tbl" id="ordersTable">
      <tr><th>#</th><th>المستخدم</th><th>المنتج</th><th>ID</th><th>الإجمالي</th><th>الحالة</th><th>التاريخ</th></tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= $o['id'] ?></td><td><?= e($o['uname']) ?></td>
          <td><?= e($o['product_name']) ?> ×<?= $o['qty'] ?></td>
          <td class="small"><?= e($o['player_id']) ?></td>
          <td><b><?= number_format($o['total']) ?></b></td>
          <td><?= $statusLabels[$o['status']] ?? e($o['status']) ?></td>
          <td class="small"><?= e($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterRows(inputId, tableId) {
    const q = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  </script>

<?php elseif ($tab === 'topups'):
  $topups = db()->query("SELECT t.*, u.name uname, u.email FROM topups t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $totalIn = db()->query("SELECT COALESCE(SUM(amount),0) FROM topups")->fetchColumn(); ?>
  <div class="card">
    <h3>الإيداعات — إجمالي: <?= number_format($totalIn) ?> ل.س</h3>
    <input type="text" id="topupSearch" placeholder="🔍 ابحث بالاسم أو رقم العملية..." onkeyup="filterRows('topupSearch','topupsTable')" style="margin-bottom:12px">
    <table class="tbl" id="topupsTable">
      <tr><th>#</th><th>المستخدم</th><th>رقم العملية</th><th>المبلغ</th><th>كوبون</th><th>التاريخ</th></tr>
      <?php foreach ($topups as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= e($t['uname']) ?></td>
          <td class="small"><?= e($t['tx_id']) ?></td>
          <td><b><?= number_format($t['amount']) ?></b></td>
          <td><?= e($t['coupon'] ?? '') ?></td>
          <td class="small"><?= e($t['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterRows(inputId, tableId) {
    const q = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  </script>

<?php elseif ($tab === 'users'):
  $users = db()->query("SELECT * FROM users ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>تعديل رصيد مستخدم 💰</h3>
    <p class="muted">حط رقم المستخدم (من الجدول تحت) والمبلغ — موجب للإضافة، سالب للخصم.</p>
    <form method="post" class="inline-form">
      <input name="add_balance_user" type="number" placeholder="رقم المستخدم" required>
      <input name="add_balance_amount" type="number" step="any" placeholder="المبلغ بالليرة (± )" required>
      <button class="btn">تنفيذ</button>
    </form>
  </div>
  <div class="card">
    <h3>إرسال رسالة لمستخدم 📩</h3>
    <p class="muted">بتوصل الرسالة للمستخدم كإشعار داخل الموقع. حط رقم المستخدم، أو فعّل "إرسال للجميع".</p>
    <form method="post">
      <div class="inline-form">
        <input name="msg_user" id="msgUser" type="number" placeholder="رقم المستخدم">
        <input name="msg_title" placeholder="عنوان الرسالة (اختياري)">
      </div>
      <textarea name="msg_body" id="msgBody" placeholder="اكتب نص الرسالة هون..." rows="3" required style="width:100%; margin-top:10px"></textarea>
      <label style="display:flex; align-items:center; gap:8px; margin-top:10px; cursor:pointer">
        <input type="checkbox" name="msg_all" value="1" style="width:auto"> إرسال لكل المستخدمين
      </label>
      <button class="btn" name="send_msg" value="1" style="margin-top:12px">إرسال 📩</button>
    </form>
  </div>
  <div class="card">
    <h3>المستخدمين (<?= count($users) ?>)</h3>
    <input type="text" id="userSearch" placeholder="🔍 ابحث بالاسم أو الإيميل..." onkeyup="filterUsers()" style="margin-bottom:12px">
    <table class="tbl" id="usersTable">
      <tr><th>#</th><th>الاسم</th><th>الإيميل</th><th>الرصيد</th><th>الدور</th><th>الحالة</th><th>إجراء</th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?= $u['id'] ?></b></td>
          <td><?= e($u['name']) ?></td>
          <td class="small"><?= e($u['email']) ?></td>
          <td><b><?= number_format($u['balance']) ?></b></td>
          <td><?= $u['role'] === 'admin' ? '👑 أدمن' : 'مستخدم' ?></td>
          <td><?= !empty($u['banned']) ? '🚫 محظور' : '✅ نشط' ?></td>
          <td>
            <button type="button" class="btn-mini" onclick="msgTo(<?= $u['id'] ?>)">📩 رسالة</button>
            <?php if ($u['role'] !== 'admin'): ?>
            <form method="post" style="display:inline">
              <button class="btn-mini <?= !empty($u['banned']) ? '' : 'danger' ?>" name="toggle_ban" value="<?= $u['id'] ?>">
                <?= !empty($u['banned']) ? 'فك الحظر' : 'حظر' ?>
              </button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <script>
  function filterUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    document.querySelectorAll('#usersTable tr').forEach((row, i) => {
      if (i === 0) return;
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
  function msgTo(id) {
    const u = document.getElementById('msgUser');
    const allBox = document.querySelector('input[name="msg_all"]');
    if (allBox) allBox.checked = false;
    u.value = id;
    u.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('msgBody').focus();
  }
  </script>

<?php elseif ($tab === 'support'):
  $suid = (int)($_GET['support_user'] ?? 0);
  if ($suid > 0):
    // فتح محادثة مستخدم محدد + تعليم رسائله كمقروءة
    db()->prepare("UPDATE support_messages SET read_admin=1 WHERE user_id=? AND sender='user'")->execute([$suid]);
    $su = db()->prepare("SELECT name,email FROM users WHERE id=?"); $su->execute([$suid]); $suUser = $su->fetch(PDO::FETCH_ASSOC);
    $cv = db()->prepare("SELECT * FROM support_messages WHERE user_id=? ORDER BY id ASC"); $cv->execute([$suid]);
    $msgs = $cv->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <a href="?tab=support" class="btn-mini">← رجوع لكل المحادثات</a>
    <h3 style="margin-top:10px">محادثة مع <?= e($suUser['name'] ?? ('#'.$suid)) ?> <span class="muted small">(#<?= $suid ?>)</span></h3>
    <div class="support-chat">
      <?php if (!$msgs): ?><p class="empty">ما في رسائل.</p><?php endif; ?>
      <?php foreach ($msgs as $m): ?>
        <div class="sc-msg <?= $m['sender'] === 'admin' ? 'admin' : 'user' ?>">
          <div class="sc-bubble">
            <div class="sc-who"><?= $m['sender'] === 'admin' ? '🧑‍💼 أنت (الدعم)' : '👤 '.e($suUser['name'] ?? 'الزبون') ?></div>
            <?= nl2br(e($m['body'])) ?>
            <div class="sc-time"><?= e($m['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="support_user_id" value="<?= $suid ?>">
      <textarea name="support_body" placeholder="اكتب ردّك هون..." rows="3" required style="width:100%"></textarea>
      <button class="btn" name="support_reply" value="1" style="margin-top:10px">إرسال الرد 🎧</button>
    </form>
  </div>
  <?php else:
    // قائمة كل محادثات الدعم
    $convos = db()->query("SELECT s.user_id, u.name, u.email, MAX(s.id) AS last_id,
        SUM(CASE WHEN s.sender='user' AND s.read_admin=0 THEN 1 ELSE 0 END) AS unread
        FROM support_messages s LEFT JOIN users u ON u.id = s.user_id
        GROUP BY s.user_id, u.name, u.email ORDER BY last_id DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>الرد عبر تلجرام ✈️</h3>
    <p class="muted">فعّل هالخيار مرة وحدة، وبعدها لما يوصلك طلب دعم على تلجرام، <b>اعمل Reply على الرسالة واكتب ردّك</b> — والرد بيوصل الزبون عالموقع. (أو اكتب: <code>رقم_المستخدم: نص الرد</code>).</p>
    <form method="post">
      <button class="btn" name="tg_setup" value="1">🔗 تفعيل الرد من تلجرام</button>
    </form>
  </div>
  <div class="card">
    <h3>محادثات الدعم 🎧 (<?= count($convos) ?>)</h3>
    <p class="muted">لما الزبون يطلب التواصل مع الدعم من المساعد الذكي، بتظهر محادثته هون. اضغط "فتح" للرد.</p>
    <?php if (!$convos): ?><p class="empty">ما في طلبات دعم بعد.</p><?php else: ?>
    <table class="tbl">
      <tr><th>#</th><th>الاسم</th><th>الإيميل</th><th>غير مقروء</th><th>إجراء</th></tr>
      <?php foreach ($convos as $c): ?>
        <tr>
          <td><b><?= (int)$c['user_id'] ?></b></td>
          <td><?= e($c['name'] ?? '—') ?></td>
          <td class="small"><?= e($c['email'] ?? '') ?></td>
          <td><?= (int)$c['unread'] > 0 ? '<b style="color:var(--no)">'.(int)$c['unread'].' 🔴</b>' : '—' ?></td>
          <td><a class="btn-mini" href="?tab=support&support_user=<?= (int)$c['user_id'] ?>">فتح المحادثة</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'images'):
  $imgMap = item_images_map();
  $rootCats = [];
  try { $rootCats = fc_content(0)['categories']; } catch (Exception $e) {}
  if (!function_exists('admin_img_row')):
    function admin_img_row($c, $imgMap, $indent = false) {
      $id = (string)$c['id']; $has = isset($imgMap[$id]); ?>
      <div class="img-row"<?= $indent ? ' style="margin-inline-start:26px"' : '' ?>>
        <div class="img-thumb">
          <?php if ($has): ?><img src="/img.php?id=<?= rawurlencode($id) ?>&v=<?= substr(md5($imgMap[$id]),0,6) ?>" alt=""><?php else: ?><span class="muted small">—</span><?php endif; ?>
        </div>
        <div class="img-info"><b><?= e($c['name']) ?></b><br><span class="muted small">ID: <?= e($id) ?></span></div>
        <form method="post" enctype="multipart/form-data" class="img-up">
          <input type="hidden" name="img_item_id" value="<?= e($id) ?>">
          <input type="file" name="img_file" accept="image/*" required>
          <button class="btn-mini" name="save_img" value="1">رفع</button>
        </form>
        <?php if ($has): ?>
        <form method="post" onsubmit="return confirm('حذف صورة هذا القسم؟')">
          <button class="btn-mini danger" name="del_img" value="<?= e($id) ?>">حذف</button>
        </form>
        <?php endif; ?>
      </div>
    <?php }
  endif; ?>
  <div class="card">
    <h3>صور الأقسام والمنتجات 🖼️</h3>
    <p class="muted">ارفع صورة لكل قسم — والمنتجات داخل القسم بتاخد صورتو تلقائياً. الصور بتتخزن بقاعدة البيانات وبتضل بعد التحديث. (الحد الأقصى 2 ميغا للصورة).</p>
    <form method="post" enctype="multipart/form-data" class="inline-form">
      <input name="img_item_id" placeholder="رقم القسم/المنتج (ID)" required>
      <input type="file" name="img_file" accept="image/*" required>
      <button class="btn" name="save_img" value="1">حفظ</button>
    </form>
  </div>
  <div class="card">
    <h3>الأقسام (ارفع صورة لكل قسم)</h3>
    <?php if (!$rootCats): ?><p class="empty">تعذّر جلب الأقسام — تأكد من توكن FastCard.</p><?php endif; ?>
    <?php foreach ($rootCats as $c): ?>
      <?php admin_img_row($c, $imgMap); ?>
      <?php try { $subs = fc_content($c['id'])['categories']; } catch (Exception $e) { $subs = []; }
        foreach ($subs as $s): admin_img_row($s, $imgMap, true); endforeach; ?>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'coupons'):
  $coupons = db()->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>إضافة كود خصم 🎁</h3>
    <p class="muted">كود الخصم بيعطي المستخدم <b>مكافأة إضافية</b> على مبلغ الإيداع. مثلاً 10% يعني لو أودع 100,000 بياخد 110,000.</p>
    <form method="post" class="inline-form">
      <input name="coupon_code" placeholder="الكود مثلاً WELCOME" required style="text-transform:uppercase">
      <select name="coupon_type">
        <option value="percent">نسبة %</option>
        <option value="fixed">مبلغ ثابت ل.س</option>
      </select>
      <input name="coupon_amount" type="number" step="any" placeholder="القيمة" required>
      <input name="coupon_maxuses" type="number" placeholder="حد الاستخدام (0=لا نهائي)">
      <input name="coupon_user" type="number" placeholder="خاص بمستخدم رقم (0=للجميع)">
      <button class="btn" name="add_coupon" value="1">إضافة</button>
    </form>
  </div>
  <div class="card">
    <h3>كود خصم على الأسعار (مربوط بـ ID) 🏷️</h3>
    <p class="muted">هذا الكود <b>بيخصم من سعر الشراء</b>. الزبون بيفعّل الكود من صفحة "كود الخصم"، وبعدها الخصم بينطبق <b>تلقائياً وبشكل دائم على كل مشترياته (كل المنتجات)</b> — وبيشوف السعر القديم مشطوب والسعر الجديد. لإيقافه: اضغط "إيقاف" على الكود. خانة الـ ID اختيارية (للملاحظة فقط).</p>
    <form method="post" class="inline-form">
      <input name="pc_code" placeholder="الكود مثلاً VIP" required style="text-transform:uppercase">
      <select name="pc_type">
        <option value="percent">نسبة %</option>
        <option value="fixed">مبلغ ثابت ل.س</option>
      </select>
      <input name="pc_amount" type="number" step="any" placeholder="قيمة الخصم (مثلاً 10)" required>
      <input name="pc_player" placeholder="ملاحظة/ID (اختياري)">
      <input name="pc_maxuses" type="number" placeholder="حد الاستخدام (0=لا نهائي)">
      <button class="btn" name="add_price_coupon" value="1">إضافة</button>
    </form>
  </div>
  <div class="card">
    <h3>الأكواد (<?= count($coupons) ?>)</h3>
    <?php if (!$coupons): ?><p class="empty">ما في أكواد بعد.</p><?php else: ?>
    <table class="tbl">
      <tr><th>الكود</th><th>النوع</th><th>القيمة</th><th>الاستخدام</th><th>الحالة</th><th>إجراء</th></tr>
      <?php foreach ($coupons as $c): $isPrice = ($c['scope'] ?? 'wallet') === 'price'; ?>
        <tr>
          <td><b><?= e($c['code']) ?></b></td>
          <td>
            <?php if ($isPrice): ?>
              🏷️ سعر<?= trim((string)($c['player_id'] ?? '')) !== '' ? '<br><span class="muted small">ID: ' . e($c['player_id']) . '</span>' : '<br><span class="muted small">أي ID</span>' ?>
            <?php else: ?>
              💰 محفظة<?= (int)($c['user_id'] ?? 0) ? '<br><span class="muted small">مستخدم #' . (int)$c['user_id'] . '</span>' : '' ?>
            <?php endif; ?>
          </td>
          <td><?= disc_label($c['type'], $c['amount']) ?></td>
          <td><?= $c['used'] ?><?= $c['max_uses'] > 0 ? '/'.$c['max_uses'] : '' ?></td>
          <td><?= $c['active'] ? '✅ فعّال' : '⛔ موقوف' ?></td>
          <td style="white-space:nowrap">
            <form method="post" style="display:inline"><button class="btn-mini" name="toggle_coupon" value="<?= $c['id'] ?>"><?= $c['active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('حذف الكود؟')"><button class="btn-mini danger" name="del_coupon" value="<?= $c['id'] ?>">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'slides'):
  $slides = db()->query("SELECT * FROM slides ORDER BY sort ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC); ?>
  <div class="card">
    <h3>إضافة صورة للسلايدر 🖼</h3>
    <p class="muted">حط رابط صورة (URL) — يظهر بالرئيسية. ممكن تضيف رابط يفتح عند الضغط (اختياري).</p>
    <form method="post">
      <label>رابط الصورة</label>
      <input name="slide_image" placeholder="https://..." required>
      <label>رابط عند الضغط (اختياري)</label>
      <input name="slide_link" placeholder="https://... أو /index.php?page=products&cat=...">
      <label>الترتيب</label>
      <input name="slide_sort" type="number" value="0">
      <button class="btn full" name="add_slide" value="1">إضافة الصورة</button>
    </form>
  </div>
  <div class="card">
    <h3>الصور (<?= count($slides) ?>)</h3>
    <?php if (!$slides): ?><p class="empty">ما في صور بعد.</p><?php else: ?>
      <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
        <?php foreach ($slides as $s): ?>
          <div style="position:relative">
            <img src="<?= e($s['image']) ?>" style="width:100%;border-radius:10px;border:1px solid var(--border)" alt="">
            <form method="post" onsubmit="return confirm('حذف الصورة؟')" style="margin-top:6px">
              <button class="btn-mini danger full" name="del_slide" value="<?= $s['id'] ?>">حذف</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'idverify'):
  $idvs = db()->query("SELECT v.*, u.name AS uname, u.email AS uemail FROM id_verifications v LEFT JOIN users u ON u.id = v.user_id WHERE v.status='pending' ORDER BY v.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
  <div class="card">
    <h3>🪪 طلبات توثيق الهوية</h3>
    <?php if (!$idvs): ?>
      <p class="empty">ما في طلبات توثيق معلّقة.</p>
    <?php else: ?>
      <div class="idv-grid">
        <?php foreach ($idvs as $v): ?>
          <div class="idv-card">
            <div class="idv-user">
              <b><?= e($v['uname'] ?? 'مستخدم') ?></b>
              <span class="muted small"><?= e($v['uemail'] ?? '') ?></span>
              <span class="muted small">رقم الحساب: <?= (int)$v['user_id'] ?></span>
            </div>
            <div class="idv-imgs">
              <a href="<?= e($v['image']) ?>" target="_blank">
                <img src="<?= e($v['image']) ?>" class="idv-img" alt="الوجه الأمامي">
                <span class="idv-cap">أمامي</span>
              </a>
              <?php if (!empty($v['image_back'])): ?>
              <a href="<?= e($v['image_back']) ?>" target="_blank">
                <img src="<?= e($v['image_back']) ?>" class="idv-img" alt="الوجه الخلفي">
                <span class="idv-cap">خلفي</span>
              </a>
              <?php endif; ?>
            </div>
            <div class="idv-actions">
              <form method="post" style="flex:1">
                <button class="btn-mini full" name="approve_id" value="<?= (int)$v['id'] ?>">✅ موافقة</button>
              </form>
              <form method="post" style="flex:1" onsubmit="return confirm('رفض هذا الطلب؟')">
                <button class="btn-mini danger full" name="reject_id" value="<?= (int)$v['id'] ?>">❌ رفض</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'phoneverify'):
  $pvs = db()->query("SELECT o.*, u.name AS uname, u.email AS uemail FROM otp_codes o LEFT JOIN users u ON u.id = o.user_id WHERE o.status='pending' ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
  <div class="card">
    <h3>📱 طلبات توثيق الأرقام</h3>
    <p class="muted small">راجع رسائل واتساب على رقمك (<?= e(wa_verify_number()) ?>). إذا وصلتك رسالة من الزبون فيها نفس الرمز، اضغط موافقة.</p>
    <?php if (!$pvs): ?>
      <p class="empty">ما في طلبات توثيق أرقام معلّقة.</p>
    <?php else: ?>
      <div class="pv-list">
        <?php foreach ($pvs as $p): ?>
          <div class="pv-card">
            <div class="pv-info">
              <b><?= e($p['uname'] ?? 'مستخدم') ?></b>
              <span class="muted small"><?= e($p['uemail'] ?? '') ?></span>
              <span class="pv-row">📱 الرقم: <b dir="ltr"><?= e($p['phone']) ?></b></span>
              <span class="pv-row">🔑 الرمز المتوقع: <b class="pv-code"><?= e($p['code']) ?></b></span>
            </div>
            <div class="pv-actions">
              <form method="post" style="flex:1">
                <button class="btn-mini full" name="approve_phone" value="<?= (int)$p['id'] ?>">✅ موافقة</button>
              </form>
              <form method="post" style="flex:1" onsubmit="return confirm('رفض هذا الطلب؟')">
                <button class="btn-mini danger full" name="reject_phone" value="<?= (int)$p['id'] ?>">❌ رفض</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'promo'):
  $pActive = setting('promo_active','0') === '1';
  $pType = setting('promo_type','banner');
  $pValue = setting('promo_value','0');
  $pTitle = setting('promo_title','عرض خاص');
  $pEnd = setting('promo_end','');
  $endText = ($pEnd !== '' && (int)$pEnd > 0) ? date('Y-m-d H:i', (int)$pEnd) : 'يدوي (بدون وقت نهاية)';
  $isLive = promo_get() !== null; ?>
  <div class="card">
    <h3>🎉 العرض بوقت محدود</h3>
    <p class="muted small">الحالة الآن:
      <?php if ($isLive): ?><b style="color:var(--ok,#22c55e)">🟢 يعمل الآن</b> — ينتهي: <?= e($endText) ?>
      <?php elseif ($pActive): ?><b style="color:var(--no,#ef4444)">🔴 مفعّل لكن انتهى وقته</b>
      <?php else: ?><b style="color:var(--muted)">⚫ متوقف</b><?php endif; ?>
    </p>

    <form method="post">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
        <input type="checkbox" name="promo_active" value="1" <?= $pActive ? 'checked' : '' ?> style="width:auto">
        تفعيل العرض
      </label>

      <label>نوع العرض</label>
      <select name="promo_type">
        <option value="discount" <?= $pType==='discount'?'selected':'' ?>>خصم على كل المنتجات (نسبة %)</option>
        <option value="deposit" <?= $pType==='deposit'?'selected':'' ?>>بونص على الإيداع (نسبة %)</option>
        <option value="banner" <?= $pType==='banner'?'selected':'' ?>>بانر إعلاني فقط (بدون خصم)</option>
      </select>

      <label>قيمة النسبة % (للخصم أو البونص)</label>
      <input name="promo_value" type="number" step="any" value="<?= e($pValue) ?>" placeholder="مثال: 20">

      <label>نص العرض (يظهر للزبائن)</label>
      <input name="promo_title" value="<?= e($pTitle) ?>" placeholder="مثال: خصم 20% على كل المنتجات!">

      <label>توقيت انتهاء العرض</label>
      <select name="promo_time_mode" onchange="document.getElementById('pdt').style.display=this.value==='datetime'?'block':'none';document.getElementById('phr').style.display=this.value==='hours'?'block':'none'">
        <option value="manual">يدوي (يضل شغال حتى توقفه)</option>
        <option value="datetime">تاريخ ووقت محدد</option>
        <option value="hours">عدد ساعات من الآن</option>
      </select>
      <div id="pdt" style="display:none;margin-top:8px">
        <input name="promo_end_date" type="datetime-local">
      </div>
      <div id="phr" style="display:none;margin-top:8px">
        <input name="promo_hours" type="number" placeholder="عدد الساعات (مثلاً 24)">
      </div>

      <button class="btn full" name="save_promo" value="1" style="margin-top:14px">حفظ العرض</button>
    </form>
    <form method="post" style="margin-top:8px">
      <button class="btn full ghost" name="stop_promo" value="1">إيقاف العرض فوراً</button>
    </form>
  </div>

<?php else: ?>
  <div class="card">
    <h3>سعر صرف تسعير الألعاب 💱</h3>
    <p class="muted">أسعار FastCard بترجع بالدولار — حط سعر الصرف ليتحول السعر لليرة تلقائياً.</p>
    <p class="muted" style="background:rgba(212,175,55,.1);padding:10px;border-radius:8px;border-right:3px solid var(--accent)">
      💡 <b>للعملة السورية القديمة</b> (الأرقام الكبيرة): حط سعر الدولار الحقيقي بالسوق، مثلاً <b>15000</b>.<br>
      كل الأسعار والمحفظة رح تظهر بالعملة القديمة تلقائياً.
    </p>
    <form method="post" class="inline-form">
      <input name="usd_rate" type="number" step="any" value="<?= e(setting('usd_rate', 11000)) ?>" required>
      <span class="muted">ل.س لكل 1$</span>
      <button class="btn">حفظ</button>
    </form>
    <?php $sp = store_products(); if ($sp): $x = $sp[0]; ?>
      <p class="muted" style="margin-top:10px">
        ✔ مثال للتأكد: "<?= e($x['name']) ?>" — سعر FastCard: <b><?= e($x['cost']) ?>$</b> →
        سعر البيع عندك: <b><?= number_format($x['price']) ?> ل.س</b>
        (<?= e($x['cost']) ?> × <?= e(setting('usd_rate', 11000)) ?> × <?= 1 + (float)setting('profit_percent', DEFAULT_PROFIT) / 100 ?>)
      </p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>سعر صرف شحن شام كاش بالدولار 💵</h3>
    <p class="muted">هذا السعر مستقل عن سعر صرف تسعير الألعاب فوق — يُستخدم فقط عند ما يشحن الزبون محفظته بالدولار عبر شام كاش.</p>
    <p class="muted small" style="background:rgba(212,175,55,.1);padding:10px;border-radius:8px;border-right:3px solid var(--accent)">
      💡 إذا تركت الحقل فارغ أو 0، رح يُستخدم تلقائياً نفس سعر صرف تسعير الألعاب.
    </p>
    <form method="post" class="inline-form">
      <input name="usd_rate_shamcash" type="number" step="any" value="<?= e(setting('usd_rate_shamcash', '')) ?>" placeholder="اتركه فارغ لاستخدام سعر تسعير الألعاب">
      <span class="muted">ل.س لكل 1$ (شام كاش)</span>
      <button class="btn">حفظ</button>
    </form>
    <p class="muted small" style="margin-top:8px">السعر الحالي المُطبَّق فعلياً: <b><?= number_format(usd_rate_shamcash()) ?></b> ل.س لكل 1$</p>
  </div>
  <div class="card">
    <h3>إشعارات تلجرام 📩</h3>
    <p class="muted">يصلك إشعار فوري على تلجرام عند: طلب شراء جديد، إيداع/شحن رصيد، أو رسالة دعم جديدة — مع اسم و<b>ID</b> صاحبها.</p>
    <p class="muted small" style="background:rgba(212,175,55,.1);padding:10px;border-radius:8px;border-right:3px solid var(--accent)">
      💡 لعمل بوت: افتح <b>@BotFather</b> بتلجرام → <b>/newbot</b> → خذ الـ Token.
      ولمعرفة معرّف محادثتك: افتح <b>@userinfobot</b> وأرسل له أي رسالة، رح يعطيك رقم الـ <b>Chat ID</b>.
      وأخيراً ابدأ محادثة مع بوتك (اضغط Start) حتى يقدر يراسلك.
    </p>
    <form method="post">
      <label class="muted small">Bot Token</label>
      <input name="tg_token" type="text" value="<?= e(setting('tg_token', '')) ?>" placeholder="123456789:AAExxxxxxxxxxxxxxxxxxxxxxxx" style="width:100%;margin-bottom:8px">
      <label class="muted small">Chat ID (معرّف محادثتك)</label>
      <input name="tg_chat_id" type="text" value="<?= e(setting('tg_chat_id', '')) ?>" placeholder="مثال: 123456789" style="width:100%;margin-bottom:10px">
      <button class="btn">حفظ الإعدادات</button>
    </form>
    <form method="post" style="margin-top:8px">
      <button class="btn ghost" name="tg_test" value="1">إرسال رسالة اختبار 🧪</button>
    </form>
    <p class="muted small" style="margin-top:8px">الحالة: <b><?= tg_enabled() ? '🟢 مفعّلة' : '⚪ غير مضبوطة' ?></b></p>
  </div>
  <div class="card">
    <h3>🔗 نظام الإحالة</h3>
    <p class="muted">كل مستخدم لديه رابط دعوة خاص. من يدخل عبره ويسجّل: يأخذ هدية فورية + بونص أول شحن، وصاحب الرابط يربح عمولة من كل شحنة يعملها المُحال.</p>
    <form method="post">
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px; cursor:pointer">
        <input type="checkbox" name="ref_enabled" value="1" <?= ref_enabled() ? 'checked' : '' ?> style="width:auto"> تفعيل نظام الإحالة
      </label>
      <label class="muted small">عمولة المُحيل من كل شحنة (%)</label>
      <input name="ref_commission_pct" type="number" step="any" value="<?= e(setting('ref_commission_pct', 5)) ?>" style="width:100%;margin-bottom:8px">
      <label class="muted small">هدية المُحال عند التسجيل (ل.س)</label>
      <input name="ref_signup_gift" type="number" step="any" value="<?= e(setting('ref_signup_gift', 5000)) ?>" style="width:100%;margin-bottom:8px">
      <label class="muted small">بونص أول شحن للمُحال (%)</label>
      <input name="ref_first_topup_pct" type="number" step="any" value="<?= e(setting('ref_first_topup_pct', 15)) ?>" style="width:100%;margin-bottom:10px">
      <button class="btn" name="save_referral" value="1">حفظ إعدادات الإحالة</button>
    </form>
  </div>
  <div class="card">
    <h3>📢 نافذة قناة الواتساب</h3>
    <p class="muted">نافذة ترحيبية تظهر لكل زائر عند فتح الموقع، تدعوه للانضمام لقناتك على واتساب. تظهر في كل مرة يفتح فيها الموقع.</p>
    <?php if (wa_popup_on() && wa_popup_link()): ?>
      <p class="muted small" style="background:rgba(40,199,111,.1);padding:10px;border-radius:8px;border-right:3px solid #28c76f">🟢 النافذة مفعّلة وتظهر للزوّار.</p>
    <?php else: ?>
      <p class="muted small" style="background:rgba(150,150,150,.1);padding:10px;border-radius:8px;border-right:3px solid #888">⚪ النافذة غير مفعّلة<?= wa_popup_on() && !wa_popup_link() ? ' (أضف رابط القناة لتظهر)' : '' ?>.</p>
    <?php endif; ?>
    <form method="post">
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px; cursor:pointer">
        <input type="checkbox" name="wa_popup_on" value="1" <?= wa_popup_on() ? 'checked' : '' ?> style="width:auto"> تفعيل النافذة
      </label>
      <label class="muted small">رابط قناة/مجموعة الواتساب</label>
      <input name="wa_popup_link" type="text" value="<?= e(setting('wa_popup_link', '')) ?>" placeholder="https://chat.whatsapp.com/..." style="width:100%;margin-bottom:8px">
      <label class="muted small">نص الدعوة</label>
      <textarea name="wa_popup_text" rows="3" style="width:100%;margin-bottom:10px"><?= e(wa_popup_text()) ?></textarea>
      <button class="btn" name="save_wa_popup" value="1">حفظ إعدادات النافذة</button>
    </form>
  </div>
  <div class="card">
    <h3>🎨 ألوان الموقع (الثيم)</h3>
    <p class="muted">غيّر لون هوية موقعك حسب المناسبة أو ذوقك. اختر ثيماً جاهزاً أو خصّص لونك بنفسك.</p>
    <?php
      $presets = [
        ['ذهبي (الافتراضي)', '#ffb938', '#7c5cff'],
        ['أحمر ملكي', '#ff4757', '#8e44ad'],
        ['أزرق سماوي', '#3498db', '#2c3e50'],
        ['أخضر زمردي', '#2ecc71', '#16a085'],
        ['برتقالي ناري', '#ff7f0e', '#c0392b'],
        ['وردي عصري', '#ff6b9d', '#6c5ce7'],
        ['رمضاني', '#d4af37', '#1a5c4a'],
      ];
      $curAccent = theme_accent();
    ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px">
      <?php foreach ($presets as $ps): ?>
        <form method="post" style="margin:0">
          <input type="hidden" name="theme_accent" value="<?= e($ps[1]) ?>">
          <input type="hidden" name="theme_accent2" value="<?= e($ps[2]) ?>">
          <button class="btn" name="save_theme" value="1" style="background:<?= e($ps[1]) ?>;color:#111;<?= strtolower($curAccent)===strtolower($ps[1]) ? 'outline:3px solid #fff;' : '' ?>">
            <?= e($ps[0]) ?><?= strtolower($curAccent)===strtolower($ps[1]) ? ' ✓' : '' ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
    <form method="post">
      <p class="muted small" style="margin-bottom:8px"><b>أو خصّص لونك:</b></p>
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <label class="muted small">اللون الأساسي
          <input name="theme_accent" type="color" value="<?= e(theme_accent()) ?>" style="width:56px;height:40px;vertical-align:middle;margin-inline-start:6px;cursor:pointer">
        </label>
        <label class="muted small">اللون الثانوي
          <input name="theme_accent2" type="color" value="<?= e(theme_accent2()) ?>" style="width:56px;height:40px;vertical-align:middle;margin-inline-start:6px;cursor:pointer">
        </label>
        <button class="btn" name="save_theme" value="1">حفظ اللون المخصص</button>
      </div>
    </form>
    <p class="muted small" style="margin-top:10px">💡 بعد الحفظ، حدّث أي صفحة بالموقع لتشوف اللون الجديد.</p>
  </div>
  <div class="card">
    <h3>🔧 وضع الصيانة</h3>
    <p class="muted">عند التفعيل، يشوف الزوّار صفحة "قيد الصيانة"، بينما تبقى أنت (الأدمن) قادراً على تصفّح الموقع بشكل طبيعي لإكمال عملك.</p>
    <?php if (maintenance_on()): ?>
      <p class="muted small" style="background:rgba(224,0,80,.12);padding:10px;border-radius:8px;border-right:3px solid var(--danger,#e05)">🔴 وضع الصيانة مفعّل حالياً — الموقع مخفي عن الزوّار.</p>
    <?php else: ?>
      <p class="muted small" style="background:rgba(40,199,111,.1);padding:10px;border-radius:8px;border-right:3px solid #28c76f">🟢 الموقع يعمل بشكل طبيعي.</p>
    <?php endif; ?>
    <form method="post">
      <label style="display:flex; align-items:center; gap:8px; margin-bottom:12px; cursor:pointer">
        <input type="checkbox" name="maintenance" value="1" <?= maintenance_on() ? 'checked' : '' ?> style="width:auto"> تفعيل وضع الصيانة
      </label>
      <label class="muted small">رسالة الصيانة المعروضة للزوّار</label>
      <textarea name="maintenance_msg" rows="2" style="width:100%;margin-bottom:10px"><?= e(maintenance_msg()) ?></textarea>
      <button class="btn" name="save_maintenance" value="1">حفظ</button>
    </form>
    <div style="margin-top:12px;padding:10px;background:rgba(212,175,55,.1);border-radius:8px;border-right:3px solid var(--accent)">
      <p class="muted small" style="margin:0 0 6px"><b>🔑 رابط التصفّح أثناء الصيانة:</b> افتحه مرة واحدة ليُحفظ بجهازك، فتتصفّح الموقع بحرية حتى وأنت خارج الحساب.</p>
      <input type="text" readonly onclick="this.select()" value="<?= e(site_url()) ?>/?bypass=<?= e(maintenance_bypass_key()) ?>" style="width:100%;font-size:13px">
      <p class="muted small" style="margin:6px 0 0">للخروج من وضع التصفّح: افتح <b><?= e(site_url()) ?>/?bypass=off</b></p>
    </div>
  </div>
  <div class="card">
    <h3>🔒 تغيير كلمة مرور الأدمن</h3>
    <p class="muted">مهم جداً لأمان موقعك — غيّر كلمة المرور الافتراضية إلى كلمة قوية خاصة بك. لن يتمكن أحد من الدخول للوحة الأدمن بدونها.</p>
    <form method="post">
      <label class="muted small">كلمة المرور الحالية</label>
      <input name="current_pass" type="password" required autocomplete="current-password" style="width:100%;margin-bottom:8px">
      <label class="muted small">كلمة المرور الجديدة (8 أحرف على الأقل)</label>
      <input name="new_pass" type="password" required autocomplete="new-password" style="width:100%;margin-bottom:8px">
      <label class="muted small">تأكيد كلمة المرور الجديدة</label>
      <input name="confirm_pass" type="password" required autocomplete="new-password" style="width:100%;margin-bottom:10px">
      <button class="btn" name="change_admin_pass" value="1">تغيير كلمة المرور</button>
    </form>
    <p class="muted small" style="margin-top:8px">💡 نصيحة: استخدم مزيجاً من حروف وأرقام ورموز، ولا تشاركها مع أحد.</p>
  </div>
  <div class="card">
    <h3>هامش الربح</h3>
    <form method="post" class="inline-form">
      <input name="profit_percent" type="number" step="any" value="<?= e(setting('profit_percent', DEFAULT_PROFIT)) ?>" required>
      <span class="muted">% فوق سعر FastCard</span>
      <button class="btn">حفظ</button>
    </form>
  </div>
  <div class="card">
    <h3>حالة الربط مع FastCard</h3>
    <?php $root = fc_content(0); $np = count(store_products()); ?>
    <p class="muted">
      الأقسام الرئيسية: <b><?= count($root['categories']) ?></b> —
      إجمالي المنتجات: <b><?= $np ?></b><br>
      <?= count($root['categories']) ? '✅ النظام الشجري شغال (content API)' : '⚠️ ما في أقسام — تأكد من التوكن' ?>
    </p>
  </div>
  <div class="card">
    <h3>مزامنة المنتجات</h3>
    <p class="muted">تُحدَّث المنتجات تلقائياً كل 5 دقائق — أو حدّثها الآن:</p>
    <form method="post"><button class="btn" name="sync_products" value="1">مزامنة الآن 🔄</button></form>
  </div>
  <div class="card">
    <h3>الإعدادات والمتغيرات (Railway)</h3>
    <p class="muted">تُضبط عبر متغيرات البيئة على Railway (صندوق الموقع → Variables):</p>
    <p class="muted small" style="line-height:2">
      <code>FASTCARD_TOKEN</code> — توكن FastCard<br>
      <code>APISYRIA_KEY</code> — التحقق من التحويلات<br>
      <code>SHAMCASH_NUMBER</code> — رقم محفظة شام كاش (لتفعيل الإيداع عبرها)<br>
      <code>BOT_CHECK_URL</code> + <code>CHECK_API_SECRET</code> — التحقق من اسم اللاعب<br>
      <code>GOOGLE_CLIENT_ID</code> + <code>GOOGLE_CLIENT_SECRET</code> + <code>SITE_URL</code> — دخول جوجل
    </p>
    <p class="muted small">
      حالة شام كاش: <?= shamcash_number() ? '✅ مفعّل' : '⚠️ غير مفعّل' ?> —
      حالة دخول جوجل: <?= google_enabled() ? '✅ مفعّل' : '⚠️ غير مفعّل' ?>
    </p>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php';
