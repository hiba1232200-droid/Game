// زر الرجوع الذكي
function goBack() {
  // إذا فاتح مودال، سكّرو بدل ما ترجع صفحة
  const openModal = document.querySelector('.modal.show');
  if (openModal) { openModal.classList.remove('show'); return; }
  // إذا السايدبار مفتوح، سكّرو
  const sb = document.getElementById('sidebar');
  if (sb && sb.classList.contains('open')) { toggleSidebar(); return; }
  // إذا في صفحة سابقة بنفس الموقع، ارجع لها — وإلا روح للرئيسية
  if (document.referrer && document.referrer.indexOf(location.host) !== -1) {
    history.back();
  } else if (history.length > 1) {
    history.back();
  } else {
    location.href = '/index.php';
  }
}

// السايدبار
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('overlay').classList.toggle('show');
}

// الوضع الداكن/الفاتح
function toggleTheme() {
  const light = document.body.classList.toggle('light');
  document.cookie = 'theme=' + (light ? 'light' : 'dark') + ';path=/;max-age=31536000';
}
(function () {
  if (document.cookie.includes('theme=light')) document.body.classList.add('light');
})();

// تبديل العملة (ل.س / $)
function toggleCurrency() {
  const cur = (typeof CUR !== 'undefined' && CUR === 'usd') ? 'syp' : 'usd';
  document.cookie = 'currency=' + cur + ';path=/;max-age=31536000';
  location.reload();
}
// تنسيق سعر (المخزن دائماً ل.س)
function fmtPrice(syp) {
  if (typeof CUR !== 'undefined' && CUR === 'usd')
    return (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' $';
  return Number(syp).toLocaleString() + ' ل.س';
}

function copyText(t) {
  navigator.clipboard && navigator.clipboard.writeText(t);
}

// تحديث عرض الرصيد حسب العملة المختارة
function updateBalanceDisplay() {
  const usd = (typeof CUR !== 'undefined' && CUR === 'usd');
  document.querySelectorAll('.bal-amount').forEach(function(el) {
    const syp = parseFloat(el.dataset.syp || '0');
    el.textContent = usd
      ? (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' $'
      : Number(syp).toLocaleString() + ' ل.س';
  });
  document.querySelectorAll('.bal-amount-big').forEach(function(el) {
    const syp = parseFloat(el.dataset.syp || '0');
    el.innerHTML = usd
      ? (syp / USD_RATE).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' <span>$</span>'
      : Number(syp).toLocaleString() + ' <span>ل.س</span>';
  });
}
document.addEventListener('DOMContentLoaded', updateBalanceDisplay);

// ===== مودال الشراء =====
let curPrice = 0, qMin = 1, qMax = 0, needVerify = false, verified = false, softPass = false;

function openBuy(card) {
  if (card.classList.contains('oos')) return;
  curPrice = parseFloat(card.dataset.price) || 0;
  qMin = parseInt(card.dataset.qmin) || 1;
  qMax = parseInt(card.dataset.qmax) || 0;
  const pType = card.dataset.type || '';
  // منتجات الباقة المحددة: قائمة كميات بقيم محددة (مثل فاست كارد)
  const fixedQty = (pType === 'specificPackage');
  document.getElementById('mName').textContent = card.dataset.name;
  document.getElementById('mPrice').textContent = fmtPrice(curPrice);
  document.getElementById('mDesc').textContent = card.dataset.desc || '';
  const qty = document.getElementById('mQty');
  const qtyRow = document.getElementById('mQtyRow');
  const qtySelectRow = document.getElementById('mQtySelectRow');
  const qtySelect = document.getElementById('mQtySelect');

  if (fixedQty) {
    // القيم الحقيقية المتوفرة عند فاست كارد لمنتجات الباقة المحددة (رصيد سيرياتيل وغيرها)
    const fixedQtyValues = [1.92,2.88,3.84,4.8,5.76,9.61,20.19,23.07,24.03,25.96,30.76,40.38,45.19,48.07,52.88,62.5,68.26,72.11,77.88,81.73,86.53,96.15,100.96,105.76,115.38,125,130.76,144.23,160.57,163.46,173.07,183.65,192.3,211.53,240.38,288.46,317.3,370.19,432.69,480.76,576.92,625,721.15,769.23,951.92,1057.69,1923.07,2115.38,2403.84];
    qtySelect.innerHTML = '';
    fixedQtyValues.forEach(function(v) {
      const opt = document.createElement('option');
      opt.value = v;
      opt.textContent = v;
      qtySelect.appendChild(opt);
    });
    const startVal = fixedQtyValues[0];
    qtySelect.value = startVal;
    qty.value = startVal; qMin = startVal; qMax = 0;
    if (qtyRow) qtyRow.style.display = 'none';
    if (qtySelectRow) qtySelectRow.style.display = '';
  } else {
    qMin = parseInt(card.dataset.qmin) || 1;
    qMax = parseInt(card.dataset.qmax) || 0;
    // شريط "الكمية" (حقل رقمي عادي يكتب فيه الزبون الرقم مباشرة، متل فاست كارد)
    // يظهر فقط لمنتجات نوعها amount (متابعين/لايكات سوشيال ميديا، وباقات الرصيد المرنة)
    // أي نوع منتج آخر (ألعاب بكل أنواعها مثل ببجي) → كمية ثابتة بلا حقل ظاهر
    const allowQtyField = (pType === 'amount');
    if (allowQtyField) {
      qty.value = qMin; qty.min = qMin;
      if (qMax > 0) qty.max = qMax; else qty.removeAttribute('max');
      const hint = document.getElementById('mQtyHint');
      if (hint) hint.textContent = 'يجب أن لا تقل الكمية عن ' + qMin.toLocaleString()
        + (qMax > 0 ? ' ولا تزيد عن ' + qMax.toLocaleString() : '');
      if (qtyRow) qtyRow.style.display = '';
      if (qtySelectRow) qtySelectRow.style.display = 'none';
    } else {
      // كمية ثابتة على الحد الأدنى المسموح من فاست كارد — بلا حقل ظاهر
      qty.value = qMin;
      if (qtyRow) qtyRow.style.display = 'none';
      if (qtySelectRow) qtySelectRow.style.display = 'none';
    }
  }
  // حقل المعرف حسب متطلبات المنتج من API
  needVerify = card.dataset.verify === '1';
  verified = false; softPass = false;
  const vb = document.getElementById('mVerify');
  vb.style.display = 'none'; vb.textContent = '';
  document.getElementById('mBuyBtn').textContent = needVerify ? 'تحقق من الاسم 🔍' : 'شراء';
  const param = card.dataset.param || '';
  const wrap = document.getElementById('mPlayerWrap');
  if (param) {
    wrap.style.display = '';
    document.getElementById('mPlayerLabel').textContent = param;
    document.getElementById('mPlayer').placeholder = param;
  } else {
    wrap.style.display = 'none';
  }
  document.getElementById('mPlayer').value = '';
  document.getElementById('mMsg').textContent = '';
  document.getElementById('mMsg').className = 'm-msg';
  document.getElementById('buyModal').dataset.pid = card.dataset.id;
  updateTotal();
  document.getElementById('buyModal').classList.add('show');
}
function closeBuy() { document.getElementById('buyModal').classList.remove('show'); }
function onQtySelect() {
  const sel = document.getElementById('mQtySelect');
  const v = parseFloat(sel.value) || qMin;
  document.getElementById('mQty').value = v;
  updateTotal();
}
function getQty() {
  // إذا القائمة المنسدلة ظاهرة، نأخذ قيمتها (قد تكون عشرية)
  const selRow = document.getElementById('mQtySelectRow');
  if (selRow && selRow.style.display !== 'none') {
    return parseFloat(document.getElementById('mQtySelect').value) || qMin;
  }
  return parseFloat(document.getElementById('mQty').value) || qMin;
}
// كل الخصومات الدائمة المفعّلة للمستخدم
function allDiscounts() {
  return (typeof MY_DISCOUNTS !== 'undefined' && Array.isArray(MY_DISCOUNTS)) ? MY_DISCOUNTS : [];
}
// الخصم الدائم المفعّل (ينطبق على كل المنتجات)
function activeDiscount() {
  const ds = allDiscounts();
  return ds.length ? ds[0] : null;
}
// تنسيق الرقم: 10 => "10"، 10.5 => "10.5"
function fmtNum(n) { n = parseFloat(n) || 0; return (n % 1 === 0) ? String(n) : String(Math.round(n * 100) / 100); }
// نص نسبة/قيمة الخصم
function discLabel(d) { return d.type === 'percent' ? (fmtNum(d.amount) + '%') : fmtPrice(parseFloat(d.amount)); }
function discValue(type, amount, total) {
  let v = (type === 'percent') ? total * (amount / 100) : amount;
  if (v > total) v = total;
  return Math.round(v);
}
function updateTotal() {
  const q = getQty();
  const promoPct = (typeof PROMO_DISCOUNT_PCT !== 'undefined' ? parseFloat(PROMO_DISCOUNT_PCT) : 0) || 0;
  // سعر الوحدة بعد خصم العرض العام (إن وجد) — نفس ما تشوفه ببطاقة المنتج
  const unitAfterPromo = promoPct > 0 ? Math.max(1, Math.round(curPrice * (1 - promoPct / 100))) : curPrice;
  const base = Math.round(unitAfterPromo * q); // الإجمالي بعد خصم العرض العام، قبل الخصم الشخصي
  const d = activeDiscount();
  const line  = document.getElementById('mDiscLine');
  const oldT  = document.getElementById('mOldTotal');
  const oldP  = document.getElementById('mOldPrice');
  if (d) {
    const disc  = discValue(d.type, parseFloat(d.amount), base);
    const final = Math.max(1, base - disc);
    document.getElementById('mTotal').textContent = fmtPrice(final);
    // السعر الأصلي المشطوب = سعر الوحدة الأساسي (قبل أي خصم) × الكمية
    oldT && (oldT.style.display = '', oldT.textContent = fmtPrice(Math.round(curPrice * q)));
    // سعر الوحدة: نعرض القديم مشطوب والجديد (يجمع خصم العرض + الخصم الشخصي للنسبة المئوية)
    if (d.type === 'percent') {
      const unitNew = Math.max(1, Math.round(unitAfterPromo * (1 - parseFloat(d.amount) / 100)));
      document.getElementById('mPrice').textContent = fmtPrice(unitNew);
      if (oldP) { oldP.style.display = ''; oldP.textContent = fmtPrice(curPrice); }
    } else {
      document.getElementById('mPrice').textContent = fmtPrice(unitAfterPromo);
      if (oldP) { if (promoPct > 0) { oldP.style.display = ''; oldP.textContent = fmtPrice(curPrice); } else oldP.style.display = 'none'; }
    }
    if (line) {
      line.style.display = '';
      line.className = 'm-disc ok';
      line.textContent = '🎁 خصمك الدائم ' + discLabel(d) + ' — وفّرت ' + fmtPrice(disc);
    }
  } else if (promoPct > 0) {
    // ما في خصم شخصي، بس في خصم عرض عام فعّال
    document.getElementById('mTotal').textContent = fmtPrice(base);
    document.getElementById('mPrice').textContent = fmtPrice(unitAfterPromo);
    if (oldT) { oldT.style.display = ''; oldT.textContent = fmtPrice(Math.round(curPrice * q)); }
    if (oldP) { oldP.style.display = ''; oldP.textContent = fmtPrice(curPrice); }
    if (line) {
      line.style.display = '';
      line.className = 'm-disc ok';
      line.textContent = '🎉 خصم العرض الحالي ' + fmtNum(promoPct) + '%';
    }
  } else {
    document.getElementById('mTotal').textContent = fmtPrice(base);
    document.getElementById('mPrice').textContent = fmtPrice(curPrice);
    if (oldT) oldT.style.display = 'none';
    if (oldP) oldP.style.display = 'none';
    if (line) line.style.display = 'none';
  }
}
document.addEventListener('input', e => { if (e.target.id === 'mQty' || e.target.id === 'mPlayer') updateTotal(); });
document.addEventListener('click', e => { if (e.target.id === 'buyModal') closeBuy(); });

// إعادة ضبط التحقق عند تغيير الـ ID
function resetVerify() {
  if (!needVerify) return;
  verified = false; softPass = false;
  const vb = document.getElementById('mVerify');
  vb.style.display = 'none';
  document.getElementById('mBuyBtn').textContent = 'تحقق من الاسم 🔍';
}

// التحقق من اسم اللاعب (ببجي / فري فاير)
async function verifyName() {
  const modal = document.getElementById('buyModal');
  const btn = document.getElementById('mBuyBtn');
  const vb = document.getElementById('mVerify');
  const player = document.getElementById('mPlayer').value.trim();
  const msg = document.getElementById('mMsg');
  if (!player) { msg.textContent = 'أدخل ID اللاعب أولاً'; msg.className = 'm-msg no'; return; }
  btn.disabled = true;
  msg.className = 'm-msg'; msg.textContent = '';
  vb.style.display = ''; vb.className = 'verify-box'; vb.textContent = 'جارٍ التحقق من الاسم... ⏳';
  try {
    const res = await fetch('/check_name.php?player=' + encodeURIComponent(player) + '&product=' + encodeURIComponent(modal.dataset.pid));
    const d = await res.json();
    if (d.ok) {
      verified = true;
      vb.className = 'verify-box ok';
      vb.textContent = '👤 اسم اللاعب: ' + d.name + ' — إذا الاسم صحيح اضغط شراء';
      btn.textContent = 'شراء ✅';
    } else if (d.soft) {
      softPass = true;
      vb.className = 'verify-box warn';
      vb.textContent = '⚠️ ' + d.msg + ' — تأكد من الـ ID بنفسك ثم اضغط شراء';
      btn.textContent = 'شراء';
    } else {
      vb.className = 'verify-box no';
      vb.textContent = '❌ ' + d.msg;
    }
  } catch (e) {
    softPass = true;
    vb.className = 'verify-box warn';
    vb.textContent = '⚠️ تعذّر التحقق — تأكد من الـ ID بنفسك ثم اضغط شراء';
    btn.textContent = 'شراء';
  }
  btn.disabled = false;
}

async function submitBuy() {
  const modal = document.getElementById('buyModal');
  const msg = document.getElementById('mMsg');
  const btn = document.getElementById('mBuyBtn');

  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) {
    location.href = '/auth.php';
    return;
  }
  // منتجات ببجي/فري فاير: لازم تحقق من الاسم أول
  if (needVerify && !verified && !softPass) { verifyName(); return; }
  // فحص الحد الأدنى/الأقصى للكمية (لمنتجات الحقل الرقمي مثل متابعين السوشيال ميديا)
  const qtyRowEl = document.getElementById('mQtyRow');
  if (qtyRowEl && qtyRowEl.style.display !== 'none') {
    const q = getQty();
    if (q < qMin) { msg.textContent = 'أقل كمية مسموحة: ' + qMin.toLocaleString(); msg.className = 'm-msg no'; return; }
    if (qMax > 0 && q > qMax) { msg.textContent = 'أكبر كمية مسموحة: ' + qMax.toLocaleString(); msg.className = 'm-msg no'; return; }
  }
  btn.disabled = true;
  msg.className = 'm-msg';
  msg.textContent = 'جارٍ إرسال الطلب...';
  try {
    const res = await fetch('/buy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        product_id: modal.dataset.pid,
        qty: getQty(),
        player_id: document.getElementById('mPlayer').value.trim(),
      }),
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    msg.textContent = d.msg + (d.eta ? ' — ' + d.eta : '');
    msg.className = 'm-msg ' + (d.ok ? 'ok' : 'no');
    if (d.ok) setTimeout(() => location.href = '/orders.php', 2600);
  } catch (err) {
    msg.textContent = 'خطأ في الاتصال — حاول مرة ثانية';
    msg.className = 'm-msg no';
  }
  btn.disabled = false;
}

