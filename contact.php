<?php
require_once __DIR__ . '/db.php';
$pageTitle = 'تواصل معنا';
include __DIR__ . '/header.php'; ?>

<div class="contact-wrap">
  <h1 class="section-title">📞 تواصل معنا</h1>
  <p class="muted">فريقنا جاهز لمساعدتك على مدار الساعة. اختر طريقة التواصل المناسبة:</p>

  <div class="contact-cards">
    <a class="contact-card" href="<?= e(WHATSAPP_1) ?>" target="_blank">
      <div class="cc-icon">💬</div>
      <div class="cc-title">واتساب 1</div>
      <div class="cc-sub" dir="ltr"><?= e(WHATSAPP_NUM_1) ?></div>
    </a>

    <a class="contact-card" href="<?= e(WHATSAPP_2) ?>" target="_blank">
      <div class="cc-icon">💬</div>
      <div class="cc-title">واتساب 2</div>
      <div class="cc-sub" dir="ltr"><?= e(WHATSAPP_NUM_2) ?></div>
    </a>

    <?php if (WHATSAPP_GROUP): ?>
    <a class="contact-card" href="<?= e(WHATSAPP_GROUP) ?>" target="_blank">
      <div class="cc-icon">👥</div>
      <div class="cc-title">مجموعة الواتساب</div>
      <div class="cc-sub">آخر العروض والتحديثات</div>
    </a>
    <?php endif; ?>

    <?php if (INSTAGRAM): ?>
    <a class="contact-card" href="<?= e(INSTAGRAM) ?>" target="_blank">
      <div class="cc-icon">📷</div>
      <div class="cc-title">انستغرام</div>
      <div class="cc-sub">تابعنا على انستغرام</div>
    </a>
    <?php endif; ?>

    <a class="contact-card" href="/assistant.php">
      <div class="cc-icon">🤖</div>
      <div class="cc-title">المساعد الذكي</div>
      <div class="cc-sub">إجابات فورية على أسئلتك</div>
    </a>
  </div>

  <div class="card" style="margin-top:18px">
    <h3>معلومات مهمة</h3>
    <ul class="checks">
      <li>⚡ التسليم فوري خلال دقائق على مدار الساعة</li>
      <li>🛡 جميع المنتجات أصلية 100%</li>
      <li>💰 في حال أي مشكلة، يُعاد المبلغ كاملاً لمحفظتك</li>
      <li>🕐 الدعم متاح 24/7</li>
    </ul>
  </div>
</div>

<?php include __DIR__ . '/footer.php';
