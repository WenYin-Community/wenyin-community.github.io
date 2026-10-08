<?php
// wenyinos 认证中心 · 后台：应用密钥管理
require __DIR__ . '/_layout.php';

$notice = '';
if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	admin_csrf_check();
	if((string)$_POST['action'] === 'rotate')
	{
		$app_id = trim((string)$_POST['app_id']);
		$new_secret = wy_rand_hex(64);
		$stmt = wy_db()->prepare('UPDATE apps SET secret = ? WHERE app_id = ?');
		$stmt->execute(array($new_secret, $app_id));
		wy_audit('auth', $admin_user['uid'], 'admin_app_rotate');
		$notice = '应用 ' . $app_id . ' 密钥已轮换——务必同步更新分站配置，否则该站 API 全部失败';
	}
}

$apps = wy_db()->query('SELECT * FROM apps ORDER BY app_id ASC')->fetchAll();

admin_header('应用密钥');
?>

<?php if($notice): ?><div class="alert alert-warning py-2"><?php echo wy_h($notice); ?></div><?php endif; ?>

<div class="alert alert-light border small mb-4">
	密钥用于分站调用 API 的 HMAC 签名。轮换后需同步更新 BBS 插件配置（xn_sso）与禅道扩展配置（wyauth.php）。
</div>

<?php foreach($apps as $app): ?>
<div class="card mb-3">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h6 class="mb-0"><?php echo wy_h($app['name']); ?> <span class="text-muted small">(<?php echo wy_h($app['app_id']); ?>)</span></h6>
			<span class="badge <?php echo intval($app['status']) === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo intval($app['status']) === 1 ? '启用' : '停用'; ?></span>
		</div>
		<div class="input-group input-group-sm mb-3">
			<span class="input-group-text">secret</span>
			<input type="text" class="form-control font-monospace" value="<?php echo wy_h($app['secret']); ?>" readonly>
		</div>
		<form method="post" onsubmit="return confirm('确认轮换密钥？分站需同步更新配置。');">
			<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
			<input type="hidden" name="action" value="rotate">
			<input type="hidden" name="app_id" value="<?php echo wy_h($app['app_id']); ?>">
			<button class="btn btn-sm btn-outline-danger">轮换密钥</button>
		</form>
	</div>
</div>
<?php endforeach; ?>

<?php admin_footer(); ?>