// ===== سلة المشتريات =====
async function addToCart() {
  const modal = document.getElementById('buyModal');
  const msg = document.getElementById('mMsg');
  const btn = document.getElementById('mCartBtn');
  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) { location.href = '/auth.php'; return; }
  // إذا المنتج بيطلب تحقق اسم، لازم يتحقق قبل الإضافة
  if (needVerify && !verified && !softPass) { verifyName(); return; }
  btn.disabled = true;
  const oldTxt = btn.textContent;
  btn.textContent = '...';
  try {
    const res = await fetch('/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add',
        product_id: modal.dataset.pid,
        qty: getQty(),
        player_id: document.getElementById('mPlayer').value.trim(),
      }),
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    msg.textContent = d.msg;
    msg.className = 'm-msg ' + (d.ok ? 'ok' : 'no');
    if (d.ok) { updateCartBadge(d.count); setTimeout(closeBuy, 900); }
  } catch (e) {
    msg.textContent = 'خطأ بالاتصال';
    msg.className = 'm-msg no';
  }
  btn.disabled = false; btn.textContent = oldTxt;
}

async function cartQty(key, delta) {
  const row = document.querySelector('.cart-item[data-key="' + key + '"]');
  if (!row) return;
  const cur = parseInt(row.querySelector('.ci-qnum').textContent) || 1;
  const next = cur + delta;
  if (next < 1) return;
  try {
    const res = await fetch('/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', key: key, qty: next }),
    });
    const d = await res.json();
    if (d.ok) location.reload();
    else { const m = document.getElementById('cartMsg'); if (m) { m.textContent = d.msg; m.className = 'alert no'; m.style.display = 'block'; } }
  } catch (e) {}
}

