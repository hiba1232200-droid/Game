/* ============================================================
   LUXE CARD — تأثيرات خفيفة (Phase 3)
   مبدأ السلامة: لو تعطّل هذا الملف أو لم يُحمّل إطلاقاً،
   الموقع يبقى شغّالاً بالكامل والمحتوى ظاهراً — لا شيء يعتمد عليه.
   ============================================================ */
(function () {
  "use strict";

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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initReveal);
  } else {
    initReveal();
  }
})();
