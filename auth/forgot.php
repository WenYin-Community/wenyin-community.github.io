<?php
// wenyinos 认证中心 · 找回密码（发送重置邮件）
require __DIR__ . '/core.php';
require __DIR__ . '/page.php';
wy_session_start();

$error = '';
$done = FALSE;
$email = '';
$captcha_enabled = wy_captcha_enabled();
$captcha_site_key = wy_setting('turnstile_site_key', '');

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';

	if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		$error = '会话已过期，请重试';
	}
	elseif(($captcha = wy_captcha_verify(array('response' => isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : ''))) !== TRUE)
	{
		$error = $captcha;
	}
	elseif(!is_email_format($email))
	{
		$error = '请输入正确的邮箱地址';
	}
	else
	{
		$user = wy_find_user($email);
		$done = TRUE;   // 统一提示，不暴露邮箱是否存在

		if($user && intval($user['status']) === 1)
		{
			if(wy_reset_mail_allowed($user['uid']))
			{
				$token = wy_rand_hex(64);
				$stmt = wy_db()->prepare('UPDATE users SET reset_token = ?, reset_expire = ? WHERE uid = ?');
				$stmt->execute(array($token, time() + 1800, $user['uid']));

				$link = wy_base_url() . '/reset.php?token=' . rawurlencode($token);
				$result = wy_smtp_send($user['email'], '【玟茵开源社区】重置您的账号密码', wy_reset_mail_html($user['username'], $link, 30));
				if($result === TRUE)
				{
					wy_audit('auth', $user['uid'], 'reset_mail');
				}
				else
				{
					wy_audit('auth', $user['uid'], 'reset_mail_fail');
					error_log('[auth] reset mail failed: ' . $result . ' (uid=' . $user['uid'] . ')');
					$done = FALSE;
					$error = '邮件发送失败：' . $result . '（请联系社区管理员）';
				}
			}
			// 频率内重复请求：静默成功（防枚举）
		}
	}
}

if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);

wy_page_head('找回密码', '找回密码', '重置您的账号');
?>

<?php if($done): ?>
<div class="success-box">
	<i class="fas fa-paper-plane"></i> 重置邮件已发送。<br>
	若该邮箱已注册，您将在几分钟内收到包含重置链接的邮件（有效期 30 分钟），请同时检查垃圾邮件目录。
</div>
<div class="link-row" style="justify-content:center;">
	<a href="login.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> 返回登录</a>
</div>
<?php else: ?>

<?php wy_error_box($error); ?>

<form method="post" action="forgot.php" autocomplete="off">
	<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
	<div class="field-group" style="margin-bottom:26px;">
		<label class="form-label" for="email">注册邮箱</label>
		<input type="email" class="form-control" id="email" name="email" value="<?php echo wy_h($email); ?>" placeholder="请输入注册时使用的邮箱" required autofocus>
		<div class="field-hint">系统将向该邮箱发送重置链接；未设置邮箱的账号请联系管理员重置。</div>
	</div>
	<?php if($captcha_enabled): ?>
	<div class="field-group" style="margin-bottom:26px;">
		<div class="cf-turnstile" data-sitekey="<?php echo wy_h($captcha_site_key); ?>" data-theme="light"></div>
	</div>
	<?php endif; ?>
	<button type="submit" class="btn submit">发送重置邮件</button>
</form>

<div class="link-row" style="justify-content:center;">
	<a href="login.php"><i class="fas fa-arrow-left" style="font-size:11px;"></i> 返回登录</a>
</div>
<?php endif; ?>

<?php if($captcha_enabled && !$done): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>

<?php wy_page_foot(); ?>
