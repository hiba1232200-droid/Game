<?php
/**
 * خريطة الموقع (sitemap) — تُولَّد تلقائياً.
 * تخبر جوجل بكل صفحات موقعك العامة وأقسامك حتى يفهرسها.
 * تُحدَّث وحدها كلما أضفت قسماً جديداً.
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=10800'); // 3 ساعات

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
try {
    require_once __DIR__ . '/fastcard_api.php';
    $root = fc_content(0);
    $cats = $root['categories'] ?? [];

    foreach ($cats as $c) {
        $id   = (string)($c['id'] ?? '');
        $name = (string)($c['name'] ?? '');
        if ($id === '') continue;

        sm_url($site . '/index.php?page=products&cat=' . urlencode($id)
             . '&name=' . urlencode($name), $today, 'daily', '0.9');

        // الأقسام الفرعية (مستوى واحد — يكفي لتغطية الألعاب)
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
} catch (Exception $e) {
    // لا شيء — الخريطة تبقى صالحة بالصفحات الثابتة
}

echo '</urlset>';
