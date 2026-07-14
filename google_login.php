<?php
require_once __DIR__ . '/db.php';

if (!google_enabled()) {
    header('Location: /auth.php'); exit;
}

$redirectUri = site_url() . '/google_login.php';

// الخطوة 2: رجوع جوجل مع code
if (isset($_GET['code'])) {
    // تبادل الـ code بـ access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => google_client_id(),
            'client_secret' => google_client_secret(),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $tok = json_decode($res, true);
    $accessToken = $tok['access_token'] ?? null;

    if (!$accessToken) { header('Location: /auth.php?err=google'); exit; }

    // جلب معلومات المستخدم
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $info = json_decode($res, true);

    $email = $info['email'] ?? null;
    $name  = $info['name'] ?? ($email ? explode('@', $email)[0] : 'مستخدم');
    if (!$email) { header('Location: /auth.php?err=google'); exit; }

    // موجود؟ سجّل دخول. مش موجود؟ أنشئ حساب
    $st = db()->prepare("SELECT * FROM users WHERE email=?");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        $randPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        db()->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)")
            ->execute([$name, $email, $randPass]);
        $_SESSION['uid'] = last_id('users');
        process_referral_signup($_SESSION['uid']); // ربط الإحالة + هدية المُحال
        notify_user($_SESSION['uid'], '🎉 أهلاً بك في ' . STORE_NAME . '!',
            'سعداء بانضمامك. اشحن محفظتك وابدأ بشراء شحن الألعاب والبطاقات بأسرع وأأمن طريقة. الدعم والمساعد الذكي جاهزين لخدمتك 24/7.', '🎉');
    } else {
        $_SESSION['uid'] = $u['id'];
    }
    $u = current_user();
    header('Location: ' . (($u && $u['role'] === 'admin') ? '/admin.php' : '/index.php'));
    exit;
}

// الخطوة 1: توجيه لجوجل
$params = http_build_query([
    'client_id'     => google_client_id(),
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
