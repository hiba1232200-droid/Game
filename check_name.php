<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$player  = trim((string)($_GET['player'] ?? ''));
$product = (int)($_GET['product'] ?? 0);
if ($player === '') { echo json_encode(['ok' => false, 'msg' => 'أدخل ID اللاعب أولاً'], JSON_UNESCAPED_UNICODE); exit; }

$botUrl = bot_check_url();

// وضع تشخيص: أضف &debug=1
$dbg = isset($_GET['debug']);

// الطريقة الأساسية: النداء على البوت (تحققه شغال 100%)
if ($botUrl !== '') {
    $url = $botUrl . (strpos($botUrl, '?') === false ? '?' : '&') . http_build_query([
        'player'  => $player,
        'product' => $product,
        'secret'  => check_secret(),
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($dbg) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "BOT_URL: $url\n\nHTTP: $code\nCURL_ERR: $err\n\nRESPONSE:\n$res";
        exit;
    }
    $d = json_decode((string)$res, true);
    if (is_array($d) && isset($d['ok'])) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
    // البوت ما رد صح → fallback تحت
}

// احتياطي: الطريقة المباشرة من الموقع (لو البوت مش متاح)
if (file_exists(__DIR__ . '/fastcard_web.php')) {
    require_once __DIR__ . '/fastcard_web.php';
    $res = fcw_check_player($player, $product ?: 7816);
    echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
}

echo json_encode(['ok' => false, 'soft' => true, 'msg' => 'تعذّر التحقق حالياً'], JSON_UNESCAPED_UNICODE);
