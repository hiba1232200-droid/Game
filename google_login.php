<?php
require_once __DIR__ . '/db.php';

if (!google_enabled()) {
    header('Location: /auth.php'); exit;
}

// rtrim يمنع أشهر سبب للخطأ: وجود "/" زائدة في SITE_URL
// تجعل الرابط "…app//google_login.php" فلا يطابق المسجَّل عند جوجل.
$redirectUri = rtrim(site_url(), '/') . '/google_login.php';

// أداة تشخيص: تعرض الرابط الذي يرسله موقعك فعلاً لجوجل.
// افتح: /google_login.php?show_uri=1
// (الرابط ليس سرياً — يظهر أصلاً داخل رابط تسجيل الدخول)
if (isset($_GET['show_uri'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "الرابط الذي يرسله موقعك (redirect_uri):\n";
    echo $redirectUri . "\n\n";
    echo "ضع هذا الرابط حرفياً في Google Cloud Console:\n";
    echo "APIs & Services → Credentials → Web client 1 → Authorized redirect URIs\n\n";
    echo "SITE_URL الحالية: " . site_url() . "\n";
    exit;
}

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
        @session_regenerate_id(true); // منع تثبيت الجلسة
        $_SESSION['uid'] = last_id('users');
        process_referral_signup($_SESSION['uid']); // ربط الإحالة + هدية المُحال
        notify_user($_SESSION['uid'], '🎉 أهلاً بك في ' . STORE_NAME . '!',
            'سعداء بانضمامك. اشحن محفظتك وابدأ بشراء شحن الألعاب والبطاقات بأسرع وأأمن طريقة. الدعم والمساعد الذكي جاهزين لخدمتك 24/7.', '🎉');
    } else {
        @session_regenerate_id(true); // منع تثبيت الجلسة
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