async function cartRemove(key) {
  try {
    const res = await fetch('/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove', key: key }),
    });
    const d = await res.json();
    if (d.ok) location.reload();
  } catch (e) {}
}

async function cartCheckout() {
  const btn = document.getElementById('checkoutBtn');
  const msg = document.getElementById('cartMsg');
  btn.disabled = true; btn.textContent = 'جارٍ التنفيذ...';
  try {
    const res = await fetch('/cart.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'checkout' }),
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    msg.textContent = d.msg;
    msg.className = 'alert ' + (d.ok ? 'ok' : 'no');
    msg.style.display = 'block';
    if (d.ok) setTimeout(() => location.href = '/orders.php', 2600);
    else { btn.disabled = false; btn.textContent = 'إتمام الشراء 💳'; }
  } catch (e) {
    msg.textContent = 'خطأ بالاتصال، حاول مجدداً';
    msg.className = 'alert no'; msg.style.display = 'block';
    btn.disabled = false; btn.textContent = 'إتمام الشراء 💳';
  }
}

function updateCartBadge(count) {
  const b = document.getElementById('cartBadge');
  if (!b) return;
  if (count > 0) { b.textContent = count > 99 ? '99+' : count; b.style.display = ''; }
  else b.style.display = 'none';
}

// تحديث عداد السلة عند فتح أي صفحة
(function () {
  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) return;
  fetch('/cart.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'count' }), credentials: 'same-origin',
  }).then(r => r.json()).then(d => { if (d.ok) updateCartBadge(d.count); }).catch(function(){});
})();
async function toggleFav(ev, pid, btn) {
  ev.stopPropagation();
  if (typeof IS_LOGGED !== 'undefined' && !IS_LOGGED) { location.href = '/auth.php'; return; }
  try {
    const res = await fetch('/index.php?action=fav&pid=' + encodeURIComponent(pid));
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    if (d.ok) btn.classList.toggle('on', d.fav);
  } catch (e) {}
}

