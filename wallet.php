<?php
require_once __DIR__ . '/db.php';
require_login();
// منع تخزين صفحة المحفظة بالكاش حتى تظهر التحديثات فوراً للزبون
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$U = current_user();
$msg = ''; $ok = false;

// وضع تشخيص: /wallet.php?apidebug=TXID&m=syriatel  (للأدمن فقط)
if (isset($_GET['apidebug'])) {
    if (!$U || ($U['role'] ?? '') !== 'admin') { http_response_code(403); exit('for admin only'); }
    header('Content-Type: text/plain; charset=utf-8');
    // جلب الحسابات المربوطة: /wallet.php?apidebug=accounts
    if ($_GET['apidebug'] === 'accounts') {
        $url = 'https://apisyria.com/api/v1?' . http_build_query(['resource'=>'accounts','action'=>'list','api_key'=>apisyria_key()]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $res = curl_exec($ch); curl_close($ch);
        echo "حساباتك المربوطة بـ apisyria:\nانسخ account_address الخاص بشام كاش (يبدأ بـ 251aw) وحطّه بمتغير SHAMCASH_TOKEN على Railway\n\n$res";
        exit;
    }
    $txId = preg_replace('/\D/', '', $_GET['apidebug']);
    $method = ($_GET['m'] ?? 'syriatel') === 'shamcash' ? 'shamcash' : 'syriatel';
    $base = 'https://apisyria.com/api/v1';
    if ($method === 'shamcash') {
        $params = ['resource'=>'shamcash','action'=>'find_tx','tx'=>$txId,'account_address'=>shamcash_account(),'api_key'=>apisyria_key()];
    } else {
        $params = ['resource'=>'syriatel','action'=>'find_tx','tx'=>$txId,'gsm'=>syriatel_gsm(),'period'=>'all','api_key'=>apisyria_key()];
    }
    $url = $base . '?' . http_build_query($params);
    $shown = str_replace(urlencode(apisyria_key()), '***KEY***', $url);
    echo "الطريقة: $method\n";
    echo "GSM/Address: " . ($method==='shamcash'?shamcash_account():syriatel_gsm()) . "\n";
    echo "مفتاح apisyria: " . (apisyria_key()!==''?'موجود (طول '.strlen(apisyria_key()).')':'فارغ ❌') . "\n";
    echo "الرابط: $shown\n\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "HTTP: $code\n";
    if ($err) echo "CURL_ERR: $err\n";
    echo "\nالرد:\n$res";
    exit;
}

// التحقق من تحويل (سيرياتيل أو شام كاش) عبر apisyria — حسب التوثيق الرسمي
function verify_tx($txId, $method, &$errOut = null) {
    $base = 'https://apisyria.com/api/v1';
    if ($method === 'shamcash') {
        $params = [
            'resource' => 'shamcash',
            'action'   => 'find_tx',
            'tx'       => $txId,
            'api_key'  => apisyria_key(),
        ];
        $addr = shamcash_account();
        if ($addr !== '') $params['account_address'] = $addr;
        return _apisyria_find($base, $params, $errOut);
    }
    // سيرياتيل: نجرّب كل الأرقام المربوطة بحساب apisyria (رقم أساسي + رقم ثانٍ اختياري)
    $lastErr = null;
    foreach (syriatel_gsms() as $gsm) {
        $params = [
            'resource' => 'syriatel',
            'action'   => 'find_tx',
            'tx'       => $txId,
            'gsm'      => $gsm,
            'period'   => 'all',
            'api_key'  => apisyria_key(),
        ];
        $err = null;
        $amount = _apisyria_find($base, $params, $err);
        if ($amount !== null) return $amount; // لقيناه على أحد الأرقام
        // نحتفظ بأول خطأ حقيقي (غير "غير موجود") لأنه الأهم
        if ($err && $err !== 'notfound' && !$lastErr) $lastErr = $err;
    }
    $errOut = $lastErr ?: 'notfound';
    return null;
}

// ينفّذ استعلام apisyria ويرجع المبلغ إن وُجد التحويل، وإلا null
// $errOut يمتلئ بسبب الفشل الحقيقي (للتشخيص)
function _apisyria_find($base, $params, &$errOut = null) {
    $url = $base . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $res = curl_exec($ch);
    $curlErr = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $curlErr) { $errOut = 'تعذّر الاتصال بخدمة التحقق (' . $curlErr . ')'; return null; }
    $d = json_decode($res, true);
    if (!is_array($d)) { $errOut = 'رد غير مفهوم من خدمة التحقق (HTTP ' . $code . ')'; return null; }
    if (empty($d['success'])) {
        // الخدمة ردّت بفشل — نلتقط رسالتها (مثلاً: مفتاح غير صالح / انتهى الرصيد)
        $apiMsg = $d['message'] ?? ($d['error'] ?? 'سبب غير محدد');
        $errOut = 'خدمة التحقق رفضت الطلب: ' . (is_string($apiMsg) ? $apiMsg : json_encode($apiMsg, JSON_UNESCAPED_UNICODE));
        return null;
    }
    $data = $d['data'] ?? [];
    if (empty($data['found'])) { $errOut = 'notfound'; return null; }
    $tx = $data['transaction'] ?? [];
    $amount = (float)($tx['amount'] ?? 0);
    if ($amount <= 0) { $errOut = 'التحويل موجود لكن مبلغه صفر'; return null; }
    return $amount;
}

