<?php
require_once __DIR__ . '/config.php';

/* ===== كشف نوع قاعدة البيانات ===== */
function db_url() {
    return getenv('DATABASE_URL') ?: '';
}
function is_pg() {
    static $pg = null;
    if ($pg === null) $pg = (bool)db_url();
    return $pg;
}
/** دالة الوقت الحالي حسب القاعدة */
function NOW_FN() { return is_pg() ? 'NOW()' : "datetime('now')"; }

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;

    if (is_pg()) {
        // PostgreSQL (Railway) — البيانات دائمة
        // الأولوية للمتغيرات المنفصلة (أضمن من تفكيك الرابط)
        $host = getenv('PGHOST');
        $port = getenv('PGPORT') ?: 5432;
        $dbname = getenv('PGDATABASE');
        $user = getenv('PGUSER');
        $pass = getenv('PGPASSWORD');
        // إذا المنفصلة ناقصة، فكّك DATABASE_URL
        if (!$host || !$user) {
            $u = parse_url(db_url());
            $host = $u['host'] ?? 'localhost';
            $port = $u['port'] ?? 5432;
            $dbname = ltrim($u['path'] ?? '', '/');
            $user = $u['user'] ?? '';
            $pass = isset($u['pass']) ? urldecode($u['pass']) : '';
        }
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true,
        ]);
    } else {
        // SQLite (تجربة محلية)
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
    }
    init_db($pdo);
    return $pdo;
}

// رقم نسخة هيكل قاعدة البيانات — كل ما تضيف عمود/جدول جديد بالكود، زِد هذا الرقم
// حتى يُعاد فحص الهيكل تلقائياً مرة واحدة بعد كل نشر جديد فقط
define('SCHEMA_VERSION', '4');