// ===== تسريع التنقل: preload الصفحات عند لمس الرابط =====
(function () {
  const prefetched = new Set();
  function prefetch(url) {
    if (!url || prefetched.has(url) || url.indexOf('#') === 0) return;
    if (url.indexOf(location.origin) !== 0 && url.indexOf('/') !== 0) return;
    prefetched.add(url);
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = url;
    document.head.appendChild(link);
  }
  // عند لمس/تحويم على رابط، نحمّل الصفحة مسبقاً (تفتح فوراً عند النقر)
  ['touchstart', 'mouseover'].forEach(function (evt) {
    document.addEventListener(evt, function (e) {
      const a = e.target.closest('a');
      if (a && a.href) prefetch(a.href);
    }, { passive: true, capture: true });
  });
})();

// ===== عدّاد العرض التنازلي =====
(function () {
  const banner = document.querySelector('.promo-banner[data-end]');
  if (!banner) return;
  const end = parseInt(banner.dataset.end) * 1000;
  const timerEl = document.getElementById('promoTimer');
  if (!timerEl) return;
  const span = timerEl.querySelector('span');
  function tick() {
    const diff = end - Date.now();
    if (diff <= 0) { banner.style.display = 'none'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor(diff % 86400000 / 3600000);
    const m = Math.floor(diff % 3600000 / 60000);
    const s = Math.floor(diff % 60000 / 1000);
    span.textContent = (d > 0 ? d + ' يوم ' : '') + h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  }
  tick();
  setInterval(tick, 1000);
})();

// ===== اقتراحات البحث الذكي =====
(function () {
  const input = document.getElementById('homeSearchInput');
  const box = document.getElementById('searchSuggest');
  if (!input || !box) return;
  let timer = null;
  input.addEventListener('input', function () {
    const q = input.value.trim();
    clearTimeout(timer);
    if (q.length < 2) { box.style.display = 'none'; box.innerHTML = ''; return; }
    timer = setTimeout(function () {
      fetch('/search_suggest.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(list => {
          if (!list.length) { box.style.display = 'none'; return; }
          box.innerHTML = list.map(function (item) {
            return '<a class="suggest-item" href="/index.php?page=search&q=' + encodeURIComponent(item.name) + '">' +
              '<span class="sg-name">' + item.name + '</span>' +
              '<span class="sg-cat">' + (item.cat || '') + '</span></a>';
          }).join('');
          box.style.display = 'block';
        }).catch(function(){ box.style.display = 'none'; });
    }, 250);
  });
  // إخفاء عند الضغط برّا
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.home-search-wrap')) box.style.display = 'none';
  });
})();

