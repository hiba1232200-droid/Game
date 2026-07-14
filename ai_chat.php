<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $data = []) {
    echo json_encode(['ok' => $ok] + $data, JSON_UNESCAPED_UNICODE); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim((string)($in['message'] ?? ''));
$history = is_array($in['history'] ?? null) ? $in['history'] : [];

$U = current_user();

// ===== كشف طلب تسريع الطلب وإشعار الأدمن =====
function detect_speedup($text) {
    $t = mb_strtolower($text);
    $keys = ['تسريع', 'سرعو', 'سرّعو', 'استعجل', 'مستعجل', 'متأخر', 'تأخر', 'وين طلبي', 'طلبي ما وصل',
             'لسا ما وصل', 'ما وصلني', 'يصلني الطلب', 'بدي طلبي', 'اين طلبي', 'وينو طلبي', 'speed', 'urgent'];
    foreach ($keys as $k) if (mb_strpos($t, $k) !== false) return true;
    return false;
}

// ===== كشف طلب التواصل مع الدعم البشري =====
function detect_support($text) {
    $t = mb_strtolower($text);
    $keys = ['اتواصل مع الدعم', 'تواصل مع الدعم', 'بدي دعم', 'بدي الدعم', 'خدمة العملاء', 'خدمة الزبائن',
             'موظف', 'حدا حقيقي', 'شخص حقيقي', 'انسان حقيقي', 'إنسان حقيقي', 'تواصل بشري', 'دعم بشري',
             'بدي احكي مع حدا', 'بدي احكي مع موظف', 'اكلم موظف', 'احكي مع موظف', 'اكلم حدا', 'مساعدة بشرية',
             'حابب اتواصل', 'بدي اتواصل', 'customer service', 'human', 'agent', 'real person'];
    foreach ($keys as $k) if (mb_strpos($t, $k) !== false) return true;
    return false;
}

// إذا طلب التواصل مع الدعم البشري: ننبّه الأدمن، ونعطي الزبون رابط صفحة الدعم (المساعد يضل يشتغل عادي)
if ($U && detect_support($message)) {
    notify_admin("🎧 <b>طلب تواصل مع الدعم</b>\n"
        . "الاسم: " . e($U['name']) . "\n"
        . "الإيميل: " . e($U['email']) . "\n"
        . "رقم المستخدم: #" . $U['id'] . "\n─────────\n"
        . "الرسالة: " . e(mb_substr($message, 0, 300)) . "\n\n↩️ ردّ (Reply) على هالرسالة للرد على الزبون");
    out(true, [
        'reply' => "أكيد! للتواصل المباشر مع فريق الدعم البشري، افتح صفحة الدعم من الزر تحت 👇\nرح يردّ عليك موظف بأقرب وقت.",
        'support_link' => '/support_chat.php',
    ]);
}

if ($U && detect_speedup($message)) {
    // آخر طلب للمستخدم (قيد التنفيذ إن وجد)
    $st = db()->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$U['id']]);
    $lastOrder = $st->fetch(PDO::FETCH_ASSOC);
    $orderInfo = $lastOrder
        ? ("آخر طلب: #{$lastOrder['id']} — {$lastOrder['product_name']} ×{$lastOrder['qty']}\n"
           . ($lastOrder['player_id'] ? "ID اللاعب: {$lastOrder['player_id']}\n" : "")
           . "الإجمالي: " . number_format($lastOrder['total']) . " ل.س\n"
           . "الحالة: " . ($lastOrder['status'] === 'accept' ? 'تم التنفيذ ✅' : ($lastOrder['status'] === 'reject' ? 'مرفوض' : 'قيد التنفيذ ⏳')))
        : "لا يوجد طلبات سابقة";

    notify_admin("🚨 <b>طلب تسريع من زبون</b>\n"
        . "الاسم: " . e($U['name']) . "\n"
        . "الإيميل: " . e($U['email']) . "\n"
        . "الرصيد: " . number_format($U['balance']) . " ل.س\n"
        . "─────────\n"
        . $orderInfo . "\n─────────\n"
        . "رسالة الزبون: " . e(mb_substr($message, 0, 200)));

    // نعلم Gemini أنه تم إبلاغ الفريق (ليطمئن الزبون)
    $message .= "\n\n[ملاحظة للنظام: تم إبلاغ فريق الدعم بطلب التسريع تلقائياً، طمئن الزبون أن فريقنا تم تنبيهه وسيتابع طلبه فوراً.]";
}

$apiKey = env_or('GEMINI_API_KEY', '');

// وضع تشخيص: افتح /ai_chat.php?debug=1 بالمتصفح
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GEMINI_API_KEY موجود: " . ($apiKey !== '' ? 'نعم (طول: ' . strlen($apiKey) . ')' : 'لا ❌') . "\n";
    if ($apiKey === '') { echo "\n⚠️ المفتاح مش محطوط على Railway. ضيف متغير GEMINI_API_KEY"; exit; }
    $models = ['gemini-1.5-flash', 'gemini-1.5-flash-latest', 'gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-flash-latest'];
    foreach ($models as $m) {
        $testUrl = "https://generativelanguage.googleapis.com/v1beta/models/$m:generateContent?key=" . urlencode($apiKey);
        $ch = curl_init($testUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['contents' => [['role' => 'user', 'parts' => [['text' => 'قل مرحبا']]]]]),
        ]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $okMark = $code === 200 ? ' ✅ شغّال!' : '';
        echo "$m => HTTP $code$okMark\n";
        if ($code === 200) { echo "\n🎉 استخدم هذا الموديل: $m\n"; break; }
    }
    exit;
}

