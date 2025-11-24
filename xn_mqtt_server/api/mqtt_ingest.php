<?php
// MQTT 规则引擎 HTTP 转发入口
// 建议配置为：当收到心跳或业务消息时，POST JSON 到本接口

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

// 简单令牌校验
if (XN_INGEST_SHARED_SECRET !== '') {
    $token = (string)($_GET['token'] ?? '');
    if ($token !== XN_INGEST_SHARED_SECRET) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'forbidden']);
        exit;
    }
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$clientId = '';
$topic    = '';
$payload  = '';

if (is_array($data) && isset($data['client_id'])) {
    $clientId = (string)$data['client_id'];
    $topic    = isset($data['topic']) ? (string)$data['topic'] : '';
    if (array_key_exists('payload', $data)) {
        $payload = is_string($data['payload']) ? $data['payload'] : json_encode($data['payload']);
    }
}

if ($clientId === '') {
    $clientId = isset($_POST['client_id']) ? (string)$_POST['client_id'] : '';
    if (isset($_POST['topic'])) {
        $topic = (string)$_POST['topic'];
    }
    if (isset($_POST['payload'])) {
        $payload = (string)$_POST['payload'];
    }
}

if ($clientId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'missing client_id']);
    exit;
}

$db     = xn_get_db();
$device = xn_upsert_device($db, $clientId);

$now = date('Y-m-d H:i:s');
$ip  = $_SERVER['REMOTE_ADDR'] ?? null;

// 简单更新在线信息
$upd = $db->prepare('UPDATE devices SET last_seen_at = :ls, last_ip = :ip, updated_at = :u WHERE id = :id');
$upd->execute([
    ':ls' => $now,
    ':ip' => $ip,
    ':u'  => $now,
    ':id' => $device['id'],
]);

// 如需根据 topic / payload 做更进一步的业务（如注册、配置），
// 可在此处解析 $topic / $payload 并更新 meta_json 等字段。

echo json_encode(['status' => 'ok']);