// ===== توثيق الموبايل عبر واتساب =====
function otpShowMsg(text, ok) {
  const m = document.getElementById('otpMsg');
  if (!m) return;
  m.textContent = text;
  m.className = 'alert ' + (ok ? 'ok' : '');
  m.style.display = 'block';
}
async function otpStart() {
  const phone = (document.getElementById('otpPhone').value || '').trim();
  if (phone.length < 9) { otpShowMsg('أدخل رقم موبايل صحيح', false); return; }
  const btn = document.getElementById('otpStartBtn');
  btn.disabled = true; btn.textContent = 'جاري التجهيز...';
  try {
    const r = await fetch('/otp.php', {
      method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      credentials: 'same-origin',
      body: 'action=start&phone=' + encodeURIComponent(phone),
    });
    const d = await r.json();
    if (d.ok) {
      document.getElementById('otpCodeShow').textContent = d.code;
      document.getElementById('otpWaLink').href = d.link;
      document.getElementById('otpStep1').style.display = 'none';
      document.getElementById('otpStep2').style.display = 'block';
      const m = document.getElementById('otpMsg'); if (m) m.style.display = 'none';
    } else {
      otpShowMsg(d.msg, false);
    }
  } catch (e) { otpShowMsg('خطأ بالاتصال، حاول مجدداً', false); }
  btn.disabled = false; btn.textContent = 'توثيق عبر واتساب';
}
async function otpSent() {
  const btn = document.getElementById('otpSentBtn');
  btn.disabled = true; btn.textContent = 'جاري الإرسال...';
  try {
    const r = await fetch('/otp.php', {
      method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      credentials: 'same-origin',
      body: 'action=sent',
    });
    const d = await r.json();
    otpShowMsg(d.msg, d.ok);
    if (d.ok) setTimeout(() => location.reload(), 1400);
  } catch (e) { otpShowMsg('خطأ بالاتصال، حاول مجدداً', false); }
  btn.disabled = false; btn.textContent = '✅ أرسلت الرسالة';
}
function otpReset() {
  document.getElementById('otpStep1').style.display = 'block';
  document.getElementById('otpStep2').style.display = 'none';
  const m = document.getElementById('otpMsg'); if (m) m.style.display = 'none';
}