// التحقق من كود الخصم وإرجاع نسبة/قيمة الإضافة
function check_coupon($code, $userId) {
    $code = strtoupper(trim($code));
    if ($code === '') return [0, null];
    $st = db()->prepare("SELECT * FROM coupons WHERE code=? AND active=1");
    $st->execute([$code]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return [0, 'كود الخصم غير صحيح'];
    if ($c['max_uses'] > 0 && $c['used'] >= $c['max_uses']) return [0, 'انتهت صلاحية كود الخصم'];
    if (!empty($c['user_id']) && (int)$c['user_id'] !== (int)$userId) {
        return [0, 'هذا الكود خاص بحساب آخر'];
    }
    $st = db()->prepare("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?");
    $st->execute([$c['id'], $userId]);
    if ($st->fetchColumn()) return [0, 'استخدمت هذا الكود مسبقاً'];
    return [$c, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $txId = trim($_POST['tx_id'] ?? '');
    $mRaw = $_POST['method'] ?? 'syriatel';
    $method = in_array($mRaw, ['shamcash', 'usdt', 'syriatel']) ? $mRaw : 'syriatel';
    $currency = ($_POST['currency'] ?? 'syp') === 'usd' ? 'usd' : 'syp';
    $couponCode = trim($_POST['coupon'] ?? '');
    if (!$txId) {
        $msg = 'أدخل رقم عملية التحويل';
    } elseif ($method === 'usdt') {
        // USDT (BEP20): تحقق يدوي — نسجّل طلباً معلّقاً وننبّه الأدمن ليوافق من التلجرام
        $usdtAmount = (float)($_POST['usdt_amount'] ?? 0);
        if ($usdtAmount <= 0) {
            $msg = 'أدخل مبلغ USDT الذي حوّلته';
        } else {
            // ضمان وجود الجدول (حماية إذا لم يُنشأ بعد بسبب كاش الهيكل)
            try {
                db()->exec("CREATE TABLE IF NOT EXISTS usdt_requests (
                    id " . (is_pg() ? "SERIAL PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT") . ",
                    user_id INTEGER, tx_id TEXT, usdt_amount " . (is_pg() ? "DOUBLE PRECISION" : "REAL") . " DEFAULT 0,
                    status TEXT DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT " . (is_pg() ? "NOW()" : "CURRENT_TIMESTAMP") . "
                )");
            } catch (Exception $e) {}

            // فحص تكرار رقم العملية (آمن)
            $dup = false;
            try {
                $st = db()->prepare("SELECT COUNT(*) FROM topups WHERE tx_id=?");
                $st->execute([$txId]);
                if ($st->fetchColumn()) $dup = true;
                $st2 = db()->prepare("SELECT COUNT(*) FROM usdt_requests WHERE tx_id=? AND status IN ('pending','approved')");
                $st2->execute([$txId]);
                if ($st2->fetchColumn()) $dup = true;
            } catch (Exception $e) {}

            if ($dup) {
                $msg = 'رقم العملية هذا مستخدم مسبقاً';
            } else {
                $sypValue = round($usdtAmount * usd_rate_shamcash());
                $reqId = 0;
                try {
                    db()->prepare("INSERT INTO usdt_requests (user_id,tx_id,usdt_amount,status) VALUES (?,?,?,'pending')")
                        ->execute([$U['id'], $txId, $usdtAmount]);
                    $reqId = (int)last_id('usdt_requests');
                } catch (Exception $e) {}
                // الإشعار يُرسل دائماً (حتى لو تعذّر التسجيل) حتى لا يضيع الطلب
                notify_admin("🪙 <b>طلب إيداع USDT (بانتظار موافقتك)</b>\n"
                    . "المستخدم: " . e($U['name']) . " (#" . $U['id'] . ")\n"
                    . "المبلغ: <b>$usdtAmount USDT</b> ≈ " . number_format($sypValue) . " ل.س\n"
                    . "الشبكة: BEP20 (BSC)\n"
                    . "رقم العملية: <code>$txId</code>\n\n"
                    . ($reqId > 0
                        ? "✅ للموافقة وإضافة الرصيد: ردّ على هذه الرسالة بكلمة <code>موافق</code>\n❌ للرفض: ردّ بكلمة <code>رفض</code>\n(رقم الطلب: USDT#$reqId)"
                        : "⚠️ راجع التحويل وأضف الرصيد يدوياً من لوحة الأدمن."));
                $ok = true;
                $msg = 'تم استلام طلبك ✅ تتم إضافة رصيد USDT خلال ساعة إلى 5 ساعات. إذا تجاوز الوقت 5 ساعات، يُرجى التواصل مع الدعم.';
            }
        }
    } else {
        $st = db()->prepare("SELECT COUNT(*) FROM topups WHERE tx_id=?");
        $st->execute([$txId]);
        if ($st->fetchColumn()) {
            $msg = 'رقم العملية هذا مستخدم مسبقاً';
        } else {
            $vErr = null;
            $amount = verify_tx($txId, $method, $vErr);
            if ($amount === null) {
                $dest = $method === 'shamcash' ? shamcash_number() : SYRIATEL_NUMBER;
                if ($vErr && $vErr !== 'notfound') {
                    // خطأ حقيقي بخدمة التحقق (مفتاح/رصيد/اتصال) — نبلّغ الأدمن فوراً
                    notify_admin("⚠️ <b>عطل بخدمة التحقق (apisyria)</b>\nالطريقة: $method\nالسبب: " . e($vErr) . "\nالمستخدم: " . e($U['name']) . " (#" . $U['id'] . ")");
                    $msg = 'تعذّر التحقق من التحويل حالياً بسبب عطل مؤقت في خدمة التحقق. تواصل مع الدعم وسيتم إضافة رصيدك يدوياً.';
                    if (($U['role'] ?? '') === 'admin') $msg .= ' [سبب فني: ' . $vErr . ']';
                } else {
                    $msg = 'لم يتم العثور على التحويل — تأكد من رقم العملية وأن التحويل وصل إلى ' . $dest;
                }
            } else {
                // ------ 🟢 تعديل الحسبة بدقة هنا 🟢 ------
                if ($method === 'shamcash' && $currency === 'usd') {
                    // إذا كان الشحن بالدولار، نضربه مباشرة بسعر صرف شام كاش الخاص (بدون إضافة أصفار)
                    $amount = round($amount * usd_rate_shamcash());
                } else {
                    // إذا كان الشحن بالليرة السورية (سيرياتيل أو شام كاش سوري)، نضربه بـ 100 لإضافة الـ 00 للعملة القديمة
                    $amount = $amount * 100;
                }
                // ----------------------------------------
                
                $bonus = 0; $couponObj = null; $couponMsg = null;
                if ($couponCode !== '') {
                    [$couponObj, $couponMsg] = check_coupon($couponCode, $U['id']);
                    if ($couponObj) {
                        if ($couponObj['type'] === 'percent') $bonus = round($amount * $couponObj['amount'] / 100);
                        else $bonus = $couponObj['amount'];
                    }
                }
                $promoBonus = 0;
                $promoPct = promo_deposit_pct();
                if ($promoPct > 0) $promoBonus = round($amount * $promoPct / 100);
                $bonus += $promoBonus;
                // ===== نظام الإحالة =====
                // بونص أول شحن للمُحال (15%) + عمولة المُحيل (5%) — تُحسب من المبلغ الأساسي $amount
                $refFirstBonus = process_referral_topup($U['id'], $amount);
                $bonus += $refFirstBonus;
                $total = $amount + $bonus;
                db()->beginTransaction();
                db()->prepare("INSERT INTO topups (user_id,tx_id,amount,coupon) VALUES (?,?,?,?)")
                    ->execute([$U['id'], $txId, $total, $couponObj ? $couponCode : null]);
                db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")
                    ->execute([$total, $U['id']]);
                if ($couponObj) {
                    db()->prepare("UPDATE coupons SET used = used + 1 WHERE id=?")->execute([$couponObj['id']]);
                    db()->prepare("INSERT INTO coupon_uses (coupon_id,user_id) VALUES (?,?)")
                        ->execute([$couponObj['id'], $U['id']]);
                }
                db()->commit();
                $ok = true;
                $msg = 'تم شحن محفظتك بمبلغ ' . number_format($total) . ' ل.س ✅';
                if ($bonus > 0) {
                    $parts = [];
                    if ($promoBonus > 0) $parts[] = number_format($promoBonus) . ' بونص العرض 🎉';
                    if ($refFirstBonus > 0) $parts[] = number_format($refFirstBonus) . ' بونص أول شحن 🎁';
                    $couponBonus = $bonus - $promoBonus - $refFirstBonus;
                    if ($couponBonus > 0) $parts[] = number_format($couponBonus) . ' كود الخصم 🎁';
                    $msg .= ' (منها ' . implode(' + ', $parts) . ')';
                } elseif ($couponMsg) $msg .= ' — ملاحظة: ' . $couponMsg;
                $U = current_user();
                notify_user($U['id'], 'تم شحن محفظتك 💰', 'أُضيف ' . number_format($total) . ' ل.س' . ($bonus > 0 ? ' (منها ' . number_format($bonus) . ' مكافأة)' : '') . ' لمحفظتك.', '💰');
                $methodName = $method === 'shamcash' ? 'شام كاش' : ($method === 'usdt' ? 'USDT (BEP20)' : 'سيرياتيل كاش');
                notify_admin("💰 <b>إيداع جديد</b>\nالمستخدم: " . e($U['name']) . " (#" . $U['id'] . ")\nالمبلغ: " . number_format($total) . " ل.س\nالطريقة: " . $methodName . "\nرقم العملية: $txId");
            }
        }
    }
}

$hasSham = shamcash_number() !== '';
$hasUsdt = usdt_bep20_address() !== '';
$pageTitle = 'المحفظة';
include __DIR__ . '/header.php'; ?>

<div class="wallet-wrap">
  <div class="card balance-card">
    <div class="muted">رصيد محفظتك</div>
    <div class="big-balance bal-amount-big" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> <span>ل.س</span></div>
  </div>

  <div class="card">
    <h3>شحن المحفظة</h3>

    <div class="pay-methods">
      <button type="button" class="pay-method active" data-method="syriatel" onclick="selectMethod(this)">
        <span class="pm-icon">📱</span>
        <span class="pm-name">سيرياتيل كاش</span>
      </button>
      <?php if ($hasSham): ?>
      <button type="button" class="pay-method" data-method="shamcash" onclick="selectMethod(this)">
        <span class="pm-icon">💳</span>
        <span class="pm-name">شام كاش</span>
      </button>
      <?php endif; ?>
      <?php if ($hasUsdt): ?>
      <button type="button" class="pay-method" data-method="usdt" onclick="selectMethod(this)">
        <span class="pm-icon">₮</span>
        <span class="pm-name">USDT</span>
      </button>
      <?php endif; ?>
    </div>

    <div class="pay-box" id="box-syriatel">
      <ol class="steps">
        <li>حوّل المبلغ <b>حصراً عبر "التحويل اليدوي"</b> في تطبيق سيرياتيل كاش، إلى أحد الأرقام التالية:
          <br><b class="copyable" onclick="copyText('<?= SYRIATEL_NUMBER ?>')"><?= SYRIATEL_NUMBER ?> 📋</b>
          <br><b class="copyable" onclick="copyText('0939126779')">0939126779 📋</b></li>
        <li>⚠️ إذا حوّلت رصيد على أحد الأرقام التالية يُخصم <b>5%</b> من المبلغ.</li>
        <li>بعد التحويل، أدخل رقم عملية التحويل بالأسفل، أو تواصل معنا عبر الدعم ليتم إضافة الرصيد.</li>
      </ol>
    </div>

    <?php if ($hasSham): ?>
    <div class="pay-box" id="box-shamcash" style="display:none">
      <ol class="steps">
        <li>حوّل المبلغ المطلوب إلى محفظة شام كاش:
          <b class="copyable" onclick="copyText('<?= e(shamcash_number()) ?>')"><?= e(shamcash_number()) ?> 📋</b></li>
        <li>اختر عملة تحويلك (سوري أو دولار)</li>
        <li>أدخل رقم عملية التحويل بالأسفل</li>
        <li>سيُضاف المبلغ تلقائياً بعد التحقق</li>
      </ol>
      <div class="cur-choice">
        <span class="cur-lbl">عملة التحويل:</span>
        <div class="cur-btns">
          <button type="button" class="cur-btn active" data-cur="syp" onclick="selectCur(this)">🇸🇾 ليرة سورية</button>
          <button type="button" class="cur-btn" data-cur="usd" onclick="selectCur(this)">💵 دولار</button>
        </div>
        <p class="muted small" id="curNote" style="display:none;margin-top:6px">سيُحوّل مبلغ الدولار لليرة حسب سعر الصرف الحالي (<?= number_format(usd_rate_shamcash()) ?> ل.س للدولار).</p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($hasUsdt): ?>
    <div class="pay-box" id="box-usdt" style="display:none">
      <ol class="steps">
        <li>حوّل مبلغ <b>USDT</b> إلى العنوان التالي:
          <br><b class="copyable" onclick="copyText('<?= e(usdt_bep20_address()) ?>')" style="word-break:break-all;font-size:13px"><?= e(usdt_bep20_address()) ?> 📋</b></li>
        <li>⚠️ <b style="color:var(--danger,#e05)">الشبكة: BEP20 (BSC) حصراً!</b> — أي تحويل على شبكة غير BEP20 (مثل TRC20 أو ERC20) سيؤدي إلى <b>ضياع المبلغ نهائياً</b> ولا يمكن استرجاعه، وتكون المسؤولية على المُرسِل.</li>
        <li>المبلغ يُحسب بالدولار ويُحوّل لليرة حسب سعر الصرف الحالي (<?= number_format(usd_rate_shamcash()) ?> ل.س لكل 1$).</li>
        <li>بعد التحويل، أدخل رقم العملية (Transaction Hash / TxID) بالأسفل، أو تواصل مع الدعم ليُضاف رصيدك.</li>
        <li>⏱️ التحقق <b>يدوي</b>: تتم إضافة الرصيد خلال <b>ساعة إلى 5 ساعات</b>. إذا تجاوز الوقت 5 ساعات، تواصل مع <a href="/support.php" style="color:var(--accent)">الدعم</a>.</li>
      </ol>
    </div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="alert <?= $ok ? 'ok' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" id="payForm">
      <input type="hidden" name="method" id="payMethod" value="syriatel">
      <input type="hidden" name="currency" id="payCurrency" value="syp">
      <div id="usdtAmountRow" style="display:none">
        <label>المبلغ الذي حوّلته (USDT) 💵</label>
        <input type="number" step="any" name="usdt_amount" id="usdtAmount" placeholder="مثال: 10" style="width:100%">
      </div>
      <label id="txLabel">رقم عملية التحويل</label>
      <input name="tx_id" id="txField" placeholder="مثال: 600123456789">
      <div id="couponRow">
        <label>كود البونص (اختياري) 🎁</label>
        <input name="coupon" placeholder="إذا عندك كود بونص، اكتبه هون">
      </div>
      <button class="btn full" type="submit" id="payBtn">تحقق وشحن</button>
    </form>
    <p class="muted small" style="margin-top:10px; text-align:center">
      أو فعّل كود الخصم من <a href="/coupon.php" style="color:var(--accent)">صفحة الأكواد 🎁</a>
    </p>
  </div>
</div>

<script>
function selectMethod(btn) {
  document.querySelectorAll('.pay-method').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const m = btn.dataset.method;
  document.getElementById('payMethod').value = m;
  document.querySelectorAll('.pay-box').forEach(b => b.style.display = 'none');
  const box = document.getElementById('box-' + m);
  if (box) box.style.display = '';
  if (m !== 'shamcash') {
    document.getElementById('payCurrency').value = 'syp';
  }
  // تخصيص واجهة النموذج حسب الطريقة
  const isUsdt = (m === 'usdt');
  const amtRow = document.getElementById('usdtAmountRow');
  const couponRow = document.getElementById('couponRow');
  const txLabel = document.getElementById('txLabel');
  const txField = document.getElementById('txField');
  const payBtn = document.getElementById('payBtn');
  const usdtAmount = document.getElementById('usdtAmount');
  if (amtRow) amtRow.style.display = isUsdt ? '' : 'none';
  if (couponRow) couponRow.style.display = isUsdt ? 'none' : '';
  if (usdtAmount) usdtAmount.required = isUsdt;
  if (txLabel) txLabel.textContent = isUsdt ? 'رقم العملية (Transaction Hash / TxID)' : 'رقم عملية التحويل';
  if (txField) txField.placeholder = isUsdt ? 'الصق رقم العملية (TxID)' : 'مثال: 600123456789';
  if (payBtn) payBtn.textContent = isUsdt ? '📤 إرسال طلب للأدمن' : 'تحقق وشحن';
}
function validatePay() {
  const method = document.getElementById('payMethod').value;
  const tx = (document.getElementById('txField').value || '').trim();
  if (!tx) { alert('الرجاء إدخال رقم العملية'); return false; }
  if (method === 'usdt') {
    const amt = parseFloat(document.getElementById('usdtAmount').value || '0');
    if (!amt || amt <= 0) { alert('الرجاء إدخال مبلغ USDT الذي حوّلته'); return false; }
  }
  return true;
}
function selectCur(btn) {
  document.querySelectorAll('.cur-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const c = btn.dataset.cur;
  document.getElementById('payCurrency').value = c;
  const note = document.getElementById('curNote');
  if (note) note.style.display = (c === 'usd') ? 'block' : 'none';
}
</script>

<style>
.cur-choice { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border, rgba(255,255,255,.1)); }
.cur-lbl { font-size: .85rem; color: var(--muted, #888); display: block; margin-bottom: 8px; }
.cur-btns { display: flex; gap: 8px; }
.cur-btn {
  flex: 1; padding: 10px; border-radius: 10px;
  border: 1px solid var(--border, rgba(255,255,255,.15));
  background: var(--card2, rgba(255,255,255,.04)); color: var(--text, #fff);
  font-size: .88rem; font-weight: 700; cursor: pointer; transition: all .2s;
}
.cur-btn.active {
  background: var(--accent, #d4af37); color: #1a1a1a;
  border-color: var(--accent, #d4af37);
}
</style>

<?php include __DIR__ . '/footer.php';
