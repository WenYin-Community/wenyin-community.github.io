<?php
// wenyinos 认证中心 · 邮件验证（注册验证链接落地页）
require __DIR__ . '/core.php';
require __DIR__ . '/page.php';
wy_session_start();

$ok = FALSE;
$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token))
{
	$stmt = wy_db()->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expire > ? LIMIT 1');
	$stmt->execute(array('verify:' . $token, time()));
	$user = $stmt->fetch();
	if($user)
	{
		$stmt = wy_db()->prepare('UPDATE users SET verified = 1, reset_token = NULL, reset_expire = 0 WHERE uid = ?');
		$stmt->execute(array($user['uid']));
		wy_audit('auth', $user['uid'], 'verify_mail_ok');
		$ok = TRUE;
	}
}

wy_page_head('验证邮箱', '账号验证', '验证您的邮箱');
?>

<?php if($ok): ?>
<div class="success-box">
	<i class="fas fa-check-circle"></i> 邮箱验证成功，账号已激活！<br>
	现在即可登录，论坛与项目管理将自动通行。
</div>
<div class="link-row" style="justify-content:center;">
	<a href="login.php">前往登录 <i class="fas fa-arrow-right" style="font-size:11px;"></i></a>
</div>
<?php else: ?>
<div class="alert alert-danger py-2 small" style="border-radius:8px;">链接无效或已过期，请重新注册获取验证邮件，或联系社区管理员为您的账号通过验证。</div>
<div class="link-row" style="justify-content:center;">
	<a href="login.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> 返回登录</a>
</div>
<?php endif; ?>

<?php wy_page_foot(); ?>