// ===== توثيق الهوية =====
let idFrontData = null;
let idBackData = null;
function idMsg(text, ok) {
  const m = document.getElementById('idvMsg');
  if (!m) return;
  m.textContent = text; m.className = 'alert ' + (ok ? 'ok' : ''); m.style.display = 'block';
}
function idPreview(ev, side) {
  const file = ev.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function (e) {
    const img = new Image();
    img.onload = function () {
      // ضغط الصورة: أقصى عرض 1000px
      const max = 1000;
      let w = img.width, h = img.height;
      if (w > max) { h = h * max / w; w = max; }
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      canvas.getContext('2d').drawImage(img, 0, 0, w, h);
      const data = canvas.toDataURL('image/jpeg', 0.7);
      if (side === 'front') {
        idFrontData = data;
        document.getElementById('idPreviewFrontImg').src = data;
        document.getElementById('idPreviewFront').style.display = 'block';
        document.getElementById('idPickFront').textContent = '📷 تغيير الصورة الأمامية';
      } else {
        idBackData = data;
        document.getElementById('idPreviewBackImg').src = data;
        document.getElementById('idPreviewBack').style.display = 'block';
        document.getElementById('idPickBack').textContent = '📷 تغيير الصورة الخلفية';
      }
      // زر الإرسال يظهر لما الصورتين جاهزتين
      if (idFrontData && idBackData) {
        document.getElementById('idUploadBtn').style.display = 'block';
      }
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}
async function idUpload() {
  if (!idFrontData) { idMsg('اختر الصورة الأمامية أولاً', false); return; }
  if (!idBackData) { idMsg('اختر الصورة الخلفية أولاً', false); return; }
  const btn = document.getElementById('idUploadBtn');
  btn.disabled = true; btn.textContent = 'جاري الإرسال...';
  try {
    const r = await fetch('/verify_id.php', {
      method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      credentials: 'same-origin',
      body: 'image=' + encodeURIComponent(idFrontData) + '&image_back=' + encodeURIComponent(idBackData),
    });
    const d = await r.json();
    idMsg(d.msg, d.ok);
    if (d.ok) setTimeout(() => location.reload(), 1400);
  } catch (e) { idMsg('خطأ بالاتصال، حاول مجدداً', false); }
  btn.disabled = false; btn.textContent = 'إرسال للمراجعة';
}

// ========================================
// نظام الأنميشن الاحترافي — Scroll Reveal
// ========================================
(function() {
  // احترام تفضيل تقليل الحركة
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  // لو المتصفح ما بيدعم IntersectionObserver، نخلي كل شي ظاهر (ما نفعّل الإخفاء)
  if (!('IntersectionObserver' in window)) return;

  function initReveal() {
    const selectors = '.card, .product-card, .cat-card, .order-card, .stat, .section-title, .faq-item, .activity-item, .idv-card, .pv-card';
    const els = document.querySelectorAll(selectors);
    if (!els.length) return;

    // نفعّل وضع الإخفاء فقط الآن (بعد ما تأكدنا إنو JS شغّال)
    document.body.classList.add('js-reveal-on');

    let groupDelay = 0, lastTop = -999;
    els.forEach(function(el) {
      if (el.classList.contains('reveal')) return;
      el.classList.add('reveal');
      const top = el.getBoundingClientRect().top;
      if (Math.abs(top - lastTop) < 40) { groupDelay = Math.min(groupDelay + 1, 6); }
      else { groupDelay = 1; }
      lastTop = top;
      if (groupDelay >= 1 && groupDelay <= 6) el.classList.add('d' + groupDelay);
    });

    const obs = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.05, rootMargin: '0px 0px 80px 0px' });

    els.forEach(function(el) {
      // العناصر القريبة من الشاشة تظهر فوراً
      if (el.getBoundingClientRect().top < window.innerHeight + 100) {
        setTimeout(function(){ el.classList.add('in'); }, 50);
      } else {
        obs.observe(el);
      }
    });

    // أمان إضافي: بعد 2.5 ثانية، أي عنصر لسا مخفي نظهّره
    setTimeout(function() {
      els.forEach(function(el){ if (!el.classList.contains('in')) el.classList.add('in'); });
    }, 2500);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReveal);
  } else {
    initReveal();
  }
})();

