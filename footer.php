</main>
<footer class="footer">
  <div><?= e(STORE_NAME) ?> © <?= date('Y') ?> — جميع الحقوق محفوظة</div>
  <div class="foot-links">
    <?php if (WHATSAPP_1): ?><a href="<?= e(WHATSAPP_1) ?>" target="_blank">واتساب</a><?php endif; ?>
    <?php if (WHATSAPP_GROUP): ?><a href="<?= e(WHATSAPP_GROUP) ?>" target="_blank">مجموعة الواتساب</a><?php endif; ?>
    <?php if (INSTAGRAM): ?><a href="<?= e(INSTAGRAM) ?>" target="_blank">انستغرام</a><?php endif; ?>
  </div>
</footer>
<script src="/app.js?v=13" defer></script>
<script src="/cyber-fx.js?v=1" defer></script>
</body>
</html>
<?php /* تتبّع الطلبات تلقائياً بالخلفية لو المستخدم مسجّل دخول */ ?>
<?php if (current_user()): ?>
<script>
(function () {
  // تتبّع الطلبات المعلّقة كل 35 ثانية
  function trackOrders() {
    fetch('/track.php', { credentials: 'same-origin' }).catch(function(){});
  }
  setTimeout(trackOrders, 8000);
  setInterval(trackOrders, 35000);

  // ===== إشعارات المتصفح =====
  var swReg = null;
  // تسجيل Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').then(function (reg) {
      swReg = reg;
    }).catch(function(){});
  }

  // طلب صلاحية الإشعارات (بلطف - بعد 5 ثواني من فتح الموقع)
  function askNotifPermission() {
    if (!('Notification' in window)) return;
    if (Notification.permission === 'default') {
      // نطلب الصلاحية فقط مرة واحدة
      var asked = false;
      try { asked = localStorage.getItem('notif_asked') === '1'; } catch(e){}
      if (!asked) {
        Notification.requestPermission().then(function(){
          try { localStorage.setItem('notif_asked', '1'); } catch(e){}
        });
      }
    }
  }
  setTimeout(askNotifPermission, 5000);

  // عرض إشعار متصفح
  function showBrowserNotif(title, body) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    // عبر Service Worker (أفضل) أو مباشرة
    if (swReg && swReg.active) {
      swReg.active.postMessage({ type: 'notify', title: title, body: body, url: '/notifications.php' });
    } else {
      try { new Notification(title, { body: body, icon: '/logo.svg', dir: 'rtl' }); } catch(e){}
    }
  }

  // صوت تنبيه قصير (يفشل بصمت إذا منعه المتصفح)
  function beep() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var o = ctx.createOscillator(), g = ctx.createGain();
      o.connect(g); g.connect(ctx.destination);
      o.type = 'sine'; o.frequency.value = 880;
      g.gain.setValueAtTime(0.0001, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.45);
      o.start(); o.stop(ctx.currentTime + 0.47);
    } catch (e) {}
  }

  // تنبيه داخل الصفحة (يشتغل دائماً بدون إذن)
  function showInAppToast(title, body) {
    var t = document.createElement('div');
    t.className = 'app-toast';
    t.innerHTML = '<div class="at-icon">🔔</div><div class="at-text"><div class="at-title"></div><div class="at-body"></div></div>';
    t.querySelector('.at-title').textContent = title;
    t.querySelector('.at-body').textContent = body || '';
    t.onclick = function () { location.href = '/notifications.php'; };
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { if (t.parentNode) t.remove(); }, 450);
    }, 6000);
    beep();
  }

  // تحديث جرس الإشعارات + عرض إشعار متصفح للجديد
  var lastNotifId = null;
  var firstCheck = true;
  function updateBell() {
    fetch('/notif_count.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        const badge = document.getElementById('notifBadge');
        if (badge) {
          const bell = document.querySelector('.notif-bell');
          if (d.count > 0) {
            badge.textContent = d.count > 99 ? '99+' : d.count; badge.style.display = '';
            if (bell) bell.classList.add('has-new');
          }
          else { badge.style.display = 'none'; if (bell) bell.classList.remove('has-new'); }
        }
        // إشعار جديد (مش بأول فحص عشان ما نزعج)
        if (d.latest) {
          if (!firstCheck && lastNotifId !== null && d.latest.id !== lastNotifId) {
            showInAppToast(d.latest.title, d.latest.body);  // تنبيه داخل الصفحة (دائماً)
            showBrowserNotif(d.latest.title, d.latest.body); // إشعار متصفح (إذا مسموح)
          }
          lastNotifId = d.latest.id;
        }
        firstCheck = false;
      }).catch(function(){});
  }
  updateBell();
  setInterval(updateBell, 15000);
})();
</script>
<?php endif; ?>
