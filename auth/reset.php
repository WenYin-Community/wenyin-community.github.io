<?php
// wenyinos 认证中心 · 重置密码（凭邮件链接中的一次性 token）
require __DIR__ . '/core.php';
require __DIR__ . '/page.php';
wy_session_start();

$error = '';
$done = FALSE;
$token = isset($_REQUEST['token']) ? trim((string)$_REQUEST['token']) : '';
$user = FALSE;

if($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token))
{
	$stmt = wy_db()->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expire > ? LIMIT 1');
	$stmt->execute(array($token, time()));
	$user = $stmt->fetch();
}

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
	$password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

	if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		$error = '会话已过期，请重试';
	}
	elseif(!$user)
	{
		$error = '链接无效或已过期，请重新发起找回密码';
	}
	elseif(strlen($password) < 6)
	{
		$error = '密码至少 6 位';
	}
	elseif($password !== $password2)
	{
		$error = '两次输入的密码不一致';
	}
	else
	{
		$stmt = wy_db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expire = 0, fails = 0, lock_until = 0, verified = 1 WHERE uid = ?');
		$stmt->execute(array(wy_password_hash(md5($password)), $user['uid']));
		wy_audit('auth', $user['uid'], 'reset_ok');
		$done = TRUE;
	}
}

if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);

wy_page_head('重置密码', '重置密码', '设置新密码');
?>

<?php if($done): ?>
<div class="success-box">
	<i class="fas fa-check-circle"></i> 密码已重置成功。<br>
	请使用新密码登录；登录后各社区站点（论坛 / 禅道）将自动同步新密码。
</div>
<div class="link-row" style="justify-content:center;">
	<a href="login.php">前往登录 <i class="fas fa-arrow-right" style="font-size:11px;"></i></a>
</div>
<?php elseif(!$user): ?>
<div class="alert alert-danger py-2 small" style="border-radius:8px;">链接无效或已过期，请重新发起「找回密码」。</div>
<div class="link-row" style="justify-content:center;">
	<a href="forgot.php">重新发送重置邮件</a>
</div>
<?php else: ?>

<?php wy_error_box($error); ?>

<form method="post" action="reset.php" autocomplete="off">
	<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
	<input type="hidden" name="token" value="<?php echo wy_h($token); ?>">
	<div class="field-group">
		<label class="form-label">账号</label>
		<input type="text" class="form-control" value="<?php echo wy_h($user['username']); ?>" disabled>
	</div>
	<div class="field-group">
		<label class="form-label" for="password">新密码</label>
		<input type="password" class="form-control" id="password" name="password" placeholder="至少 6 位" required autofocus>
	</div>
	<div class="field-group" style="margin-bottom:26px;">
		<label class="form-label" for="password2">确认新密码</label>
		<input type="password" class="form-control" id="password2" name="password2" placeholder="再次输入新密码" required>
	</div>
	<button type="submit" class="btn submit">重置密码</button>
</form>

<div class="link-row" style="justify-content:center;">
	<a href="login.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> 返回登录</a>
</div>
<?php endif; ?>

<?php wy_page_foot(); ?>
