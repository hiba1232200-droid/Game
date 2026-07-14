<?php
require_once __DIR__ . '/order_tracker.php';
require_login();
$U = current_user();
$msg = ''; $ok = false;

$vip = user_vip_info($U['id']);

// بيانات الإحالة
$refStats = referral_stats($U['id']);
$refLink = site_url() . '/?ref=' . (int)$U['id'];

// حالة توثيق الهوية
$st = db()->prepare("SELECT status FROM id_verifications WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$U['id']]);
$idvStatus = $st->fetchColumn(); // pending / rejected / approved / false

// حالة توثيق رقم الموبايل (آخر طلب)
$st = db()->prepare("SELECT status FROM otp_codes WHERE user_id=? ORDER BY id DESC LIMIT 1");
$st->execute([$U['id']]);
$otpStatus = $st->fetchColumn(); // pending / false

// سجل النشاط: آخر الطلبات والإيداعات مدمجة
$acts = [];
$st = db()->prepare("SELECT 'order' typ, product_name title, total amount, status, created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 15");
$st->execute([$U['id']]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $acts[] = $r;
$st = db()->prepare("SELECT 'topup' typ, tx_id title, amount, 'accept' status, created_at FROM topups WHERE user_id=? ORDER BY id DESC LIMIT 15");
$st->execute([$U['id']]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $acts[] = $r;
usort($acts, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
$acts = array_slice($acts, 0, 20);

$pageTitle = 'حسابي';
include __DIR__ . '/header.php'; ?>

<div class="account-page">
  <h1 class="section-title">👤 حسابي</h1>

  <!-- بطاقة VIP -->
  <div class="vip-card vip-<?= $vip['level'] ?>">
    <div class="vip-top">
      <div class="vip-badge-big"><?= vip_badge($vip['level']) ?></div>
      <div class="vip-spent">أنفقت: $<?= number_format($vip['spent_usd'], 2) ?></div>
    </div>
    <?php if ($vip['next_level']): ?>
      <?php $th = vip_thresholds(); $target = $th[$vip['next_level']];
        $prog = $target > 0 ? min(100, ($vip['spent_usd'] / $target) * 100) : 0; ?>
      <div class="vip-progress"><div class="vip-progress-bar" style="width:<?= $prog ?>%"></div></div>
      <div class="vip-next">باقي <b>$<?= number_format($vip['need_usd'], 2) ?></b> للوصول إلى <?= vip_badge($vip['next_level']) ?></div>
      <?php if ($vip['level'] >= 2): ?><div class="vip-perk">🎁 تواصل مع الدعم لكود الخصم الخاص بك</div><?php endif; ?>
    <?php else: ?>
      <div class="vip-next">🏆 وصلت لأعلى مستوى!</div>
    <?php endif; ?>
  </div>

  <!-- معلومات الحساب -->
  <div class="card">
    <h3>معلوماتي</h3>
    <div class="acc-info">
      <div><span class="muted">رقم العضوية:</span> <b id="myUserId">#<?= (int)$U['id'] ?></b>
        <button type="button" class="btn-mini" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= (int)$U['id'] ?>');this.textContent='تم النسخ ✓'" style="margin-inline-start:8px">نسخ</button>
      </div>
      <div><span class="muted">الاسم:</span> <b><?= e($U['name']) ?></b></div>
      <div><span class="muted">الإيميل:</span> <b><?= e($U['email']) ?></b></div>
      <div><span class="muted">الرصيد:</span> <b class="bal-amount" data-syp="<?= (int)$U['balance'] ?>"><?= number_format($U['balance']) ?> ل.س</b></div>
    </div>
  </div>

  <?php if (ref_enabled()): ?>
  <div class="card">
    <h3>🔗 ادعُ أصدقاءك واربح</h3>
    <p class="muted">شارك رابطك الخاص. كل صديق يسجّل عبره يحصل على <b><?= number_format(ref_signup_gift()) ?> ل.س</b> فوراً + بونص <b><?= number_format(ref_first_topup_pct()) ?>%</b> على أول شحن، وأنت تربح <b><?= number_format(ref_commission_pct()) ?>%</b> من كل عملية شحن يعملها — مدى الحياة!</p>
    <div class="inline-form" style="margin-top:10px">
      <input type="text" id="refLink" value="<?= e($refLink) ?>" readonly onclick="this.select()" style="flex:1">
      <button type="button" class="btn" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('refLink').value);this.textContent='تم النسخ ✓'">نسخ الرابط</button>
    </div>
    <div class="acc-info" style="margin-top:12px">
      <div><span class="muted">عدد من دعوتهم:</span> <b><?= (int)$refStats['count'] ?></b></div>
      <div><span class="muted">أرباح الإحالة:</span> <b style="color:#28c76f"><?= number_format($refStats['earned']) ?> ل.س</b></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- توثيق رقم الموبايل -->
  <div class="card">
    <h3>📱 رقم الموبايل</h3>
    <?php if (!empty($U['phone_verified'])): ?>
      <div class="phone-verified">
        ✅ رقمك موثّق: <b dir="ltr"><?= e($U['phone']) ?></b>
      </div>
    <?php elseif ($otpStatus === 'pending'): ?>
      <div class="alert" style="display:block">📱 طلب التوثيق قيد المراجعة، رح يوصلك إشعار عند الموافقة.</div>
    <?php else: ?>
      <p class="muted small">وثّق رقم موبايلك عبر واتساب. اضغط الزر، وبيفتحلك واتساب برسالة جاهزة، أرسلها لنا كما هي.</p>
      <div id="otpMsg" class="alert" style="display:none"></div>

      <!-- خطوة 1: إدخال الرقم -->
      <div id="otpStep1">
        <label>رقم الموبايل</label>
        <input id="otpPhone" type="tel" dir="ltr" value="<?= e($U['phone'] ?? '') ?>" placeholder="0991234567">
        <button class="btn full" id="otpStartBtn" onclick="otpStart()">توثيق عبر واتساب</button>
      </div>

      <!-- خطوة 2: فتح واتساب وإرسال -->
      <div id="otpStep2" style="display:none">
        <div class="otp-code-box">
          رمز التحقق الخاص بك: <b id="otpCodeShow">----</b>
        </div>
        <p class="muted small">اضغط الزر لفتح واتساب، ثم <b>أرسل الرسالة كما هي</b> (فيها الرمز). بعد الإرسال اضغط "أرسلت الرسالة".</p>
        <a id="otpWaLink" href="#" target="_blank" class="btn full wa-btn">📲 فتح واتساب وإرسال الرسالة</a>
        <button class="btn full" id="otpSentBtn" onclick="otpSent()" style="margin-top:8px">✅ أرسلت الرسالة</button>
        <button class="btn full ghost" onclick="otpReset()" style="margin-top:8px">تغيير الرقم</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- توثيق الهوية -->
  <div class="card">
    <h3>🪪 توثيق الهوية</h3>
    <?php if (!empty($U['id_verified'])): ?>
      <div class="phone-verified">✅ هويتك موثّقة</div>
    <?php elseif ($idvStatus === 'pending'): ?>
      <div class="alert" style="display:block">🪪 طلب التوثيق قيد المراجعة، رح يوصلك إشعار عند الموافقة.</div>
    <?php else: ?>
      <p class="muted small">
        ارفع صورة واضحة للوجهين الأمامي والخلفي لهويتك أو بطاقتك الشخصية ليتم توثيق حسابك.
        <?= $idvStatus === 'rejected' ? '<br><span style="color:var(--no,#ef4444)">⚠️ طلبك السابق مرفوض، حاول بصور أوضح.</span>' : '' ?>
      </p>
      <div id="idvMsg" class="alert" style="display:none"></div>

      <!-- الوجه الأمامي -->
      <div class="idv-upload-box">
        <label class="idv-label">الوجه الأمامي</label>
        <input type="file" id="idImageFront" accept="image/*" style="display:none" onchange="idPreview(event,'front')">
        <div id="idPreviewFront" class="idv-preview" style="display:none">
          <img id="idPreviewFrontImg">
        </div>
        <button class="btn full ghost" onclick="document.getElementById('idImageFront').click()" id="idPickFront">📷 اختر الصورة الأمامية</button>
      </div>

      <!-- الوجه الخلفي -->
      <div class="idv-upload-box" style="margin-top:12px">
        <label class="idv-label">الوجه الخلفي</label>
        <input type="file" id="idImageBack" accept="image/*" style="display:none" onchange="idPreview(event,'back')">
        <div id="idPreviewBack" class="idv-preview" style="display:none">
          <img id="idPreviewBackImg">
        </div>
        <button class="btn full ghost" onclick="document.getElementById('idImageBack').click()" id="idPickBack">📷 اختر الصورة الخلفية</button>
      </div>

      <button class="btn full" onclick="idUpload()" id="idUploadBtn" style="display:none; margin-top:14px">إرسال للمراجعة</button>
    <?php endif; ?>
  </div>

  <!-- سجل النشاط -->
  <div class="card">
    <h3>📜 سجل النشاط</h3>
    <?php if (!$acts): ?><p class="empty">ما في نشاط بعد.</p><?php else: ?>
      <div class="activity-list">
        <?php foreach ($acts as $a): ?>
          <div class="activity-item">
            <div class="act-icon"><?= $a['typ'] === 'order' ? '🛒' : '💰' ?></div>
            <div class="act-body">
              <div class="act-title"><?= $a['typ'] === 'order' ? e($a['title']) : 'إيداع رصيد' ?></div>
              <div class="act-time"><?= e($a['created_at']) ?></div>
            </div>
            <div class="act-amount <?= $a['typ'] === 'topup' ? 'plus' : '' ?>">
              <?= $a['typ'] === 'topup' ? '+' : '-' ?><?= number_format($a['amount']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php';
