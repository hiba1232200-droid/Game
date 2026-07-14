<?php
require_once __DIR__ . '/fastcard_api.php';
$page = $_GET['page'] ?? 'home';
$U = current_user();

/* ===== المفضلة (toggle عبر AJAX) ===== */
if (($_GET['action'] ?? '') === 'fav') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$U) { echo json_encode(['ok' => false, 'login' => true]); exit; }
    $pid = (string)($_GET['pid'] ?? '');
    $st = db()->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND product_id=?");
    $st->execute([$U['id'], $pid]);
    if ($st->fetchColumn()) {
        db()->prepare("DELETE FROM favorites WHERE user_id=? AND product_id=?")->execute([$U['id'], $pid]);
        echo json_encode(['ok' => true, 'fav' => false]);
    } else {
        db()->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?,?)")->execute([$U['id'], $pid]);
        echo json_encode(['ok' => true, 'fav' => true]);
    }
    exit;
}

function user_favs($U) {
    if (!$U) return [];
    $st = db()->prepare("SELECT product_id FROM favorites WHERE user_id=?");
    $st->execute([$U['id']]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

/* ===== كرت منتج (مثل FastCard: صورة + اسم + سعر + غير متوفر + قلب) ===== */
function fc_img($file, $cls) {
    if (!$file) return false;
    if (preg_match('#^https?://#', $file)) { $src = $file; $alts = ''; }
    else {
        $base = 'https://fastcard1.store/uploads/';
        $first = (strpos($file, 'cat_') === 0) ? 'categories/' : 'products/';
        $src = $base . $first . $file;
        $alts = e($base . $file);
    }
    echo '<img class="' . $cls . '" src="' . e($src) . '" alt="" loading="lazy"'
       . ($alts ? ' onerror="if(!this.dataset.t){this.dataset.t=1;this.src=\'' . $alts . '\'}else{this.style.display=\'none\';this.insertAdjacentHTML(\'afterend\',\'<div class=ph>🎮</div>\')}"'
                : ' onerror="this.style.display=\'none\';this.insertAdjacentHTML(\'afterend\',\'<div class=ph>🎮</div>\')"')
       . '>';
    return true;
}

function needs_verify($p, $ctx = '') {
    $t = mb_strtolower($p['name'] . ' ' . $p['category'] . ' ' . $ctx);
    foreach (['ببجي', 'pubg', 'شدة', 'شدات', 'فري فاير', 'free fire', 'freefire', 'uc '] as $k)
        if (mb_strpos($t, $k) !== false) return true;
    if (preg_match('/\d+\s*uc\b|\buc\s*\d+/i', $t)) return true;
    return false;
}

function product_card($p, $favs, $ctx = '') {
    $isFav = in_array((string)$p['id'], $favs);
    $label = $p['params'][0] ?? '';
    // سعر العرض = سعر الكمية الدنيا (مهم للمنتجات ذات سعر الوحدة الصغير مثل amount/رصيد)
    $qm = max(1, (int)$p['qty_min']);
    $unitSmall = ($p['type'] ?? '') === 'amount' || $p['price'] < 100; // سعر الوحدة صغير
    $showFrom = ($qm > 1) || $unitSmall;
    $displayPrice = $showFrom ? round($p['price'] * $qm) : $p['price'];
    // إذا كانت الكمية الدنيا 1 لكن سعر الوحدة صغير، نعرض السعر كما هو بدون "من"
    $prefix = ($qm > 1) ? 'من ' : ''; ?>
    <div class="card product-card <?= $p['available'] ? '' : 'oos' ?>"
         data-id="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
         data-price="<?= e($p['price']) ?>" data-desc="<?= e($p['desc']) ?>"
         data-param="<?= e($label) ?>" data-qmin="<?= e($p['qty_min']) ?>" data-qmax="<?= e($p['qty_max']) ?>"
         data-type="<?= e($p['type'] ?? '') ?>" data-category="<?= e($p['category'] ?? '') ?>"
         data-verify="<?= (needs_verify($p, $ctx) && !empty($p['params'])) ? '1' : '0' ?>"
         onclick="openBuy(this)">
      <button class="fav-btn <?= $isFav ? 'on' : '' ?>" onclick="toggleFav(event, '<?= e($p['id']) ?>', this)">❤</button>
      <?php $pimg = item_img_url($p['id']) ?: ($GLOBALS['cur_cat_img'] ?? null);
            if ($pimg): ?><img src="<?= e($pimg) ?>" alt="" loading="lazy"><?php else: ?><div class="ph"></div><?php endif; ?>
      <div class="p-name"><?= e($p['name']) ?></div>
      <?php $disc = promo_discount_pct(); if ($disc > 0 && $p['available']):
        $newPrice = $displayPrice * (1 - $disc/100); ?>
        <div class="p-price-wrap">
          <span class="p-price-old"><?= fmt_price($displayPrice) ?></span>
          <span class="p-price discounted"><?= $prefix ?><?= fmt_price($newPrice) ?></span>
        </div>
        <span class="p-disc-badge">-<?= rtrim(rtrim(number_format($disc,1),'0'),'.') ?>%</span>
      <?php else: ?>
        <div class="p-price"><?= $prefix ?><?= fmt_price($displayPrice) ?></div>
      <?php endif; ?>
      <?php if (!$p['available']): ?><div class="oos-badge">غير متوفر حالياً ❌</div><?php endif; ?>
    </div>
<?php }

/* ===== صفحات ثابتة ===== */
if ($page === 'about' || $page === 'terms') {
    $pageTitle = $page === 'about' ? 'من نحن' : 'سياسة الاسترجاع';
    include __DIR__ . '/header.php'; ?>
    <section class="static-page">
      <?php if ($page === 'about'): ?>
        <h1>من نحن</h1>
        <p><?= e(STORE_NAME) ?> — وجهتك لشحن الألعاب والبطاقات الرقمية بسرعة وأمان.</p>
        <ul class="checks">
          <li>✔ تسليم فوري خلال دقائق</li>
          <li>✔ دعم فني على مدار الساعة</li>
          <li>✔ منتجات أصلية 100%</li>
          <li>✔ أسعار منافسة</li>
        </ul>
      <?php else: ?>
        <h1>سياسة الاسترجاع</h1>
        <p>في حال حدوث مشكلة في تنفيذ الطلب، يُعاد المبلغ كاملاً إلى محفظتك تلقائياً. للمساعدة تواصل معنا عبر واتساب.</p>
      <?php endif; ?>
    </section>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة المفضلة ===== */
/* ===== صفحة البحث ===== */
if ($page === 'search') {
    $q = trim($_GET['q'] ?? '');
    $minP = (isset($_GET['min']) && $_GET['min'] !== '') ? (float)$_GET['min'] : null;
    $maxP = (isset($_GET['max']) && $_GET['max'] !== '') ? (float)$_GET['max'] : null;
    $sortBy = $_GET['sort'] ?? '';
    $favs = user_favs($U);
    $results = [];
    if ($q !== '' && mb_strlen($q) >= 2) {
        $ql = mb_strtolower($q);
        foreach (store_products() as $p) {
            if (mb_strpos(mb_strtolower($p['name']), $ql) !== false
                || mb_strpos(mb_strtolower($p['category']), $ql) !== false) {
                // فلترة بالسعر
                if ($minP !== null && $p['price'] < $minP) continue;
                if ($maxP !== null && $p['price'] > $maxP) continue;
                $results[] = $p;
            }
        }
        // الترتيب
        if ($sortBy === 'price_asc') usort($results, fn($a,$b) => $a['price'] <=> $b['price']);
        elseif ($sortBy === 'price_desc') usort($results, fn($a,$b) => $b['price'] <=> $a['price']);
        elseif ($sortBy === 'name') usort($results, fn($a,$b) => strcmp($a['name'], $b['name']));
    }
    $pageTitle = 'بحث';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">🔍 بحث عن منتج</h1>
    <form method="get" class="search-form-adv">
      <input type="hidden" name="page" value="search">
      <div class="sf-row">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="اكتب اسم المنتج أو القسم..." autofocus>
        <button class="btn" type="submit">بحث</button>
      </div>
      <div class="sf-filters">
        <div class="sf-price">
          <span class="sf-lbl">السعر (ل.س):</span>
          <input type="number" name="min" value="<?= e($_GET['min'] ?? '') ?>" placeholder="من" inputmode="numeric">
          <span>—</span>
          <input type="number" name="max" value="<?= e($_GET['max'] ?? '') ?>" placeholder="إلى" inputmode="numeric">
        </div>
        <select name="sort" class="sf-sort">
          <option value="">الترتيب: الافتراضي</option>
          <option value="price_asc" <?= $sortBy==='price_asc'?'selected':'' ?>>الأرخص أولاً ↑</option>
          <option value="price_desc" <?= $sortBy==='price_desc'?'selected':'' ?>>الأغلى أولاً ↓</option>
          <option value="name" <?= $sortBy==='name'?'selected':'' ?>>أبجدياً</option>
        </select>
      </div>
    </form>
    <?php if ($q !== '' && mb_strlen($q) < 2): ?>
      <p class="empty">اكتب حرفين على الأقل للبحث.</p>
    <?php elseif ($q !== '' && !$results): ?>
      <p class="empty">ما في نتائج لـ "<?= e($q) ?>"<?= ($minP!==null||$maxP!==null) ? ' ضمن نطاق السعر المحدّد' : '' ?> — جرّب كلمة ثانية أو وسّع نطاق السعر.</p>
    <?php elseif ($results): ?>
      <p class="muted" style="margin-bottom:14px"><?= count($results) ?> نتيجة لـ "<?= e($q) ?>"</p>
      <div class="grid products-grid"><?php foreach (array_slice($results, 0, 60) as $p) product_card($p, $favs); ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

if ($page === 'favs') {
    $favs = user_favs($U);
    $products = array_values(array_filter(store_products(), fn($p) => in_array((string)$p['id'], $favs)));
    $pageTitle = 'المفضلة';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">المفضلة ❤</h1>
    <?php if (!$U): ?><p class="empty"><a href="/auth.php">سجّل دخول</a> لاستخدام المفضلة.</p>
    <?php elseif (!$products): ?><p class="empty">ما في منتجات بالمفضلة بعد — اضغط ❤ على أي منتج لإضافته.</p>
    <?php else: ?>
      <div class="grid products-grid"><?php foreach ($products as $p) product_card($p, $favs); ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة عجلة الحظ ===== */
if ($page === 'wheel') {
    $wheelActive = setting('wheel_active', '1') === '1';
    $pageTitle = 'عجلة الحظ';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">🎡 عجلة الحظ</h1>
    <?php if (!$U): ?>
      <p class="empty"><a href="/auth.php">سجّل دخول</a> للعب عجلة الحظ المجانية كل يوم.</p>
    <?php elseif (!$wheelActive): ?>
      <p class="empty">عجلة الحظ غير متاحة حالياً، تابعنا قريباً 🎁</p>
    <?php else: ?>
      <p class="muted" style="text-align:center;margin-bottom:18px">لُف العجلة مرة كل يوم واربح رصيد مجاني! 🎁</p>
      <div class="wheel-wrap">
        <div class="wheel-pointer"></div>
        <svg class="wheel" id="wheel" viewBox="0 0 200 200">
          <!-- 6 قطاعات، كل واحد 60 درجة. القطاع 0 يبدأ من الأعلى -->
          <g id="wheelG">
            <!-- s0: 0-60 -->
            <path d="M100,100 L100,5 A95,95 0 0,1 182.3,52.5 Z" fill="#e74c3c"/>
            <!-- s1: 60-120 -->
            <path d="M100,100 L182.3,52.5 A95,95 0 0,1 182.3,147.5 Z" fill="#3498db"/>
            <!-- s2: 120-180 -->
            <path d="M100,100 L182.3,147.5 A95,95 0 0,1 100,195 Z" fill="#2ecc71"/>
            <!-- s3: 180-240 -->
            <path d="M100,100 L100,195 A95,95 0 0,1 17.7,147.5 Z" fill="#f39c12"/>
            <!-- s4: 240-300 -->
            <path d="M100,100 L17.7,147.5 A95,95 0 0,1 17.7,52.5 Z" fill="#9b59b6"/>
            <!-- s5: 300-360 -->
            <path d="M100,100 L17.7,52.5 A95,95 0 0,1 100,5 Z" fill="#1abc9c"/>
            <!-- النصوص: بمنتصف كل قطاع -->
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="9" font-weight="bold" transform="rotate(30 100 100)">حظ أوفر</text>
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" transform="rotate(90 100 100)">100</text>
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" transform="rotate(150 100 100)">250</text>
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" transform="rotate(210 100 100)">500</text>
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="11" font-weight="bold" transform="rotate(270 100 100)">1000</text>
            <text x="100" y="38" text-anchor="middle" fill="#fff" font-size="10" font-weight="bold" transform="rotate(330 100 100)">2500</text>
          </g>
          <circle cx="100" cy="100" r="95" fill="none" stroke="#d4af37" stroke-width="5"/>
        </svg>
        <div class="wheel-center">🎁</div>
      </div>
      <div class="wheel-action">
        <button class="btn full" id="spinBtn" onclick="spinWheel()">🎡 لُف العجلة</button>
        <div id="wheelMsg" class="alert" style="display:none;margin-top:12px"></div>
        <div id="wheelTimer" class="muted small" style="text-align:center;margin-top:10px;display:none"></div>
      </div>
    <?php endif; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة السلة ===== */
if ($page === 'cart') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $cart = $_SESSION['cart'] ?? [];
    $favs = user_favs($U);
    $items = []; $total = 0;
    $disc = promo_discount_pct();
    foreach ($cart as $key => $c) {
        $p = store_product($c['product_id']);
        if (!$p) continue;
        $unit = $p['price'];
        if ($disc > 0) $unit = round($p['price'] * (1 - $disc/100));
        $sub = $unit * $c['qty'];
        $total += $sub;
        $items[] = ['key'=>$key,'name'=>$p['name'],'qty'=>$c['qty'],'player_id'=>$c['player_id'],'unit'=>$unit,'subtotal'=>$sub,'available'=>$p['available']];
    }
    $pageTitle = 'سلة المشتريات';
    include __DIR__ . '/header.php'; ?>
    <h1 class="section-title">🛒 سلة المشتريات</h1>
    <?php if (!$items): ?>
      <p class="empty">سلتك فارغة — تصفّح <a href="/index.php">الأقسام</a> وأضف منتجات.</p>
    <?php else: ?>
      <div class="cart-list" id="cartList">
        <?php foreach ($items as $it): ?>
          <div class="cart-item" data-key="<?= e($it['key']) ?>">
            <div class="ci-info">
              <b><?= e($it['name']) ?></b>
              <?php if ($it['player_id']): ?><span class="muted small">ID: <?= e($it['player_id']) ?></span><?php endif; ?>
              <?php if (!$it['available']): ?><span class="ci-unavail">⚠️ غير متوفر حالياً</span><?php endif; ?>
              <span class="ci-unit"><?= number_format($it['unit']) ?> ل.س / وحدة</span>
            </div>
            <div class="ci-qty">
              <button onclick="cartQty('<?= e($it['key']) ?>',-1)">−</button>
              <span class="ci-qnum"><?= $it['qty'] ?></span>
              <button onclick="cartQty('<?= e($it['key']) ?>',1)">+</button>
            </div>
            <div class="ci-sub"><b class="ci-subval"><?= number_format($it['subtotal']) ?></b> ل.س</div>
            <button class="ci-del" onclick="cartRemove('<?= e($it['key']) ?>')" title="حذف">🗑</button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="cart-footer">
        <div class="cart-total">الإجمالي: <b id="cartTotal"><?= number_format($total) ?></b> ل.س</div>
        <button class="btn full" id="checkoutBtn" onclick="cartCheckout()">إتمام الشراء 💳</button>
      </div>
      <div id="cartMsg" class="alert" style="display:none;margin-top:12px"></div>
    <?php endif; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== صفحة قسم: content/{id} → أقسام فرعية + منتجات (نفس FastCard) ===== */
if ($page === 'products') {
    $cat = $_GET['cat'] ?? '0';
    // توريث الصورة: صورة القسم نفسه، وإلا الصورة الموروثة من قسم أعلى (تنتقل عبر الرابط)
    $ownImg = item_img_url($cat);
    $GLOBALS['cur_cat_img'] = $ownImg ?: item_img_url($_GET['img'] ?? '');
    $GLOBALS['cur_img_id']  = $ownImg ? (string)$cat : (string)($_GET['img'] ?? '');
    $content = fc_content($cat);
    $subs = $content['categories'];
    $products = $content['products'];
    $favs = user_favs($U);
    $catName = $_GET['name'] ?? 'المنتجات';
    $pageTitle = $catName;
    include __DIR__ . '/header.php'; ?>

    <h1 class="section-title"><?= e($catName) ?></h1>

    <?php if ($subs): ?>
      <div class="grid cats-grid">
        <?php foreach ($subs as $c):
          $cOwn = item_img_url($c['id']);
          $cShow = $cOwn ?: ($GLOBALS['cur_cat_img'] ?? null);
          $cPass = $cOwn ? (string)$c['id'] : (string)($GLOBALS['cur_img_id'] ?? ''); ?>
          <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?><?= $cPass !== '' ? '&img=' . urlencode($cPass) : '' ?>">
            <?php if ($cShow): ?><img class="cat-img" src="<?= e($cShow) ?>" alt="" loading="lazy"><?php else: ?><div class="cat-icon"></div><?php endif; ?>
            <div class="cat-name"><?= e($c['name']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($products): ?>
      <?php
      // ترتيب المنتجات
      $sort = $_GET['sort'] ?? '';
      if ($sort === 'price_asc') usort($products, fn($a,$b) => $a['price'] <=> $b['price']);
      elseif ($sort === 'price_desc') usort($products, fn($a,$b) => $b['price'] <=> $a['price']);
      $catQ = urlencode($cat); $nameQ = urlencode($catName);
      ?>
      <?php if ($subs): ?><h2 class="section-title">المنتجات</h2><?php endif; ?>
      <div class="sort-bar">
        <span class="sort-label">ترتيب:</span>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>" class="sort-btn <?= $sort===''?'on':'' ?>">الافتراضي</a>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>&sort=price_asc" class="sort-btn <?= $sort==='price_asc'?'on':'' ?>">الأرخص ↑</a>
        <a href="/index.php?page=products&cat=<?= $catQ ?>&name=<?= $nameQ ?>&sort=price_desc" class="sort-btn <?= $sort==='price_desc'?'on':'' ?>">الأغلى ↓</a>
      </div>
      <div class="grid products-grid"><?php foreach ($products as $p) product_card($p, $favs, $catName); ?></div>
    <?php elseif (!$subs): ?>
      <p class="empty">لا توجد منتجات في هذا القسم حالياً.</p>
    <?php endif; ?>

    <?php include __DIR__ . '/buy_modal.php'; ?>
    <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
    <?php include __DIR__ . '/footer.php'; exit;
}

/* ===== الرئيسية: content/0 → الأقسام الرئيسية ===== */
$root = fc_content(0);
$slides = db()->query("SELECT * FROM slides WHERE active=1 ORDER BY sort ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'الرئيسية';
include __DIR__ . '/header.php'; ?>

<?php if ($slides): ?>
<!-- النص فوق الصورة (شريط منفصل) -->
<div class="slider-topbar">
  <span class="cap-line">⚡ تسليم فوري ودعم 24/7</span>
  <span class="cap-dot">•</span>
  <span class="cap-line">💰 أفضل الأسعار وأسرع خدمة</span>
</div>
<div class="slider" id="slider">
  <div class="slides" id="slides">
    <?php foreach ($slides as $s): ?>
      <?php if ($s['link']): ?><a href="<?= e($s['link']) ?>" class="slide"><img src="<?= e($s['image']) ?>" alt="" loading="lazy"></a>
      <?php else: ?><div class="slide"><img src="<?= e($s['image']) ?>" alt="" loading="lazy"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?>
  <div class="slider-dots" id="sliderDots">
    <?php foreach ($slides as $i => $s): ?><button class="<?= $i === 0 ? 'on' : '' ?>" onclick="goSlide(<?= $i ?>)"></button><?php endforeach; ?>
  </div>
  <script>
  let slideIdx = 0; const slideCount = <?= count($slides) ?>;
  function goSlide(i) {
    slideIdx = (i + slideCount) % slideCount;
    document.getElementById('slides').style.transform = 'translateX(' + (slideIdx * 100) + '%)';
    document.querySelectorAll('#sliderDots button').forEach((d, j) => d.classList.toggle('on', j === slideIdx));
  }
  setInterval(() => goSlide(slideIdx + 1), 4500);
  </script>
  <?php endif; ?>
</div>
<?php else: ?>
<!-- لو ما في صور بالسلايدر، نعرض شريط بسيط فيه النص -->
<div class="mini-banner">
  <div class="slider-caption"><span class="cap-line">⚡ تسليم فوري ودعم 24/7</span><span class="cap-dot">•</span><span class="cap-line">💰 أفضل الأسعار وأسرع خدمة</span></div>
</div>
<?php endif; ?>

<!-- بانر العرض بوقت محدود -->
<?php $promo = promo_get(); if ($promo): ?>
<div class="promo-banner" <?= $promo['end'] > 0 ? 'data-end="'.$promo['end'].'"' : '' ?>>
  <div class="promo-icon">🎉</div>
  <div class="promo-text">
    <div class="promo-title"><?= e($promo['title']) ?></div>
    <?php if ($promo['end'] > 0): ?><div class="promo-timer" id="promoTimer">⏳ <span></span></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- شريط البحث الرئيسي (بالعرض، فوق الأقسام) -->
<div class="home-search-wrap">
<form method="get" action="/index.php" class="home-search" autocomplete="off">
  <input type="hidden" name="page" value="search">
  <span class="hs-icon">🔍</span>
  <input type="text" name="q" id="homeSearchInput" placeholder="ابحث عن لعبة، شحن، بطاقة..." autocomplete="off">
  <button type="submit" class="hs-btn">بحث</button>
</form>
<div class="search-suggest" id="searchSuggest"></div>
</div>

<h2 class="section-title">الأقسام</h2>
<?php if (!$root['categories'] && !$root['products']): ?>
  <p class="empty">لم يتم تحميل المنتجات بعد — تأكد من توكن FastCard في الإعدادات.</p>
<?php endif; ?>
<div class="grid cats-grid">
  <?php foreach ($root['categories'] as $c): $cimg = item_img_url($c['id']); ?>
    <a class="card cat-card" href="/index.php?page=products&cat=<?= urlencode($c['id']) ?>&name=<?= urlencode($c['name']) ?><?= $cimg ? '&img=' . urlencode($c['id']) : '' ?>">
      <?php if ($cimg): ?><img class="cat-img" src="<?= e($cimg) ?>" alt="" loading="lazy"><?php else: ?><div class="cat-icon"></div><?php endif; ?>
      <div class="cat-name"><?= e($c['name']) ?></div>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($root['products']): $favs = user_favs($U); ?>
  <h2 class="section-title">منتجات</h2>
  <div class="grid products-grid"><?php foreach ($root['products'] as $p) product_card($p, $favs); ?></div>
  <?php include __DIR__ . '/buy_modal.php'; ?>
  <script>const IS_LOGGED = <?= $U ? 'true' : 'false' ?>;</script>
<?php endif; ?>

<section class="features">
  <div class="feat">⚡ تسليم فوري</div>
  <div class="feat">🛡 منتجات أصلية</div>
  <div class="feat">💬 دعم 24/7</div>
  <div class="feat">💰 أسعار منافسة</div>
</section>

<?php include __DIR__ . '/footer.php';