function init_db($pdo) {
    // فحص سريع (استعلام واحد فقط): إذا الداتابيز متهيأة بالفعل بنفس النسخة،
    // نتجاوز فوراً كل عمليات CREATE/ALTER TABLE (~27 استعلام) — هذا أهم تحسين
    // للسرعة لأنو كان عم يتكرر بكل طلب وبكل صفحة بالموقع بدون فايدة
    try {
        $chk = $pdo->prepare("SELECT value FROM settings WHERE key='schema_version'");
        $chk->execute();
        if ($chk->fetchColumn() === SCHEMA_VERSION) return;
    } catch (Exception $e) {}

    $pk = is_pg() ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $now = is_pg() ? 'CURRENT_TIMESTAMP' : "(datetime('now'))";
    $real = is_pg() ? 'DOUBLE PRECISION' : 'REAL';

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        name TEXT, email TEXT UNIQUE, password TEXT,
        balance $real DEFAULT 0, role TEXT DEFAULT 'user',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id $pk,
        user_id INTEGER, product_id TEXT, product_name TEXT,
        qty $real DEFAULT 1, player_id TEXT,
        price $real, total $real,
        uuid TEXT, fc_order_id TEXT, codes TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT $now,
        updated_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS topups (
        id $pk,
        user_id INTEGER, tx_id TEXT UNIQUE, amount $real,
        status TEXT DEFAULT 'approved',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        user_id INTEGER, product_id TEXT, PRIMARY KEY(user_id, product_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY, value TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cache (
        key TEXT PRIMARY KEY, value TEXT, expires BIGINT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        sid TEXT PRIMARY KEY, data TEXT, updated BIGINT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id $pk,
        code TEXT UNIQUE, type TEXT DEFAULT 'percent', amount $real DEFAULT 0,
        max_uses INTEGER DEFAULT 0, used INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        user_id INTEGER DEFAULT 0,
        scope TEXT DEFAULT 'wallet',
        player_id TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupon_uses (
        coupon_id INTEGER, user_id INTEGER, used_at TIMESTAMP DEFAULT $now,
        PRIMARY KEY(coupon_id, user_id)
    )");
    // خصومات الأسعار المفعّلة على حسابات المستخدمين (من صفحة كود الخصم)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_discounts (
        id $pk,
        user_id INTEGER, coupon_id INTEGER, code TEXT DEFAULT '',
        player_id TEXT DEFAULT '', type TEXT DEFAULT 'percent', amount $real DEFAULT 0,
        status TEXT DEFAULT 'active',
        created_at TIMESTAMP DEFAULT $now, used_at TIMESTAMP
    )");
    // رسائل الدعم البشري (محادثة بين الزبون والأدمن)
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id $pk,
        user_id INTEGER, sender TEXT DEFAULT 'user', body TEXT,
        read_user INTEGER DEFAULT 0, read_admin INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    // صور مخصّصة للأقسام/المنتجات (مخزّنة بقاعدة البيانات لتبقى بعد التحديث)
    $pdo->exec("CREATE TABLE IF NOT EXISTS item_images (
        item_id TEXT PRIMARY KEY,
        mime TEXT DEFAULT 'image/png',
        data TEXT,
        updated_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS slides (
        id $pk,
        image TEXT, link TEXT, sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id $pk,
        user_id INTEGER, title TEXT, body TEXT, icon TEXT DEFAULT '🔔',
        is_read INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        id $pk,
        user_id INTEGER, phone TEXT, code TEXT,
        status TEXT DEFAULT 'pending',
        expires_at TIMESTAMP, attempts INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS id_verifications (
        id $pk,
        user_id INTEGER, image TEXT, image_back TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT $now
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS wheel_spins (
        id $pk,
        user_id INTEGER, prize INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    // عمولات الإحالة: كل صف = عمولة دفعناها للمُحيل من شحنة أحد مُحاليه
    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_earnings (
        id $pk,
        referrer_id INTEGER, referred_id INTEGER,
        topup_amount $real DEFAULT 0, commission $real DEFAULT 0,
        created_at TIMESTAMP DEFAULT $now
    )");
    // طلبات إيداع USDT المعلّقة (تحقق يدوي عبر موافقة التلجرام)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usdt_requests (
        id $pk,
        user_id INTEGER, tx_id TEXT, usdt_amount $real DEFAULT 0,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT $now
    )");
    // عمود الكوبون بجدول الإيداع (للقواعد القديمة)
    if (is_pg()) {
        try { $pdo->exec("ALTER TABLE topups ADD COLUMN IF NOT EXISTS coupon TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS codes TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banned INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS user_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS scope TEXT DEFAULT 'wallet'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN IF NOT EXISTS player_id TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS birthday TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_bday_gift TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS id_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE id_verifications ADD COLUMN IF NOT EXISTS image_back TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ALTER COLUMN qty TYPE DOUBLE PRECISION"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cost_syp DOUBLE PRECISION DEFAULT 0"); } catch (Exception $e) {}
        // نظام الإحالة
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ref_gift_given INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS first_topup_done INTEGER DEFAULT 0"); } catch (Exception $e) {}
    } else {
        try { $pdo->exec("ALTER TABLE topups ADD COLUMN coupon TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN codes TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN banned INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN user_id INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN scope TEXT DEFAULT 'wallet'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN player_id TEXT DEFAULT ''"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN birthday TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN last_bday_gift TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN phone_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN id_verified INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE id_verifications ADD COLUMN image_back TEXT"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE otp_codes ADD COLUMN status TEXT DEFAULT 'pending'"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN cost_syp REAL DEFAULT 0"); } catch (Exception $e) {}
        // نظام الإحالة
        try { $pdo->exec("ALTER TABLE users ADD COLUMN referred_by INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN ref_gift_given INTEGER DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE users ADD COLUMN first_topup_done INTEGER DEFAULT 0"); } catch (Exception $e) {}
    }
    // أدمن افتراضي
    $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin'");
    $st->execute();
    if (!$st->fetchColumn()) {
        $ins = is_pg()
            ? "INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin') ON CONFLICT (email) DO NOTHING"
            : "INSERT OR IGNORE INTO users (name,email,password,role) VALUES (?,?,?,'admin')";
        $pdo->prepare($ins)->execute(['الأدمن', ADMIN_EMAIL, password_hash(ADMIN_PASS, PASSWORD_DEFAULT)]);
    }
    // منع بونص "أول شحن" للمستخدمين القدامى: من عنده إيداع سابق يُعلَّم أنه شحن من قبل.
    // يُنفَّذ مرة واحدة فقط وقت ترقية الهيكل (لأن init_db كلها تُتجاوز بعد ذلك).
    try {
        $pdo->exec("UPDATE users SET first_topup_done=1
            WHERE COALESCE(first_topup_done,0)=0
            AND id IN (SELECT DISTINCT user_id FROM topups)");
    } catch (Exception $e) {}
    // تسجيل نجاح التهيئة بهذي النسخة — أي طلب جاي بعد هلق رح يتجاوز كل الشغل فوق فوراً
    try {
        $ins2 = is_pg()
            ? "INSERT INTO settings (key,value) VALUES ('schema_version', ?) ON CONFLICT (key) DO UPDATE SET value=excluded.value"
            : "INSERT OR REPLACE INTO settings (key,value) VALUES ('schema_version', ?)";
        $pdo->prepare($ins2)->execute([SCHEMA_VERSION]);
    } catch (Exception $e) {}
}

/** آخر ID مُدرج (PostgreSQL يحتاج اسم السيكوينس) */
function last_id($table = null) {
    if (is_pg()) {
        $seq = $table ? "{$table}_id_seq" : null;
        return $seq ? db()->lastInsertId($seq) : db()->lastInsertId();
    }
    return db()->lastInsertId();
}

/* ===== جلسة محفوظة بقاعدة البيانات (تضل مسجّل دخول رغم إعادة النشر) ===== */
class DbSessionHandler implements SessionHandlerInterface {
    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }
    #[\ReturnTypeWillChange]
    public function read($sid) {
        try {
            $st = db()->prepare("SELECT data FROM sessions WHERE sid=?");
            $st->execute([$sid]);
            $v = $st->fetchColumn();
            return $v !== false ? (string)$v : '';
        } catch (Exception $e) { return ''; }
    }
    public function write($sid, $data): bool {
        try {
            $sql = "INSERT INTO sessions (sid,data,updated) VALUES (?,?,?)
                    ON CONFLICT(sid) DO UPDATE SET data=excluded.data, updated=excluded.updated";
            db()->prepare($sql)->execute([$sid, $data, time()]);
            return true;
        } catch (Exception $e) { return false; }
    }
    public function destroy($sid): bool {
        try { db()->prepare("DELETE FROM sessions WHERE sid=?")->execute([$sid]); } catch (Exception $e) {}
        return true;
    }
    #[\ReturnTypeWillChange]
    public function gc($maxlifetime) {
        try { db()->prepare("DELETE FROM sessions WHERE updated < ?")->execute([time() - $maxlifetime]); } catch (Exception $e) {}
        return true;
    }
}

// بدء الجلسة بعد تجهيز القاعدة (مرة واحدة)
function start_session_once() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    db(); // التأكد من تجهيز القاعدة والجداول
    try {
        session_set_save_handler(new DbSessionHandler(), true);
    } catch (Exception $e) {}
    @session_start();
}
start_session_once();

// مفتاح تجاوز الصيانة: افتح  /?bypass=المفتاح  مرة واحدة، فيُحفظ بجلستك وتتصفّح الموقع
// بحرية طوال فترة الصيانة (حتى لو مسجّل خروج). للخروج من وضع التجاوز: /?bypass=off
if (isset($_GET['bypass'])) {
    if ($_GET['bypass'] === 'off') { unset($_SESSION['maint_bypass']); }
    elseif (function_exists('maintenance_bypass_key') && $_GET['bypass'] === maintenance_bypass_key()) {
        $_SESSION['maint_bypass'] = 1;
    }
}

// التقاط رابط الإحالة (?ref=ID): نخزّن معرّف المُحيل بالجلسة حتى لو سجّل الزائر لاحقاً
if (isset($_GET['ref']) && empty($_SESSION['ref_by'])) {
    $refId = (int)$_GET['ref'];
    if ($refId > 0 && empty($_SESSION['uid'])) { // فقط لو الزائر مش مسجّل دخول
        $_SESSION['ref_by'] = $refId;
    }
}


function setting($key, $default = null) {
    $st = db()->prepare("SELECT value FROM settings WHERE key=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v !== false ? $v : $default;
}
function set_setting($key, $value) {
    $sql = "INSERT INTO settings (key,value) VALUES (?,?)
            ON CONFLICT(key) DO UPDATE SET value=excluded.value";
    db()->prepare($sql)->execute([$key, $value]);
}

// مخرج طوارئ: إيقاف وضع الصيانة عبر رابط سري (يعمل حتى لو كان الأدمن محجوباً خارجاً)
// الاستخدام: افتح الرابط  /?disable_maintenance=MAINT_OFF_LUXE
if (isset($_GET['disable_maintenance']) && $_GET['disable_maintenance'] === 'MAINT_OFF_LUXE') {
    try { set_setting('maintenance', '0'); } catch (Exception $e) {}
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html dir="rtl"><body style="font-family:sans-serif;text-align:center;padding:40px">'
       . '<h2>✅ تم إيقاف وضع الصيانة</h2>'
       . '<p>الموقع عاد للعمل الآن.</p>'
       . '<p><a href="/auth.php">➡️ اذهب لتسجيل الدخول</a></p>'
       . '<p><a href="/index.php">🏠 الصفحة الرئيسية</a></p>'
       . '</body></html>';
    exit;
}

function cache_get($key) {
    $st = db()->prepare("SELECT value FROM cache WHERE key=? AND expires > ?");
    $st->execute([$key, time()]);
    $v = $st->fetchColumn();
    return $v !== false ? json_decode($v, true) : null;
}
function cache_set($key, $data, $ttl) {
    $sql = "INSERT INTO cache (key,value,expires) VALUES (?,?,?)
            ON CONFLICT(key) DO UPDATE SET value=excluded.value, expires=excluded.expires";
    db()->prepare($sql)->execute([$key, json_encode($data, JSON_UNESCAPED_UNICODE), time() + $ttl]);
}

function current_user() {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    // المستخدم المحظور: تسجيل خروج فوري
    if ($u && !empty($u['banned'])) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
        return null;
    }
    return $u;
}
function require_login() {
    if (!current_user()) { header('Location: /auth.php'); exit; }
}
function require_admin() {
    $u = current_user();
    if (!$u || $u['role'] !== 'admin') { header('Location: /auth.php'); exit; }
    return $u;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** يُستدعى مرة واحدة بعد تسجيل مستخدم جديد.
 *  إذا جاء عبر رابط إحالة صالح: يربطه بالمُحيل ويعطيه هدية التسجيل (5000 ل.س افتراضياً). */
function process_referral_signup($newUserId) {
    if (!ref_enabled()) return;
    $refBy = (int)($_SESSION['ref_by'] ?? 0);
    if ($refBy <= 0 || $refBy == $newUserId) return; // لا إحالة، أو إحالة ذاتية
    try {
        // تأكد أن المُحيل موجود فعلاً
        $st = db()->prepare("SELECT id, name FROM users WHERE id=?");
        $st->execute([$refBy]);
        $referrer = $st->fetch(PDO::FETCH_ASSOC);
        if (!$referrer) { unset($_SESSION['ref_by']); return; }

        $gift = ref_signup_gift();
        db()->beginTransaction();
        // اربط المُحال بالمُحيل + امنحه الهدية (مرة واحدة)
        db()->prepare("UPDATE users SET referred_by=?, ref_gift_given=1, balance = balance + ? WHERE id=? AND COALESCE(ref_gift_given,0)=0")
            ->execute([$refBy, $gift, $newUserId]);
        db()->commit();

        if ($gift > 0) {
            notify_user($newUserId, '🎁 هدية الإحالة',
                'حصلت على ' . number_format($gift) . ' ل.س هدية لانضمامك عبر دعوة صديق! ولديك بونص ' . number_format(ref_first_topup_pct()) . '% على أول عملية شحن.', '🎁');
        }
        notify_admin("🔗 <b>إحالة جديدة</b>\nالمُحيل: " . e($referrer['name']) . " (#$refBy)\nانضم عبر دعوته مستخدم جديد (#$newUserId)" . ($gift > 0 ? "\nمُنح المُحال هدية: " . number_format($gift) . " ل.س" : ''));
    } catch (Exception $e) {
        try { db()->rollBack(); } catch (Exception $e2) {}
    }
    unset($_SESSION['ref_by']);
}

/** يُستدعى عند كل عملية شحن ناجحة.
 *  1) بونص أول شحن للمُحال (15%) — مرة واحدة فقط.
 *  2) عمولة المُحيل (5% من المبلغ الأساسي) — كل شحنة.
 *  يُمرّر المبلغ الأساسي (قبل أي بونص). يرجع مبلغ بونص أول الشحن ليُضاف لرصيد المُحال. */
function process_referral_topup($userId, $baseAmount) {
    if (!ref_enabled()) return 0;
    $firstBonus = 0;
    try {
        $st = db()->prepare("SELECT referred_by, COALESCE(first_topup_done,0) ft FROM users WHERE id=?");
        $st->execute([$userId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) return 0;
        $refBy = (int)($u['referred_by'] ?? 0);

        // 1) بونص أول شحن للمُحال (فقط لو مُحال، وأول شحنة)
        if ($refBy > 0 && (int)$u['ft'] === 0) {
            $firstBonus = round($baseAmount * ref_first_topup_pct() / 100);
        }
        // علّم أن أول شحنة تمت (للجميع، حتى لو غير مُحالين — لا يضر)
        db()->prepare("UPDATE users SET first_topup_done=1 WHERE id=?")->execute([$userId]);

        // 2) عمولة المُحيل من المبلغ الأساسي — كل شحنة
        if ($refBy > 0) {
            $commission = round($baseAmount * ref_commission_pct() / 100);
            if ($commission > 0) {
                db()->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$commission, $refBy]);
                db()->prepare("INSERT INTO referral_earnings (referrer_id,referred_id,topup_amount,commission) VALUES (?,?,?,?)")
                    ->execute([$refBy, $userId, $baseAmount, $commission]);
                notify_user($refBy, '💸 عمولة إحالة',
                    'ربحت ' . number_format($commission) . ' ل.س عمولة من شحنة صديق دعوته!', '💸');
            }
        }
    } catch (Exception $e) {}
    return $firstBonus;
}

/** إحصائيات إحالة المستخدم (لعرضها بصفحة الحساب) */
function referral_stats($userId) {
    $out = ['count' => 0, 'earned' => 0];
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
        $st->execute([$userId]);
        $out['count'] = (int)$st->fetchColumn();
        $st = db()->prepare("SELECT COALESCE(SUM(commission),0) FROM referral_earnings WHERE referrer_id=?");
        $st->execute([$userId]);
        $out['earned'] = (float)$st->fetchColumn();
    } catch (Exception $e) {}
    return $out;
}

/* ===== العملة وسعر الصرف ===== */
// سعر صرف تسعير الألعاب (تحويل سعر FastCard بالدولار لليرة السورية)
function usd_rate() { return max(0.0001, (float)setting('usd_rate', 13500)); }
// سعر صرف شحن شام كاش بالدولار (منفصل عن سعر تسعير الألعاب)
// إذا الأدمن ما حدّده، بيرجع لسعر تسعير الألعاب تلقائياً
function usd_rate_shamcash() {
    $v = setting('usd_rate_shamcash', '');
    if ($v === '' || $v === null) return usd_rate();
    return max(0.0001, (float)$v);
}
function display_currency() { return (($_COOKIE['currency'] ?? 'syp') === 'usd') ? 'usd' : 'syp'; }
function fmt_price($syp) {
    if (display_currency() === 'usd') return number_format($syp / usd_rate(), 2) . ' $';
    return number_format($syp) . ' ل.س';
}

/* ===== كوبون خصم على الأسعار (مربوط بـ ID لاعب محدد) =====
   الكود يتفعّل على حساب المستخدم من صفحة "كود الخصم"، وينطبق تلقائياً وقت الشراء. */

// يحسب قيمة الخصم على إجمالي معيّن
function discount_value($type, $amount, $total) {
    $total = max(0, (float)$total);
    $d = ($type === 'percent') ? $total * ((float)$amount / 100) : (float)$amount;
    if ($d > $total) $d = $total;       // الخصم لا يتجاوز الإجمالي
    return round($d);
}

// نص الخصم للعرض: 10 => "10%"، 10.5 => "10.5%"، أو "5,000 ل.س"
function disc_label($type, $amount) {
    $a = (float)$amount;
    $n = ($a == (int)$a) ? (string)(int)$a : rtrim(rtrim(number_format($a, 2, '.', ''), '0'), '.');
    return $type === 'percent' ? $n . '%' : number_format($a) . ' ل.س';
}

// ===== صور الأقسام/المنتجات المخصّصة =====
// خريطة [item_id => updated_at] لكل العناصر يلي إلها صورة (استعلام واحد لكل صفحة)
function item_images_map() {
    static $m = null;
    if ($m === null) {
        $m = [];
        try {
            foreach (db()->query("SELECT item_id, updated_at FROM item_images")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $m[(string)$r['item_id']] = (string)$r['updated_at'];
            }
        } catch (Exception $e) {}
    }
    return $m;
}
// رابط صورة العنصر إن وجدت، وإلا null
function item_img_url($id) {
    $id = (string)$id;
    $m = item_images_map();
    if (!isset($m[$id])) return null;
    return '/img.php?id=' . rawurlencode($id) . '&v=' . substr(md5($m[$id]), 0, 8);
}

// يبحث عن خصم دائم مفعّل لهذا المستخدم — ينطبق على كل مشترياته (بدون شرط ID)
// الخصم دائم: لا ينتهي بالاستخدام، ويبقى فعّالاً ما دام الكود مفعّلاً في لوحة الأدمن
function find_active_discount($userId, $player = '') {
    $st = db()->prepare("SELECT ud.id AS id, ud.code AS code, ud.player_id AS player_id, c.type AS type, c.amount AS amount
        FROM user_discounts ud JOIN coupons c ON c.id = ud.coupon_id
        WHERE ud.user_id=? AND ud.status='active' AND c.active=1
        ORDER BY ud.id DESC");
    $st->execute([$userId]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
    return $d ?: null;
}
