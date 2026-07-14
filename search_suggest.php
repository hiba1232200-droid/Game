<?php
require_once __DIR__ . '/fastcard_api.php';
header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }
$ql = mb_strtolower($q);
$results = [];
foreach (store_products() as $p) {
    if (mb_strpos(mb_strtolower($p['name']), $ql) !== false
        || mb_strpos(mb_strtolower($p['category']), $ql) !== false) {
        $results[] = ['name' => $p['name'], 'cat' => $p['category']];
        if (count($results) >= 8) break;
    }
}
echo json_encode($results, JSON_UNESCAPED_UNICODE);
