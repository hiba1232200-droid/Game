// ========================================
// نظام الترجمة التلقائي — LUXE CARD i18n
// عربي ↔ إنجليزي (ترجمة فورية بالمتصفح)
// ========================================
(function () {
  'use strict';

  // قاموس الترجمة: عربي → إنجليزي
  // مرتّب من الأطول للأقصر ليترجم العبارات الكاملة قبل الكلمات
  var DICT = {
    // التنقّل والهيدر
    "سلة المشتريات": "Shopping Cart",
    "عجلة الحظ": "Lucky Wheel",
    "تسجيل الدخول": "Login",
    "إنشاء حساب جديد": "Create New Account",
    "إنشاء حساب": "Sign Up",
    "تسجيل الخروج": "Logout",
    "الإشعارات": "Notifications",
    "المفضلة": "Favorites",
    "حسابي": "My Account",
    "طلباتي": "My Orders",
    "المحفظة": "Wallet",
    "الأقسام": "Categories",
    "الرئيسية": "Home",
    "الدعم": "Support",
    "تواصل معنا": "Contact Us",
    "المساعد الذكي": "AI Assistant",
    "الأسئلة الشائعة": "FAQ",
    "دخول": "Login",
    "بحث": "Search",
    "السلة": "Cart",

    // الأزرار والإجراءات
    "إتمام الشراء": "Checkout",
    "أضف للسلة": "Add to Cart",
    "للسلة": "To Cart",
    "شراء الآن": "Buy Now",
    "شراء": "Buy",
    "إلغاء": "Cancel",
    "تأكيد": "Confirm",
    "حذف": "Delete",
    "تعديل": "Edit",
    "حفظ": "Save",
    "موافقة": "Approve",
    "رفض": "Reject",
    "إرسال": "Send",
    "تطبيق": "Apply",
    "متابعة": "Continue",
    "رجوع": "Back",
    "إغلاق": "Close",
    "نسخ": "Copy",
    "تم النسخ": "Copied",
    "تحديث": "Refresh",
    "المزيد": "More",
    "عرض الكل": "View All",
    "لُف العجلة": "Spin the Wheel",
    "تحقق من الاسم": "Verify Name",

    // المحفظة والدفع
    "شحن المحفظة": "Top Up Wallet",
    "اشحن محفظتك": "Top up your wallet",
    "رصيد محفظتك": "Your wallet balance",
    "الرصيد": "Balance",
    "المبلغ": "Amount",
    "الإجمالي": "Total",
    "السعر": "Price",
    "العدد": "Quantity",
    "الكمية": "Quantity",
    "طريقة الدفع": "Payment Method",
    "سيرياتيل كاش": "Syriatel Cash",
    "شام كاش": "Sham Cash",
    "رمز العملية": "Transaction Code",
    "إثبات الدفع": "Payment Proof",
    "ليرة سورية": "Syrian Pound",
    "ل.س": "SYP",

    // الطلبات والحالات
    "قيد التنفيذ": "Processing",
    "تم التنفيذ": "Completed",
    "تم بنجاح": "Successful",
    "مرفوض": "Rejected",
    "قيد المراجعة": "Under Review",
    "بانتظار الموافقة": "Pending Approval",
    "استُلم": "Received",
    "تم": "Done",
    "الحالة": "Status",
    "التاريخ": "Date",
    "رقم الطلب": "Order Number",
    "الوقت المتوقع للتنفيذ": "Estimated processing time",
    "من دقيقة إلى 10 دقائق": "1 to 10 minutes",
    "الكود": "Code",

    // الحساب والتوثيق
    "رقم الموبايل": "Phone Number",
    "البريد الإلكتروني": "Email",
    "كلمة المرور": "Password",
    "الاسم": "Name",
    "توثيق الهوية": "ID Verification",
    "توثيق رقم الموبايل": "Phone Verification",
    "توثيق عبر واتساب": "Verify via WhatsApp",
    "رقمك موثّق": "Your number is verified",
    "موثّق": "Verified",
    "غير موثّق": "Not Verified",
    "رمز التحقق": "Verification Code",
    "أرسلت الرسالة": "Message Sent",
    "تغيير الرقم": "Change Number",

    // المنتجات والبحث
    "بحث عن منتج": "Search for a product",
    "اكتب اسم المنتج أو القسم": "Type product or category name",
    "الترتيب": "Sort",
    "الافتراضي": "Default",
    "الأرخص أولاً": "Cheapest First",
    "الأغلى أولاً": "Most Expensive First",
    "أبجدياً": "Alphabetically",
    "نتيجة": "results",
    "ما في نتائج": "No results",
    "غير متوفر حالياً": "Currently unavailable",
    "غير متوفر": "Unavailable",
    "متوفر": "Available",
    "ID اللاعب": "Player ID",
    "حدّد نطاق السعر": "Set price range",
    "من": "From",
    "إلى": "To",

    // عجلة الحظ
    "لُف العجلة مرة كل يوم واربح رصيد مجاني": "Spin once a day and win free credit",
    "حظ أوفر": "Better luck",
    "مبروك": "Congratulations",
    "ربحت": "You won",
    "الدوران التالي بعد": "Next spin in",

    // رسائل عامة
    "سجّل دخول أولاً": "Please login first",
    "تمت الإضافة للسلة": "Added to cart",
    "سلتك فارغة": "Your cart is empty",
    "تم بنجاح": "Done successfully",
    "خطأ بالاتصال": "Connection error",
    "حاول مجدداً": "Try again",
    "حاول مرة ثانية": "Try again",
    "جارٍ التنفيذ": "Processing",
    "جاري الإرسال": "Sending",
    "مطلوب": "Required",
    "اختياري": "Optional",
    "نعم": "Yes",
    "لا": "No",
    "تنبيه": "Notice",
    "خطأ": "Error",
    "نجاح": "Success",
    "تحذير": "Warning",

    // الأدمن
    "لوحة الأدمن": "Admin Panel",
    "إحصائيات": "Statistics",
    "الطلبات": "Orders",
    "الإيداعات": "Deposits",
    "المستخدمين": "Users",
    "كوبونات": "Coupons",
    "السلايدر": "Slider",
    "العروض": "Offers",
    "الإعدادات": "Settings",
    "توثيق الأرقام": "Phone Verifications",
    "مبيعات اليوم": "Today's Sales",
    "إجمالي المبيعات": "Total Sales",
    "أكثر المنتجات مبيعاً": "Best Selling Products",
    "مبيعات آخر 7 أيام": "Last 7 Days Sales",

    // VIP والمستويات
    "أنفقت": "Spent",
    "باقي": "Remaining",
    "للوصول إلى": "to reach",
    "وصلت لأعلى مستوى": "You've reached the top level",
    "تواصل مع الدعم": "Contact support",

    // كلمات مفردة شائعة
    "العميل": "Customer",
    "المستخدم": "User",
    "الزبون": "Customer",
    "المنتج": "Product",
    "القسم": "Category",
    "الكمية": "Quantity",
    "مجاني": "Free",
    "خصم": "Discount",
    "بونص": "Bonus",
    "عرض خاص": "Special Offer",
    "جديد": "New",
    "الآن": "Now",
    "اليوم": "Today",
    "أمس": "Yesterday",
    "دقيقة": "minute",
    "ساعة": "hour",

    // جمل وعبارات كاملة (الأطول أولاً)
    "⚡ تسليم فوري ودعم 24/7": "⚡ Instant delivery & 24/7 support",
    "💰 أفضل الأسعار وأسرع خدمة": "💰 Best prices & fastest service",
    "أدخل رقم عملية التحويل بالأسفل": "Enter the transfer transaction number below",
    "سيُضاف المبلغ تلقائياً بعد التحقق": "The amount will be added automatically after verification",
    "🎁 تواصل مع الدعم لكود الخصم الخاص بك": "🎁 Contact support for your discount code",
    "🏆 وصلت لأعلى مستوى!": "🏆 You've reached the top level!",
    "اضغط الزر لفتح واتساب، ثم": "Press the button to open WhatsApp, then",
    "أرسل الرسالة كما هي": "send the message as is",
    "📲 فتح واتساب وإرسال الرسالة": "📲 Open WhatsApp & send message",
    "✅ أرسلت الرسالة": "✅ Message sent",
    "رمز التحقق الخاص بك:": "Your verification code:",
    "⚠️ طلبك السابق مرفوض، حاول بصور أوضح.": "⚠️ Your previous request was rejected, try clearer photos.",
    "📷 اختر الصورة الأمامية": "📷 Choose front image",
    "📷 اختر الصورة الخلفية": "📷 Choose back image",
    "إرسال للمراجعة": "Submit for review",
    "ما في نشاط بعد.": "No activity yet.",
    "لُف العجلة مرة كل يوم واربح رصيد مجاني!": "Spin once a day and win free credit!",
    "🔍 ابحث بالاسم أو المنتج أو ID...": "🔍 Search by name, product or ID...",
    "🔍 ابحث بالاسم أو رقم العملية...": "🔍 Search by name or transaction number...",
    "🔍 ابحث بالاسم أو الإيميل...": "🔍 Search by name or email...",
    "اكتب اسم المنتج أو القسم...": "Type product or category name...",
    "ابحث عن لعبة، شحن، بطاقة...": "Search for a game, top-up, card...",
    "اكتب سؤالك هون...": "Type your question here...",
    "إذا عندك كود بونص، اكتبه هون": "If you have a bonus code, type it here",
    "حد الاستخدام (0=لا نهائي)": "Usage limit (0=unlimited)",
    "خاص بمستخدم رقم (0=للجميع)": "For user number (0=everyone)",
    "عدد الساعات (مثلاً 24)": "Number of hours (e.g. 24)",
    "المبلغ بالليرة (± )": "Amount in SYP (±)",
    "رمز التحقق الخاص بك": "Your verification code",
    "🪪 توثيق الهوية": "🪪 ID Verification",
    "✅ هويتك موثّقة": "✅ Your ID is verified",
    "✅ رقمك موثّق:": "✅ Your number is verified:",
    "📱 رقم الموبايل": "📱 Phone Number",
    "📜 سجل النشاط": "📜 Activity Log",
    "🎁 كود الخصم": "🎁 Discount Code",
    "🚪 تسجيل الخروج": "🚪 Logout",
    "ℹ️ من نحن": "ℹ️ About Us",
    "📞 تواصل معنا": "📞 Contact Us",
    "❓ الأسئلة الشائعة": "❓ FAQ",
    "📄 سياسة الاسترجاع": "📄 Refund Policy",
    "🛠 لوحة الأدمن": "🛠 Admin Panel",
    "👤 حسابي": "👤 My Account",
    "🧾 طلباتي": "🧾 My Orders",
    "🔔 الإشعارات": "🔔 Notifications",
    "❤ المفضلة": "❤ Favorites",
    "🛒 السلة": "🛒 Cart",
    "🎡 عجلة الحظ": "🎡 Lucky Wheel",
    "💳 المحفظة": "💳 Wallet",
    "🔍 بحث عن منتج": "🔍 Search for a product",
    "لوحة الأدمن": "Admin Panel",
    "الوجه الأمامي": "Front Side",
    "الوجه الخلفي": "Back Side",
    "مجموعة الواتساب": "WhatsApp Group",
    "تواصل مع الدعم": "Contact Support",
    "وصلت لأعلى مستوى": "Reached top level",
    "حد الاستخدام": "Usage limit",
    "رقم العملية": "Transaction number",
    "رقم المستخدم": "User number",
    "رقم الموبايل": "Phone number",
    "سجّل دخول": "Login",
    "معلوماتي": "My Info",
    "الإيميل": "Email",
    "القيمة": "Value",
    "إجراء": "Action",
    "انستغرام": "Instagram",
    "واتساب": "WhatsApp",
    "مثال": "Example",
    "الأمامية": "front",
    "الخلفية": "back",
    "موثّقة": "verified",
    "هويتك": "your ID",
    "رقمك": "your number",
    "سجل النشاط": "Activity log",
    "كود الخصم": "Discount code",
    "كود بونص": "Bonus code",
    "بونص": "Bonus",
    "الإيداعات": "Deposits",
    "السلايدر": "Slider",
    "يوم": "day"
  };

  // ترتيب المفاتيح من الأطول للأقصر (عشان العبارات الطويلة تترجم أول)
  var KEYS = Object.keys(DICT).sort(function (a, b) { return b.length - a.length; });

  function translateText(text) {
    var out = text;
    for (var i = 0; i < KEYS.length; i++) {
      if (out.indexOf(KEYS[i]) !== -1) {
        out = out.split(KEYS[i]).join(DICT[KEYS[i]]);
      }
    }
    return out;
  }

  // ترجمة كل النصوص بالصفحة
  function translatePage() {
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
    var nodes = [];
    var n;
    while ((n = walker.nextNode())) {
      if (n.nodeValue && /[\u0600-\u06FF]/.test(n.nodeValue)) {
        // تجاهل السكربتات والستايل
        var p = n.parentNode;
        if (p && (p.tagName === 'SCRIPT' || p.tagName === 'STYLE')) continue;
        nodes.push(n);
      }
    }
    nodes.forEach(function (node) {
      if (!node.dataset_orig) {
        // نخزّن النص الأصلي على العنصر الأب
      }
      var translated = translateText(node.nodeValue);
      if (translated !== node.nodeValue) {
        // خزّن الأصل لإمكانية الرجوع
        if (!node._origAr) node._origAr = node.nodeValue;
        node.nodeValue = translated;
      }
    });

    // ترجمة placeholders
    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(function (el) {
      if (/[\u0600-\u06FF]/.test(el.placeholder)) {
        if (!el._origPh) el._origPh = el.placeholder;
        el.placeholder = translateText(el.placeholder);
      }
    });
  }

  // الرجوع للعربية (استرجاع النصوص الأصلية)
  function restoreArabic() {
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
    var n;
    while ((n = walker.nextNode())) {
      if (n._origAr) n.nodeValue = n._origAr;
    }
    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(function (el) {
      if (el._origPh) el.placeholder = el._origPh;
    });
  }

  function applyLang(lang) {
    var html = document.documentElement;
    if (lang === 'en') {
      html.setAttribute('lang', 'en');
      html.setAttribute('dir', 'ltr');
      document.body.classList.add('ltr-mode');
      translatePage();
    } else {
      html.setAttribute('lang', 'ar');
      html.setAttribute('dir', 'rtl');
      document.body.classList.remove('ltr-mode');
      restoreArabic();
    }
    try { localStorage.setItem('luxe_lang', lang); } catch (e) {}
  }

  // زر التبديل
  window.toggleLang = function () {
    var cur = 'ar';
    try { cur = localStorage.getItem('luxe_lang') || 'ar'; } catch (e) {}
    var next = cur === 'ar' ? 'en' : 'ar';
    applyLang(next);
    updateLangBtn(next);
  };

  function updateLangBtn(lang) {
    var btn = document.getElementById('langToggle');
    if (btn) btn.textContent = lang === 'ar' ? 'EN' : 'ع';
  }

  // عند تحميل الصفحة: طبّق اللغة المحفوظة
  function init() {
    var saved = 'ar';
    try { saved = localStorage.getItem('luxe_lang') || 'ar'; } catch (e) {}
    if (saved === 'en') { applyLang('en'); }
    updateLangBtn(saved);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
