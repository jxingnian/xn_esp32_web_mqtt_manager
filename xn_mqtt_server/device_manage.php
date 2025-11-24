<?php
require_once __DIR__ . '/auth.php';
xn_require_login();

$db = xn_get_db();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $mode   = $action === 'start' ? 1 : 0;

    $upd = $db->prepare('UPDATE devices SET manage_mode = :m, updated_at = :u WHERE id = :id');
    $upd->execute([
        ':m'  => $mode,
        ':u'  => date('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    header('Location: device_manage.php?id=' . $id);
    exit;
}

$stmt = $db->prepare('SELECT * FROM devices WHERE id = :id');
$stmt->execute([':id' => $id]);
$device = $stmt->fetch();

if (!$device) {
    header('Location: index.php');
    exit;
}

$now    = time();
$ts     = $device['last_seen_at'] ? strtotime($device['last_seen_at']) : 0;
$online = $ts && ($now - $ts <= XN_DEVICE_OFFLINE_SECONDS);

include __DIR__ . '/header.php';
?>
<h2>设备管理：<?php echo htmlspecialchars($device['device_id'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p>
    在线状态：
    <?php if ($online): ?>
        <span class="status-dot status-online"></span>在线
    <?php else: ?>
        <span class="status-dot status-offline"></span>离线
    <?php endif; ?>
</p>
<p>最后在线时间：<?php echo $device['last_seen_at'] ? htmlspecialchars($device['last_seen_at'], ENT_QUOTES, 'UTF-8') : '-'; ?></p>
<p>当前管理模式：
    <?php if ((int)$device['manage_mode'] === 1): ?>
        <span class="badge badge-manage-on">管理中</span>
    <?php else: ?>
        <span class="badge badge-manage-off">空闲</span>
    <?php endif; ?>
</p>

<form method="post" style="margin-top: 12px;">
    <?php if ((int)$device['manage_mode'] === 1): ?>
        <button type="submit" name="action" value="stop" class="btn btn-danger">结束管理</button>
    <?php else: ?>
        <button type="submit" name="action" value="start" class="btn btn-primary">进入管理模式</button>
    <?php endif; ?>
    <a href="index.php" class="btn" style="margin-left:8px;">返回</a>
</form>

<p style="margin-top:16px; font-size:13px; color:#666;">
    当设备处于“管理中”状态时，可由 MQTT 规则或设备主动轮询后台接口，
    根据 manage_mode 字段决定是否进入“前后台通讯/暂停其他任务”模式。
</p>

<?php include __DIR__ . '/footer.php'; ?>