// ===== عجلة الحظ =====
async function spinWheel() {
  const btn = document.getElementById('spinBtn');
  const wheel = document.getElementById('wheel');
  const msg = document.getElementById('wheelMsg');
  if (!btn || !wheel) return;
  btn.disabled = true;
  msg.style.display = 'none';
  try {
    const res = await fetch('/wheel.php', {
      method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin', body: 'action=spin',
    });
    const d = await res.json();
    if (d.login) { location.href = '/auth.php'; return; }
    if (!d.ok) {
      msg.textContent = d.msg; msg.className = 'alert no'; msg.style.display = 'block';
      btn.disabled = false;
      if (d.wait) showWheelTimer(d.wait);
      return;
    }
    // عنصر الدوران: مجموعة القطاعات داخل SVG
    const g = document.getElementById('wheelG') || wheel;
    // كل قطاع 60°، منتصف القطاع index عند الزاوية (index*60 + 30)
    // المؤشر بالأعلى (0°). لإيقاف منتصف القطاع تحت المؤشر ندوّر عكسياً
    const segAngle = 60;
    const mid = d.index * segAngle + segAngle / 2;
    const spins = 6; // لفات كاملة قبل الوقوف
    const finalRot = (spins * 360) + (360 - mid);
    g.style.transition = 'transform 4.8s cubic-bezier(.15,.62,.28,1)';
    g.style.transformOrigin = '50% 50%';
    g.style.transform = 'rotate(' + finalRot + 'deg)';
    setTimeout(function () {
      msg.textContent = d.msg;
      msg.className = 'alert ' + (d.value > 0 ? 'ok' : '');
      msg.style.display = 'block';
      if (d.value > 0) {
        const bal = document.querySelector('.bal-amount');
        if (bal) { const cur = parseInt(bal.dataset.syp || '0') + d.value; bal.dataset.syp = cur; bal.textContent = cur.toLocaleString() + ' ل.س'; }
      }
      showWheelTimer(86400);
    }, 5000);
  } catch (e) {
    msg.textContent = 'خطأ بالاتصال، حاول مجدداً'; msg.className = 'alert no'; msg.style.display = 'block';
    btn.disabled = false;
  }
}

function showWheelTimer(secs) {
  const t = document.getElementById('wheelTimer');
  const btn = document.getElementById('spinBtn');
  if (!t) return;
  t.style.display = 'block';
  if (btn) btn.style.display = 'none';
  function tick() {
    if (secs <= 0) { location.reload(); return; }
    const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
    t.textContent = '⏳ الدوران التالي بعد: ' + h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    secs--;
    setTimeout(tick, 1000);
  }
  tick();
}

// عند فتح صفحة العجلة: فحص الحالة
(function () {
  if (!document.getElementById('wheel')) return;
  fetch('/wheel.php?action=status', { credentials: 'same-origin' })
    .then(r => r.json()).then(d => {
      if (d.ok && !d.can_spin && d.wait > 0) showWheelTimer(d.wait);
    }).catch(function(){});
})();
