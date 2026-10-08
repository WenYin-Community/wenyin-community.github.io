<?php
// wenyinos 认证中心 · 后台：用户详情与组分配
require __DIR__ . '/_layout.php';

$uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
$target = $uid > 0 ? wy_find_user_by_uid($uid) : false;
if(!$target)
{
	admin_header('用户不存在');
	echo '<div class="alert alert-danger">用户不存在</div>';
	admin_footer();
	exit;
}

$notice = '';
if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	admin_csrf_check();
	$group_ids = isset($_POST['groups']) && is_array($_POST['groups']) ? array_map('intval', $_POST['groups']) : array();

	$stmt = wy_db()->prepare('DELETE FROM user_group WHERE uid = ?');
	$stmt->execute(array($uid));
	foreach($group_ids as $gid)
	{
		$stmt = wy_db()->prepare('INSERT IGNORE INTO user_group (uid, group_id) VALUES (?, ?)');
		$stmt->execute(array($uid, $gid));
	}
	wy_rebuild_user_apps($uid);
	wy_audit('auth', $admin_user['uid'], 'admin_group_assign');
	$notice = '组分配已保存（站点准入已同步展开）';

	// 若改的是自己的组，防呆：仍要求保留超管组才可继续用后台
	if($uid == $admin_user['uid'] && !wy_is_super($uid))
	{
		$notice = '注意：你已移除自己的超级管理组，将无法再进入后台';
	}
}

$member_ids = array();
foreach(wy_user_groups($uid) as $g) $member_ids[] = intval($g['id']);
$all_groups = wy_db()->query('SELECT * FROM groups ORDER BY sort ASC, id ASC')->fetchAll();
$apps = wy_user_apps($uid);

admin_header('用户 · ' . $target['username']);
?>

<?php if($notice): ?><div class="alert alert-success py-2"><?php echo wy_h($notice); ?></div><?php endif; ?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="index.php">用户管理</a></li><li class="breadcrumb-item active"><?php echo wy_h($target['username']); ?></li></ol></nav>

<div class="row justify-content-center">
	<div class="col-lg-7">
		<div class="card mb-4">
			<div class="card-body">
				<h6 class="mb-3">账号信息</h6>
				<div class="small text-muted mb-1">用户名：<b class="text-dark"><?php echo wy_h($target['username']); ?></b>（UID <?php echo intval($target['uid']); ?>）</div>
				<div class="small text-muted mb-1">姓名：<?php echo wy_h($target['realname']) ?: '—'; ?>　邮箱：<?php echo wy_h((string)$target['email']) ?: '—'; ?></div>
				<div class="small text-muted mb-1">状态：<?php echo intval($target['status']) === 1 ? '正常' : '禁用'; ?>　最后登录：<?php echo $target['last_login'] ? date('Y-m-d H:i', $target['last_login']) : '—'; ?></div>
				<div class="small text-muted">当前可访问站点：<?php echo $apps ? wy_h(implode('、', $apps)) : '无'; ?></div>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<h6 class="mb-3">分配组（决定站点准入与两端角色映射）</h6>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
					<?php foreach($all_groups as $g): ?>
					<div class="form-check mb-2">
						<input class="form-check-input" type="checkbox" name="groups[]" value="<?php echo intval($g['id']); ?>" id="g<?php echo intval($g['id']); ?>" <?php echo in_array(intval($g['id']), $member_ids, true) ? 'checked' : ''; ?>>
						<label class="form-check-label" for="g<?php echo intval($g['id']); ?>">
							<b><?php echo wy_h($g['name']); ?></b>
							<span class="text-muted small">— 站点：<?php echo $g['apps'] !== '' ? wy_h($g['apps']) : '无'; ?>；BBS gid：<?php echo intval($g['bbs_gid']); ?>；禅道组：<?php echo intval($g['zentao_group']); ?></span>
						</label>
					</div>
					<?php endforeach; ?>
					<button class="btn btn-brand text-white mt-2">保存</button>
				</form>
			</div>
		</div>
	</div>
</div>

<?php admin_footer(); ?>
