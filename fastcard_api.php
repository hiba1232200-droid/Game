<?php
require_once __DIR__ . '/db.php';

function fc_request($path, $params = [], $post = false) {
    $url = rtrim(FASTCARD_BASE, '/') . '/' . ltrim($path, '/');
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => [
            'api-token: ' . fastcard_token(),
            'Accept: application/json',
        ],
    ];
    if ($post) {
        $opts[CURLOPT_POST] = true;
        // الـ params بالـ body (معيار POST الصحيح)
        if ($params) $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    } else {
        if ($params) $opts[CURLOPT_URL] = $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($res, true), 'raw' => $res, 'err' => $err];
}

/** رصيد حسابك عند FastCard: {status, balance, email} */
function fc_profile() {
    $r = fc_request('profile');
    return $r['data'] ?? null;
}

function _map_product($p, $profit) {
    $rate = usd_rate();
    $type = $p['product_type'] ?? '';
    // التكلفة الحقيقية بالدولار: نفضّل 'cost' (الدقيق) ثم 'price'
    $costUsd = (float)($p['cost'] ?? 0);
    if ($costUsd <= 0) $costUsd = (float)($p['price'] ?? 0);
    // السعر النهائي للوحدة بالليرة (قبل التقريب)
    $rawPrice = $costUsd * $rate * (1 + $profit / 100);
    // منتجات "amount" سعر وحدتها صغير جداً والكمية بالآلاف،
    // فلا نقرّبه لصفر — نحتفظ بالكسور. باقي المنتجات نقرّبها لأقرب ليرة.
    if ($type === 'amount') {
        $price = round($rawPrice, 6); // دقة عشرية للوحدة
    } else {
        $price = round($rawPrice);
    }
    return [
        'id'        => (string)($p['id'] ?? ''),
        'name'      => $p['name'] ?? '',
        'cost'      => $costUsd,
        'price'     => $price,
        'category'  => $p['category_name'] ?? '',
        'parent_id' => (string)($p['parent_id'] ?? '0'),
        'image'     => $p['image'] ?? $p['img'] ?? '',
        'available' => !isset($p['available']) || $p['available'] == 1 || $p['available'] === true,
        'desc'      => $p['description'] ?? $p['desc'] ?? '',
        'params'    => is_array($p['params'] ?? null) ? $p['params'] : [],
        'qty_min'   => (int)($p['qty_values']['min'] ?? $p['qty_min'] ?? 1),
        'qty_max'   => (int)($p['qty_values']['max'] ?? $p['qty_max'] ?? 0), // 0 = بلا حد
        'type'      => $type,
    ];
}

/** قسم + أقسامه الفرعية + منتجاته — حسب توثيق Ahminix: GET /content/{categoryId} */
function fc_content($catId = 0, $force = false) {
    static $memo = [];
    $key = 'fc_content_' . $catId;
    if (!$force && isset($memo[$key])) return $memo[$key];
    if (!$force) {
        $c = cache_get($key);
        if ($c !== null) return $memo[$key] = $c;
    }
    $profit = (float)setting('profit_percent', DEFAULT_PROFIT);
    $r = fc_request('content/' . rawurlencode((string)$catId));
    $d = is_array($r['data']) ? $r['data'] : [];
    $out = ['categories' => [], 'products' => []];
    foreach (($d['categories'] ?? []) as $c) {
        if (!is_array($c) || !isset($c['id'])) continue;
        $out['categories'][] = [
            'id'    => (string)$c['id'],
            'name'  => $c['name'] ?? '',
            'image' => $c['image'] ?? $c['img'] ?? '',
        ];
    }
    foreach (($d['products'] ?? []) as $p) {
        if (!is_array($p)) continue;
        $out['products'][] = _map_product($p, $profit);
    }
    if ($out['categories'] || $out['products']) cache_set($key, $out, PRODUCTS_CACHE_TTL);
    return $memo[$key] = $out;
}

/** كل المنتجات (للمفضلة والبحث عن منتج عند الشراء) */
function store_products($force = false) {
    // تخزين مؤقت بمستوى الطلب الواحد: صفحات مثل السلة بتنادي store_product()
    // لكل منتج بالسلة على حدا — بدون هذا، كان عم يُعاد جلب وفك تشفير كامل
    // كتالوج المنتجات من الكاش لكل منتج بالسلة بدل مرة واحدة فقط
    static $memo = null;
    if (!$force) {
        if ($memo !== null) return $memo;
        $c = cache_get('fc_all_products');
        if ($c !== null) return $memo = $c;
    }
    $profit = (float)setting('profit_percent', DEFAULT_PROFIT);
    $r = fc_request('products');
    $items = is_array($r['data']) ? (isset($r['data'][0]) ? $r['data'] : ($r['data']['products'] ?? $r['data']['data'] ?? [])) : [];
    $list = [];
    foreach ($items as $p) if (is_array($p)) $list[] = _map_product($p, $profit);
    if ($list) cache_set('fc_all_products', $list, PRODUCTS_CACHE_TTL);
    return $memo = ($list ?: (cache_get('fc_all_products') ?? []));
}

function store_product($id) {
    foreach (store_products() as $p) if ((string)$p['id'] === (string)$id) return $p;
    return null;
}

/** تصفية كامل كاش أسعار المنتجات (الأقسام الفرعية + قائمة كل المنتجات)
 *  لازم تُستدعى فوراً عند تغيير سعر الصرف أو هامش الربح، وإلا الزبون
 *  بيستمر يشوف الأسعار القديمة المخزّنة بالكاش لحد ساعة كاملة. */
function clear_products_cache() {
    try {
        db()->exec("DELETE FROM cache WHERE key = 'fc_all_products' OR key LIKE 'fc_content_%'");
    } catch (Exception $e) {}
}

/** إنشاء طلب — POST حسب التوثيق، idempotent عبر order_uuid */
function fc_new_order($productId, $qty, $playerId, $uuid) {
    $params = ['qty' => $qty, 'order_uuid' => $uuid];
    if ($playerId !== '') $params['playerId'] = $playerId;
    return fc_request('newOrder/' . rawurlencode($productId) . '/params', $params, true);
}

/** فحص حالة طلب بالـ UUID — بيرجع status + order_id + الأكواد إن وجدت */
function fc_check_uuid($uuid) {
    $r = fc_request('check', ['orders' => json_encode([$uuid]), 'uuid' => 1]);
    $d = is_array($r['data']) ? $r['data'] : [];
    foreach (($d['data'] ?? []) as $o) {
        if (!is_array($o)) continue;
        return [
            'status' => $o['status'] ?? null,          // accept / processing / reject
            'id'     => $o['order_id'] ?? null,
            'codes'  => is_array($o['replay_api'] ?? null) ? $o['replay_api'] : [],
        ];
    }
    return null;
}
