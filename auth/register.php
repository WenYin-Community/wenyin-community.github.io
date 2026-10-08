<?php
// wenyinos 认证中心 · 自助注册（Turnstile 人机验证；注册后按后台策略验证：邮件验证 / 管理员通过）
require __DIR__ . '/core.php';
require __DIR__ . '/page.php';
wy_session_start();

$error = '';
$registered = FALSE;
$mail_sent = FALSE;
$form = array('username' => '', 'email' => '', 'realname' => '');

$register_enabled = wy_setting('register_enabled', '1') === '1';
$captcha_enabled = wy_captcha_enabled();
$captcha_site_key = wy_setting('turnstile_site_key', '');

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$form['username'] = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
	$form['email'] = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
	$form['realname'] = isset($_POST['realname']) ? trim((string)$_POST['realname']) : '';
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
	$password2 = isset($_POST['password2']) ? (string)$_POST['password2'] : '';

	if(!$register_enabled)
	{
		$error = '注册暂未开放，请联系社区管理员';
	}
	elseif(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		$error = '会话已过期，请重试';
	}
	elseif(!preg_match('/^[A-Za-z0-9_\.\-]{2,32}$/', $form['username']))
	{
		$error = '用户名需为 2-32 位字母 / 数字 / 下划线';
	}
	elseif(!is_email_format($form['email']))
	{
		$error = '请输入正确的邮箱地址';
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
		$captcha = wy_captcha_verify(array('response' => isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : ''));
		if($captcha !== TRUE) $error = $captcha;
		elseif(wy_register_ip_exceeded())
		{
			$error = '注册过于频繁，请稍后再试';
		}
		elseif(wy_find_user($form['username']))
		{
			$error = '该用户名暂不可用';
		}
		elseif(wy_find_user($form['email']))
		{
			$error = '该邮箱暂不可用';
		}
		else
		{
			$stmt = wy_db()->prepare('INSERT INTO users (username, password, email, realname, status, verified, create_date) VALUES (?, ?, ?, ?, 1, 0, ?)');
			$stmt->execute(array($form['username'], wy_password_hash(md5($password)), $form['email'], $form['realname'], time()));
			$uid = wy_db()->lastInsertId();

			$default_group = intval(wy_setting('register_group', '4'));
			$stmt = wy_db()->prepare('INSERT IGNORE INTO user_group (uid, group_id) VALUES (?, ?)');
			$stmt->execute(array($uid, $default_group));
			wy_expand_user_apps($uid);
			wy_audit('auth', $uid, 'register_ok');

			// 注册后不使用自动登录：账号须经验证后方可登录
			$registered = TRUE;
			$user = wy_find_user_by_uid($uid);
			if(wy_smtp_verify_enabled() && $user['email'] !== '')
			{
				$result = wy_send_verify_mail($user);
				$mail_sent = ($result === TRUE);
				if(!$mail_sent) error_log('[auth] verify mail failed: ' . $result . ' (uid=' . $uid . ')');
			}
		}
	}
}

if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);

wy_page_head('注册', '欢迎加入', '注册新账号');
?>

<?php if($registered): ?>
<div class="success-box">
	<i class="fas fa-check-circle"></i> 注册成功！<br>
	<?php if($mail_sent): ?>
	验证邮件已发送至 <b><?php echo wy_h($form['email']); ?></b>，请点击邮件中的链接完成验证后即可登录（24 小时内有效，注意查收垃圾邮件）。
	<?php elseif(wy_smtp_verify_enabled()): ?>
	账号已创建，但验证邮件发送失败。<br>请联系社区管理员在后台为您通过验证。
	<?php else: ?>
	账号待管理员验证，通过后即可登录；请联系社区管理员。
	<?php endif; ?>
</div>
<div class="link-row" style="justify-content:center;">
	<a href="login.php">前往登录 <i class="fas fa-arrow-right" style="font-size:11px;"></i></a>
</div>
<?php else: ?>

<?php if(!$register_enabled): ?>
<div class="alert alert-secondary py-2 small" style="border-radius:8px;">注册暂未开放，请联系社区管理员开通账号。</div>
<?php endif; ?>

<?php wy_error_box($error); ?>

<?php if($register_enabled): ?>
<form method="post" action="register.php" autocomplete="off">
	<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
	<div class="field-group">
		<label class="form-label" for="username">用户名</label>
		<input type="text" class="form-control" id="username" name="username" value="<?php echo wy_h($form['username']); ?>" placeholder="2-32 位字母 / 数字 / 下划线" required autofocus>
	</div>
	<div class="field-group">
		<label class="form-label" for="email">邮箱</label>
		<input type="email" class="form-control" id="email" name="email" value="<?php echo wy_h($form['email']); ?>" placeholder="用于账号验证与找回密码" required>
	</div>
	<div class="field-group">
		<label class="form-label" for="realname">姓名 / 昵称（选填）</label>
		<input type="text" class="form-control" id="realname" name="realname" value="<?php echo wy_h($form['realname']); ?>">
	</div>
	<div class="field-group">
		<label class="form-label" for="password">密码</label>
		<input type="password" class="form-control" id="password" name="password" placeholder="至少 6 位" required>
	</div>
	<div class="field-group" <?php echo $captcha_enabled ? '' : 'style="margin-bottom:26px;"'; ?>>
		<label class="form-label" for="password2">确认密码</label>
		<input type="password" class="form-control" id="password2" name="password2" placeholder="再次输入密码" required>
	</div>
	<?php if($captcha_enabled): ?>
	<div class="field-group" style="margin-bottom:26px;">
		<div class="cf-turnstile" data-sitekey="<?php echo wy_h($captcha_site_key); ?>" data-theme="light"></div>
	</div>
	<?php endif; ?>
	<button type="submit" class="btn submit">注 册</button>
</form>

<div class="link-row">
	<a href="forgot.php">忘记密码？</a>
	<a href="login.php">已有账号，去登录 <i class="fas fa-arrow-right" style="font-size:11px;"></i></a>
</div>
<?php else: ?>
<div class="link-row" style="justify-content:center;">
	<a href="login.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> 返回登录</a>
</div>
<?php endif; ?>

<?php endif; ?>

<?php if($captcha_enabled && !$registered): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

<?php wy_page_foot('注册后需完成账号验证（邮件验证或管理员通过）方可登录'); ?>
