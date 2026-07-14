<?php
require_once __DIR__ . '/config.php';

/**
 * عميل موقع FastCard للتحقق من اسم اللاعب.
 * منقول من منطق البوت: تسجيل دخول → CSRF → POST /ajax/player-id-check
 * يحفظ كوكيز الجلسة في ملف ليعيد استخدامها (cache).
 */

function fcw_enabled() { return fcw_user() !== '' && fcw_pass() !== ''; }
function fcw_base() { return rtrim(FASTCARD_WEB_BASE, '/'); }
function fcw_cookiefile() {
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/fcw_cookies.txt';
}

const FCW_UA = 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

function fcw_curl($url, $opts = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => FCW_UA,
        CURLOPT_COOKIEJAR      => fcw_cookiefile(),
        CURLOPT_COOKIEFILE     => fcw_cookiefile(),
        CURLOPT_HTTPHEADER     => ['Accept-Language: ar,en;q=0.9'],
    ] + $opts);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [$body, $info];
}

/** توليد كود TOTP للـ 2FA بدون مكتبات */
function fcw_totp($secret) {
    $secret = strtoupper(str_replace(' ', '', (string)$secret));
    if ($secret === '') return '';
    $pad = strlen($secret) % 8;
    if ($pad) $secret .= str_repeat('=', 8 - $pad);
    // base32 decode
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($secret) as $c) {
        if ($c === '=') continue;
        $v = strpos($map, $c);
        if ($v === false) return '';
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    foreach (str_split($bits, 8) as $byte)
        if (strlen($byte) === 8) $key .= chr(bindec($byte));
    $counter = intval(time() / 30);
    $msg = pack('N*', 0) . pack('N*', $counter);
    $h = hash_hmac('sha1', $msg, $key, true);
    $off = ord($h[19]) & 0x0f;
    $code = ((ord($h[$off]) & 0x7f) << 24 | (ord($h[$off+1]) & 0xff) << 16 |
             (ord($h[$off+2]) & 0xff) << 8 | (ord($h[$off+3]) & 0xff)) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/** يجلب CSRF token من صفحات المنتجات — يرجع [token, sourcePage] */
function fcw_csrf() {
    $base = fcw_base();
    foreach (['/index?page=products&cat=440', '/index?page=products', '/index'] as $page) {
        [$html] = fcw_curl($base . $page);
        if (!$html) continue;
        if (preg_match('/PLAYER_CHECK_CSRF\s*=\s*["\']([a-f0-9]{20,})["\']/i', $html, $m)) return [$m[1], $page];
        if (preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/', $html, $m)) return [$m[1], $page];
        if (preg_match('/csrf[_-]?token["\']?\s*[:=]\s*["\']([a-f0-9]{32,})["\']/i', $html, $m)) return [$m[1], $page];
        if (preg_match('/name=["\']_token["\']\s+value=["\']([^"\']+)["\']/', $html, $m)) return [$m[1], $page];
    }
    return ['', '/index?page=products'];
}

/** تسجيل الدخول لموقع FastCard */
function fcw_login() {
    $base = fcw_base();
    fcw_curl($base . '/login'); // جلب PHPSESSID
    [$body, $info] = fcw_curl($base . '/login', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query(['username' => fcw_user(), 'password' => fcw_pass()]),
    ]);
    $finalUrl = strtolower($info['url'] ?? '');

    // مصادقة ثنائية
    if (strpos($finalUrl, 'twofactor') !== false || strpos($finalUrl, '2fa') !== false) {
        $secret = fcw_2fa();
        if ($secret === '') return false;
        $code = fcw_totp($secret);
        $csrf = '';
        if (preg_match('/name=["\']?_token["\']?\s+value=["\']([^"\']+)["\']/', (string)$body, $m)) $csrf = $m[1];
        foreach (['code', 'otp', 'two_factor_code', 'token', '2fa_code', 'authenticator_code', 'pin'] as $field) {
            $payload = [$field => $code];
            if ($csrf) { $payload['_token'] = $csrf; $payload['csrf_token'] = $csrf; }
            [, $i2] = fcw_curl($base . '/twofactor', [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query($payload),
                CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', 'Referer: ' . $base . '/twofactor'],
            ]);
            if (strpos(strtolower($i2['url'] ?? ''), 'twofactor') === false) return true;
        }
        return false;
    }
    return true;
}

/**
 * التحقق من اسم اللاعب.
 * يرجّع: ['ok'=>true,'name'=>...] أو ['ok'=>false,'msg'=>...] أو ['ok'=>false,'soft'=>true,...]
 */
function fcw_check_player($playerId, $productId, &$debug = null) {
    if (!fcw_enabled()) return ['ok' => false, 'soft' => true, 'msg' => 'تحقق الاسم غير مفعّل — بيانات دخول الموقع ناقصة'];

    $base = fcw_base();
    $url  = $base . '/ajax/player-id-check';

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        if ($attempt === 2) { @unlink(fcw_cookiefile()); $lg = fcw_login(); if (is_array($debug)) $debug[] = "attempt2 login=" . var_export($lg, true); }
        elseif (!file_exists(fcw_cookiefile())) { $lg = fcw_login(); if (is_array($debug)) $debug[] = "login=" . var_export($lg, true); }

        [$csrf, $srcPage] = fcw_csrf();
        if (is_array($debug)) $debug[] = "attempt=$attempt product=$productId csrf_len=" . strlen($csrf) . " src=$srcPage";

        // مطابق لملف player-id-check.js الأصلي حرف بحرف:
        // X-CSRF-Token (مش TOKEN) + Content-Type + الترتيب product_id ثم user_id + Referer مع cat
        [$body, $info] = fcw_curl($url, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query(['product_id' => (int)$productId, 'user_id' => (string)$playerId]),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With: XMLHttpRequest',
                'X-CSRF-Token: ' . $csrf,
                'Origin: ' . $base,
                'Referer: ' . $base . '/index?page=products&cat=440',
            ],
        ]);
        $data = json_decode((string)$body, true);
        if (is_array($debug)) $debug[] = "http=" . ($info['http_code'] ?? '?') . " body=" . mb_substr(trim((string)$body), 0, 300);

        if (is_array($data)) {
            $valid = $data['valid'] ?? null;
            $name  = $data['player_name'] ?? $data['name'] ?? $data['username'] ?? null;
            if ($valid && $valid !== 'invalid' && $name) return ['ok' => true, 'name' => $name];
            $success = $data['success'] ?? null;
            $msg = strtolower((string)($data['message'] ?? $data['error'] ?? ''));
            if ($attempt === 1 && (strpos($msg, 'login') !== false || strpos($msg, 'تسجيل') !== false || $success === null)) continue;
            return ['ok' => false, 'msg' => 'ID غير صحيح أو لم يتم العثور على اللاعب'];
        }
        if ($attempt === 1) continue;
        // كل الصيغ فشلت بهالمحاولة → أعد تسجيل الدخول وجرب مرة ثانية
    }
    return ['ok' => false, 'msg' => 'ID غير صحيح أو لم يتم العثور على اللاعب'];
}
