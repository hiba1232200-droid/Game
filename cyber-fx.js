/* ============================================================
   LUXE CARD — تأثيرات خفيفة (Phase 3)
   مبدأ السلامة: لو تعطّل هذا الملف أو لم يُحمّل إطلاقاً،
   الموقع يبقى شغّالاً بالكامل والمحتوى ظاهراً — لا شيء يعتمد عليه.
   ============================================================ */
(function () {
  "use strict";

  /* ---------- 0) وضع الأداء المخفّف ----------
     يُفعّل تلقائياً على الأجهزة الضعيفة أو عند تفعيل "توفير البيانات".
     يجب أن يعمل مبكراً جداً قبل الرسم، لذلك هو أول شيء في الملف. */
  (function detectLite() {
    try {
      var lite = false;
      var nav = navigator;

      // عدد أنوية المعالج (4 أو أقل = جهاز متواضع)
      if (typeof nav.hardwareConcurrency === "number" && nav.hardwareConcurrency <= 4) lite = true;
      // ذاكرة الجهاز بالجيجابايت
      if (typeof nav.deviceMemory === "number" && nav.deviceMemory <= 4) lite = true;
      // المستخدم مفعّل "توفير البيانات" أو الشبكة بطيئة
      if (nav.connection) {
        if (nav.connection.saveData === true) lite = true;
        var et = nav.connection.effectiveType || "";
        if (et === "slow-2g" || et === "2g" || et === "3g") lite = true;
      }

      if (lite) document.documentElement.classList.add("cy-lite");
    } catch (e) { /* لا شيء — الموقع يعمل عادي */ }
  })();

  /* ---------- 1) إخفاء شاشة التحميل ----------
     ملاحظة: الشاشة تختفي وحدها بالـ CSS حتى لو لم يعمل هذا السكربت،
     وهنا نخفيها أبكر عند اكتمال تحميل الصفحة. */
  function hideLoader() {
    var el = document.getElementById("cyberLoader");
    if (!el) return;
    el.classList.add("is-done");
    setTimeout(function () {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 600);
  }
  if (document.readyState === "complete") {
    hideLoader();
  } else {
    window.addEventListener("load", hideLoader);
    setTimeout(hideLoader, 3000); // أمان إضافي
  }

  /* ---------- 2) الظهور التدريجي عند التمرير ----------
     الافتراضي في CSS أن كل شيء ظاهر. هذا السكربت هو الذي
     "يفعّل" الإخفاء المؤقت ثم يُظهر العناصر عند وصولها للشاشة.
     فإذا لم يعمل، لا يختفي شيء أبداً. */
  function initReveal() {
    if (!("IntersectionObserver" in window)) return;

    // احترام تفضيل تقليل الحركة
    try {
      if (window.matchMedia &&
          window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    } catch (e) {}

    var targets = document.querySelectorAll(
      ".section-title, .cat-card, .product-card, .card"
    );
    if (!targets.length) return;

    var io = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i++) {
        if (entries[i].isIntersecting) {
          entries[i].target.classList.add("cy-in");
          io.unobserve(entries[i].target);
        }
      }
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.05 });

    for (var i = 0; i < targets.length; i++) {
      var t = targets[i];
      // العناصر الظاهرة أصلاً في أول الشاشة تظهر فوراً بلا تأخير
      var r = t.getBoundingClientRect();
      if (r.top < window.innerHeight * 0.9) {
        t.classList.add("cy-reveal", "cy-in");
        continue;
      }
      t.classList.add("cy-reveal");
      io.observe(t);
    }

    // أمان: بعد 6 ثوانٍ أظهر أي عنصر بقي مخفياً لأي سبب
    setTimeout(function () {
      var left = document.querySelectorAll(".cy-reveal:not(.cy-in)");
      for (var j = 0; j < left.length; j++) left[j].classList.add("cy-in");
    }, 6000);
  }

  /* ---------- 3) مؤشر متوهّج (للأجهزة ذات الماوس فقط) ----------
     لا يُنشأ إطلاقاً على الموبايل/اللمس، فتكلفته هناك = صفر. */
  function initCursor() {
    try {
      if (!window.matchMedia) return;
      if (!window.matchMedia("(pointer: fine)").matches) return;
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    } catch (e) { return; }

    var dot = document.createElement("div");
    dot.id = "cyCursor";
    document.body.appendChild(dot);

    var x = 0, y = 0, drawn = false, ticking = false;

    function draw() {
      ticking = false;
      dot.style.transform = "translate3d(" + x + "px," + y + "px,0)";
      if (!drawn) { dot.classList.add("on"); drawn = true; }
    }

    window.addEventListener("mousemove", function (e) {
      x = e.clientX; y = e.clientY;
      if (!ticking) { ticking = true; requestAnimationFrame(draw); }
    }, { passive: true });

    window.addEventListener("mousedown", function () {
      dot.classList.add("tap");
    }, { passive: true });
    window.addEventListener("mouseup", function () {
      dot.classList.remove("tap");
    }, { passive: true });
    document.addEventListener("mouseleave", function () {
      dot.classList.remove("on"); drawn = false;
    });
  }

  /* ---------- 4) زر مشاركة رابط الموقع ----------
     يستخدم قائمة المشاركة الأصلية بالهاتف، وإن لم تتوفر ينسخ الرابط. */
  function initShare() {
    var btn = document.getElementById("shareSiteBtn");
    if (!btn) return;

    function toast(msg) {
      var t = document.createElement("div");
      t.className = "cy-toast";
      t.textContent = msg;
      document.body.appendChild(t);
      setTimeout(function () { t.classList.add("on"); }, 10);
      setTimeout(function () {
        t.classList.remove("on");
        setTimeout(function () {
          if (t.parentNode) t.parentNode.removeChild(t);
        }, 350);
      }, 2200);
    }

    function fallbackCopy(url) {
      // نسخ يدوي يعمل على المتصفحات القديمة أيضاً
      try {
        var ta = document.createElement("textarea");
        ta.value = url;
        ta.setAttribute("readonly", "");
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        var ok = document.execCommand("copy");
        document.body.removeChild(ta);
        toast(ok ? "✅ تم نسخ رابط الموقع" : url);
      } catch (e) {
        toast(url);
      }
    }

    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var url = btn.getAttribute("data-share-url") || window.location.origin;
      var title = btn.getAttribute("data-share-title") || document.title;
      var text = btn.getAttribute("data-share-text") || "";

      if (navigator.share) {
        navigator.share({ title: title, text: text, url: url })
          .catch(function () { /* المستخدم ألغى المشاركة — لا شيء */ });
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url)
          .then(function () { toast("✅ تم نسخ رابط الموقع"); })
          .catch(function () { fallbackCopy(url); });
        return;
      }
      fallbackCopy(url);
    });
  }

  /* ---------- 5) ميلان ثلاثي الأبعاد + أزرار مغناطيسية + أثر المؤشر ----------
     كل ما تحت يعمل على أجهزة الماوس فقط.
     على الموبايل لا يُسجَّل أي مستمع ولا يُنشأ أي عنصر = صفر تكلفة. */
  function initPointerFX() {
    try {
      if (!window.matchMedia) return;
      if (!window.matchMedia("(pointer: fine)").matches) return;
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    } catch (e) { return; }

    /* --- ميلان البطاقات حسب موضع الماوس --- */
    var cards = document.querySelectorAll(".cat-card, .product-card");
    for (var i = 0; i < cards.length; i++) {
      (function (card) {
        var raf = null, rx = 0, ry = 0;
        card.classList.add("cy-tilt");

        function apply() {
          raf = null;
          card.style.transform =
            "perspective(700px) rotateX(" + rx + "deg) rotateY(" + ry + "deg) translateY(-5px)";
        }
        card.addEventListener("mousemove", function (e) {
          var r = card.getBoundingClientRect();
          var px = (e.clientX - r.left) / r.width - 0.5;
          var py = (e.clientY - r.top) / r.height - 0.5;
          ry = px * 9;      // ميلان أفقي
          rx = -py * 9;     // ميلان رأسي
          if (!raf) raf = requestAnimationFrame(apply);
        }, { passive: true });
        card.addEventListener("mouseleave", function () {
          if (raf) { cancelAnimationFrame(raf); raf = null; }
          card.style.transform = "";
        }, { passive: true });
      })(cards[i]);
    }

    /* --- أزرار مغناطيسية: تنجذب قليلاً نحو المؤشر --- */
    var btns = document.querySelectorAll(".btn");
    for (var j = 0; j < btns.length; j++) {
      (function (btn) {
        var raf = null, tx = 0, ty = 0;
        btn.classList.add("cy-magnet");

        function apply() {
          raf = null;
          btn.style.transform = "translate3d(" + tx + "px," + ty + "px,0)";
        }
        btn.addEventListener("mousemove", function (e) {
          var r = btn.getBoundingClientRect();
          tx = ((e.clientX - r.left) / r.width - 0.5) * 10;
          ty = ((e.clientY - r.top) / r.height - 0.5) * 8;
          if (!raf) raf = requestAnimationFrame(apply);
        }, { passive: true });
        btn.addEventListener("mouseleave", function () {
          if (raf) { cancelAnimationFrame(raf); raf = null; }
          btn.style.transform = "";
        }, { passive: true });
      })(btns[j]);
    }

    /* --- أثر المؤشر: 6 نقاط تتبع بتأخير متدرّج --- */
    var COUNT = 6, dots = [], pts = [];
    for (var k = 0; k < COUNT; k++) {
      var d = document.createElement("div");
      d.className = "cy-trail";
      document.body.appendChild(d);
      dots.push(d);
      pts.push({ x: 0, y: 0 });
    }
    var mx = 0, my = 0, active = false;

    window.addEventListener("mousemove", function (e) {
      mx = e.clientX; my = e.clientY;
      if (!active) { active = true; requestAnimationFrame(loop); }
    }, { passive: true });

    function loop() {
      var px = mx, py = my;
      for (var n = 0; n < COUNT; n++) {
        var p = pts[n];
        p.x += (px - p.x) * 0.35;
        p.y += (py - p.y) * 0.35;
        dots[n].style.transform = "translate3d(" + p.x + "px," + p.y + "px,0)";
        dots[n].style.opacity = String(0.5 - n * 0.07);
        px = p.x; py = p.y;
      }
      requestAnimationFrame(loop);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initReveal);
    document.addEventListener("DOMContentLoaded", initCursor);
    document.addEventListener("DOMContentLoaded", initShare);
    document.addEventListener("DOMContentLoaded", initPointerFX);
  } else {
    initReveal();
    initCursor();
    initShare();
    initPointerFX();
  }
})();
