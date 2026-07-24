<?php
/**
 * خريطة الموقع (sitemap) — تُولَّد تلقائياً.
 * تخبر جوجل بكل صفحات موقعك العامة وأقسامك حتى يفهرسها.
 * تُحدَّث وحدها كلما أضفت قسماً جديداً.
 */
require_once __DIR__ . '/db.php';

// نتجاهل أي مخرجات سابقة (مسافة أو سطر فارغ من ملف مُضمَّن)
// لأن أي حرف قبل <?xml يجعل جوجل يرفض الملف.
if (ob_get_level()) { @ob_end_clean(); }

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=10800'); // 3 ساعات

/* ---------------------------------------------------------------
   نسخة محفوظة (cache):
   توليد الخريطة يتطلب الاتصال بفاست كارد وقد يستغرق ثواني،
   وزاحف جوجل يستسلم بسرعة فتفشل الخريطة. لذلك نحفظ آخر نسخة
   في قاعدة البيانات ونقدّمها فوراً، ونعيد التوليد كل 6 ساعات فقط.
   --------------------------------------------------------------- */
$__CACHE_KEY = 'sitemap_xml';
$__CACHE_AT  = 'sitemap_xml_at';
$__TTL       = 21600; // 6 ساعات
$__force     = isset($_GET['refresh']);

if (!$__force) {
    try {
        $cached = (string)setting($__CACHE_KEY, '');
        $when   = (int)setting($__CACHE_AT, 0);
        if ($cached !== '' && (time() - $when) < $__TTL) {
            header('X-Sitemap-Cache: hit');
            echo $cached;
            exit;
        }
    } catch (Exception $e) { /* نكمل ونولّد من جديد */ }
}

// نجمع الناتج لنحفظه بعد التوليد
ob_start();

$site = rtrim(site_url(), '/');
$today = date('Y-m-d');

/** يضيف رابطاً للخريطة */
function sm_url($loc, $lastmod, $freq, $prio) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>$lastmod</lastmod>\n";
    echo "    <changefreq>$freq</changefreq>\n";
    echo "    <priority>$prio</priority>\n";
    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// ===== الصفحات الثابتة =====
sm_url($site . '/',                        $today, 'daily',   '1.0');
sm_url($site . '/index.php',               $today, 'daily',   '1.0');
sm_url($site . '/faq.php',                 $today, 'monthly', '0.7');
sm_url($site . '/contact.php',             $today, 'monthly', '0.6');
sm_url($site . '/index.php?page=about',    $today, 'monthly', '0.6');
sm_url($site . '/index.php?page=terms',    $today, 'yearly',  '0.4');
sm_url($site . '/index.php?page=search',   $today, 'weekly',  '0.5');

// ===== الأقسام (تُقرأ من فاست كارد) =====
// حماية من البطء: جوجل يستسلم إذا تأخّر الرد، لذلك نضع حداً زمنياً
// ونتوقّف عن إضافة الأقسام إن طال الوقت — الخريطة تبقى صالحة دائماً.
$__start = microtime(true);
$__budget = 8.0; // ثوانٍ كحد أقصى لجلب الأقسام

try {
    @set_time_limit(25);
    require_once __DIR__ . '/fastcard_api.php';
    $root = fc_content(0);
    $cats = $root['categories'] ?? [];

    foreach ($cats as $c) {
        if (microtime(true) - $__start > $__budget) break;

        $id   = (string)($c['id'] ?? '');
        $name = (string)($c['name'] ?? '');
        if ($id === '') continue;

        sm_url($site . '/index.php?page=products&cat=' . urlencode($id)
             . '&name=' . urlencode($name), $today, 'daily', '0.9');

        // الأقسام الفرعية (مستوى واحد)
        if (microtime(true) - $__start > $__budget) continue;
        try {
            $sub = fc_content($id);
            foreach (($sub['categories'] ?? []) as $s) {
                $sid = (string)($s['id'] ?? '');
                if ($sid === '') continue;
                sm_url($site . '/index.php?page=products&cat=' . urlencode($sid)
                     . '&name=' . urlencode((string)($s['name'] ?? '')),
                     $today, 'daily', '0.8');
            }
        } catch (Exception $e) { /* تجاهل قسماً واحداً فقط */ }
    }
} catch (Throwable $e) {
    // لا شيء — الخريطة تبقى صالحة بالصفحات الثابتة
}

echo '</urlset>';

// حفظ النسخة لتقديمها فوراً في الطلبات القادمة (وخاصة لزاحف جوجل)
$__xml = ob_get_clean();
echo $__xml;
try {
    set_setting($__CACHE_KEY, $__xml);
    set_setting($__CACHE_AT, (string)time());
} catch (Exception $e) { /* لا شيء — الخريطة صحيحة على أي حال */ }