if ($message === '') out(false, ['msg' => 'اكتب سؤالك']);
if ($apiKey === '') {
    out(false, ['msg' => 'المساعد الذكي غير مفعّل حالياً. تواصل معنا عبر واتساب.']);
}

// ===== معلومات الموقع للسياق =====
$cats = [];
try {
    $root = fc_content(0);
    foreach (array_slice($root['categories'], 0, 15) as $c) $cats[] = $c['name'];
} catch (Exception $e) {}
$catsList = $cats ? implode('، ', $cats) : 'الألعاب، البطاقات، تطبيقات التواصل، خدمات الدفع';
$sham = shamcash_number() ? 'سيرياتيل كاش وشام كاش' : 'سيرياتيل كاش';
$wa1 = WHATSAPP_NUM_1; $wa2 = WHATSAPP_NUM_2;

// ===== تعليمات النظام (محصورة بمواضيع الموقع) =====
$system = "أنت المساعد الذكي لمتجر \"" . STORE_NAME . "\"، متجر سوري لشحن الألعاب والبطاقات الرقمية.

مهمتك: مساعدة الزبائن بأسئلتهم عن المتجر فقط. تحدث باللهجة السورية البيضاء بشكل ودود ومختصر.

معلومات المتجر:
- نبيع: شحن ألعاب (ببجي موبايل، فري فاير، وغيرها)، بطاقات رقمية، اشتراكات، خدمات دفع.
- الأقسام المتوفرة: $catsList.
- طرق الدفع/الإيداع: $sham (تلقائي — الزبون يحوّل ويدخل رقم العملية فيُضاف الرصيد لمحفظته).
- آلية الطلب: الزبون يسجّل دخول، يشحن محفظته، يختار المنتج، يدخل ID اللاعب لمنتجات الألعاب، يتأكد من اسم اللاعب، ثم يشتري.
- لمنتجات ببجي وفري فاير: يوجد تحقق من اسم اللاعب قبل الشراء لضمان صحة الـ ID.
- التسليم فوري خلال دقائق (تلقائي على مدار الساعة).
- الأسعار بالليرة السورية، ويمكن عرضها بالدولار من زر العملة.

قواعد مهمة جداً:
- جاوب فقط عن مواضيع تخص المتجر (المنتجات، الشحن، الأسعار، الطلب، الدفع، الحساب، المحفظة، التحقق).
- إذا سُئلت عن أي موضوع خارج المتجر (سياسة، رياضة، برمجة، معلومات عامة، مواضيع شخصية، أو أي سؤال لا علاقة له بالمتجر)، اعتذر بلطف وقل إنك مساعد خاص بمتجر " . STORE_NAME . " وتساعد فقط بأمور المتجر، واقترح سؤالاً متعلقاً بالمتجر. لا تجب على السؤال الخارجي إطلاقاً.
- لا تخترع أسعاراً محددة لمنتجات (الأسعار تتغير) — وجّه الزبون لتصفح القسم المناسب لرؤية السعر الحالي.
- لا تعطِ معلومات تقنية عن كيفية عمل الموقع الداخلي أو الـ API أو قاعدة البيانات.
- كن مختصراً (2-4 جمل عادةً).
- إذا واجه الزبون مشكلة تحتاج تدخل بشري، أو طلب رقم التواصل أو الدعم، أعطه رقمي الواتساب كاملين بدون نقص.
- أرقام واتساب الدعم (اكتبها كاملة بين علامتي اقتباس هكذا): \"$wa1\" أو \"$wa2\" — انسخها حرفياً بكل أرقامها العشرة ولا تختصر أي رقم.";

// ===== بناء محتوى المحادثة لـ Gemini =====
$contents = [];
foreach ($history as $h) {
    $role = ($h['role'] ?? '') === 'assistant' ? 'model' : 'user';
    $content = trim((string)($h['content'] ?? ''));
    if ($content !== '') $contents[] = ['role' => $role, 'parts' => [['text' => $content]]];
}
$contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

$payload = [
    'system_instruction' => ['parts' => [['text' => $system]]],
    'contents' => $contents,
    'generationConfig' => ['maxOutputTokens' => 600, 'temperature' => 0.7],
];

// ===== نداء Gemini API مع محاولة عدة موديلات =====
$models = ['gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-flash-latest', 'gemini-2.5-flash'];
$d = null; $code = 0;
foreach ($models as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $d = json_decode($res, true);
        if (is_array($d) && !empty($d['candidates'])) break; // نجح
    }
    // لو 429 أو 503 أو 404 نجرّب الموديل التالي
    $d = null;
}

if ($code !== 200 || !is_array($d)) {
    out(false, ['msg' => 'المساعد مشغول حالياً، حاول بعد قليل أو تواصل عبر واتساب.']);
}

// استخراج النص من رد Gemini
$reply = '';
$parts = $d['candidates'][0]['content']['parts'] ?? [];
foreach ($parts as $p) {
    if (isset($p['text'])) $reply .= $p['text'];
}
$reply = trim($reply);
if ($reply === '') $reply = 'ما فهمت سؤالك تماماً، ممكن توضّح أكتر؟';

out(true, ['reply' => $reply]);
