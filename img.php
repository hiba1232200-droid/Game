<?php
require_once __DIR__ . '/db.php';

$id = (string)($_GET['id'] ?? '');
if ($id === '') { http_response_code(404); exit; }

try {
    $st = db()->prepare("SELECT mime, data FROM item_images WHERE item_id=?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $row = null; }

if (!$row || !$row['data']) { http_response_code(404); exit; }

header('Content-Type: ' . ($row['mime'] ?: 'image/png'));
header('Cache-Control: public, max-age=604800, immutable');
echo base64_decode($row['data']);
