<?php
// wenyinos 认证中心 · 后台：系统设置（邮件 SMTP / 自助注册）
require __DIR__ . '/_layout.php';

$notice = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	admin_csrf_check();
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

	if($action === 'save_mail')
	{
		wy_setting_set('smtp_host', trim((string)$_POST['smtp_host']));
		wy_setting_set('smtp_port', intval($_POST['smtp_port']));
		wy_setting_set('smtp_secure', in_array($_POST['smtp_secure'], array('ssl', 'tls', 'none'), TRUE) ? $_POST['smtp_secure'] : 'ssl');
		wy_setting_set('smtp_user', trim((string)$_POST['smtp_user']));
		if((string)$_POST['smtp_pass'] !== '') wy_setting_set('smtp_pass', (string)$_POST['smtp_pass']);
		wy_setting_set('smtp_from', trim((string)$_POST['smtp_from']));
		wy_setting_set('smtp_from_name', trim((string)$_POST['smtp_from_name']));
		wy_audit('auth', $admin_user['uid'], 'admin_smtp_save');
		$notice = '邮件设置已保存（密码留空表示不修改）';
	}
	elseif($action === 'save_register')
	{
		wy_setting_set('register_enabled', isset($_POST['register_enabled']) && (string)$_POST['register_enabled'] === '1' ? '1' : '0');
		wy_setting_set('register_group', intval($_POST['register_group']));
		wy_setting_set('smtp_verify', isset($_POST['smtp_verify']) && (string)$_POST['smtp_verify'] === '1' ? '1' : '0');
		wy_audit('auth', $admin_user['uid'], 'admin_register_save');
		$notice = '注册设置已保存';
	}
	elseif($action === 'save_captcha')
	{
		wy_setting_set('turnstile_site_key', trim((string)$_POST['turnstile_site_key']));
		if((string)$_POST['turnstile_secret'] !== '') wy_setting_set('turnstile_secret', trim((string)$_POST['turnstile_secret']));
		wy_audit('auth', $admin_user['uid'], 'admin_captcha_save');
		$notice = '人机验证设置已保存（两项均填写后注册页生效；清空 Site Key 即关闭）';
	}
	elseif($action === 'test_mail')
	{
		$to = trim((string)$_POST['test_to']);
		if(!is_email_format($to))
		{
			$error = '测试收件邮箱格式错误';
		}
		else
		{
			$result = wy_smtp_send($to, '【玟茵开源社区】SMTP 测试邮件', '<p style="font-family:sans-serif;font-size:14px;">这是一封来自认证中心后台的测试邮件，收到即表示 SMTP 配置正常。</p>');
			if($result === TRUE) $notice = '测试邮件已发送到 ' . $to . '，请查收';
			else $error = '发送失败：' . $result;
		}
	}
}

$groups = wy_db()->query('SELECT * FROM groups ORDER BY sort ASC, id ASC')->fetchAll();

admin_header('系统设置');
?>

<?php if($notice): ?><div class="alert alert-success py-2"><?php echo wy_h($notice); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger py-2"><?php echo wy_h($error); ?></div><?php endif; ?>

<div class="row">
	<div class="col-lg-6">
		<div class="card mb-4">
			<div class="card-body">
				<h6 class="mb-3"><i class="fas fa-envelope"></i> 邮件设置（SMTP · 用于找回密码等通知）</h6>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
					<input type="hidden" name="action" value="save_mail">
					<div class="row g-2">
						<div class="col-8"><label class="form-label small">SMTP 服务器</label><input class="form-control form-control-sm" name="smtp_host" value="<?php echo wy_h(wy_setting('smtp_host', '')); ?>" placeholder="smtp.exmail.qq.com"></div>
						<div class="col-4"><label class="form-label small">端口</label><input class="form-control form-control-sm" name="smtp_port" value="<?php echo wy_h(wy_setting('smtp_port', '465')); ?>"></div>
						<div class="col-4">
							<label class="form-label small">加密方式</label>
							<?php $sec = wy_setting('smtp_secure', 'ssl'); ?>
							<select class="form-select form-select-sm" name="smtp_secure">
								<option value="ssl" <?php echo $sec === 'ssl' ? 'selected' : ''; ?>>SSL（465）</option>
								<option value="tls" <?php echo $sec === 'tls' ? 'selected' : ''; ?>>STARTTLS（587）</option>
								<option value="none" <?php echo $sec === 'none' ? 'selected' : ''; ?>>无加密</option>
							</select>
						</div>
						<div class="col-8"><label class="form-label small">账号</label><input class="form-control form-control-sm" name="smtp_user" value="<?php echo wy_h(wy_setting('smtp_user', '')); ?>" autocomplete="off"></div>
						<div class="col-12"><label class="form-label small">密码 / 授权码（留空不修改）</label><input type="password" class="form-control form-control-sm" name="smtp_pass" value="" autocomplete="new-password"></div>
						<div class="col-6"><label class="form-label small">发件人邮箱</label><input class="form-control form-control-sm" name="smtp_from" value="<?php echo wy_h(wy_setting('smtp_from', '')); ?>" placeholder="noreply@wenyinos.com"></div>
						<div class="col-6"><label class="form-label small">发件人名称</label><input class="form-control form-control-sm" name="smtp_from_name" value="<?php echo wy_h(wy_setting('smtp_from_name', '玟茵开源社区')); ?>"></div>
					</div>
					<button class="btn btn-sm btn-brand text-white mt-3">保存邮件设置</button>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<h6 class="mb-3"><i class="fas fa-vial"></i> 发送测试邮件</h6>
				<form method="post" class="row g-2 align-items-end">
					<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
					<input type="hidden" name="action" value="test_mail">
					<div class="col-8"><label class="form-label small">收件邮箱</label><input class="form-control form-control-sm" name="test_to" placeholder="your@email.com"></div>
					<div class="col-4"><button class="btn btn-sm btn-outline-secondary w-100">发送测试</button></div>
				</form>
			</div>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card">
			<div class="card-body">
				<h6 class="mb-3"><i class="fas fa-user-plus"></i> 自助注册设置</h6>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
					<input type="hidden" name="action" value="save_register">
					<div class="mb-3">
						<label class="form-label small">注册开关</label>
						<?php $regen = wy_setting('register_enabled', '1'); ?>
						<div class="d-flex gap-3">
							<div class="form-check"><input class="form-check-input" type="radio" name="register_enabled" value="1" id="reg1" <?php echo $regen === '1' ? 'checked' : ''; ?>><label class="form-check-label" for="reg1">开放注册</label></div>
							<div class="form-check"><input class="form-check-input" type="radio" name="register_enabled" value="0" id="reg0" <?php echo $regen !== '1' ? 'checked' : ''; ?>><label class="form-check-label" for="reg0">关闭注册</label></div>
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label small">新用户默认组（决定注册后可访问的站点）</label>
						<?php $reggrp = intval(wy_setting('register_group', '4')); ?>
						<select class="form-select form-select-sm" name="register_group">
							<?php foreach($groups as $g): ?>
							<option value="<?php echo intval($g['id']); ?>" <?php echo $reggrp === intval($g['id']) ? 'selected' : ''; ?>>
								<?php echo wy_h($g['name']); ?> — 站点：<?php echo $g['apps'] !== '' ? wy_h($g['apps']) : '无'; ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label small">SMTP 认证（注册账号验证方式）</label>
						<?php $sv = wy_setting('smtp_verify', '0'); ?>
						<div class="d-flex flex-column gap-2">
							<div class="form-check">
								<input class="form-check-input" type="radio" name="smtp_verify" value="1" id="sv1" <?php echo $sv === '1' ? 'checked' : ''; ?>>
								<label class="form-check-label" for="sv1">开启：注册后自动发送验证邮件，点击链接即通过</label>
							</div>
							<div class="form-check">
								<input class="form-check-input" type="radio" name="smtp_verify" value="0" id="sv0" <?php echo $sv !== '1' ? 'checked' : ''; ?>>
								<label class="form-check-label" for="sv0">关闭：不发邮件，由管理员在「用户管理」中点击「通过验证」</label>
							</div>
						</div>
					</div>
					<button class="btn btn-sm btn-brand text-white">保存注册设置</button>
				</form>
				<p class="text-muted small mt-3 mb-0">注册入口：<a href="../register.php" target="_blank">../register.php</a>（登录页也有「注册新账号」链接）。新用户注册后为「待验证」，通过验证后方可登录（两种通道见上）。</p>
			</div>
		</div>

		<div class="card mt-4">
			<div class="card-body">
				<h6 class="mb-3"><i class="fas fa-shield-halved"></i> 人机验证（Cloudflare Turnstile · 仅注册页）</h6>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
					<input type="hidden" name="action" value="save_captcha">
					<div class="mb-2">
						<label class="form-label small">Site Key</label>
						<input class="form-control form-control-sm" name="turnstile_site_key" value="<?php echo wy_h(wy_setting('turnstile_site_key', '')); ?>" autocomplete="off">
					</div>
					<div class="mb-3">
						<label class="form-label small">Secret Key（留空不修改）</label>
						<input type="password" class="form-control form-control-sm" name="turnstile_secret" value="" autocomplete="new-password">
					</div>
					<button class="btn btn-sm btn-brand text-white">保存人机验证设置</button>
				</form>
				<p class="text-muted small mt-3 mb-0">
					在 <a href="https://dash.cloudflare.com/" target="_blank" rel="noopener">Cloudflare 控制台</a> → Turnstile 创建站点获取密钥（免费，需填写域名）。
					两项均填写后注册页生效；清空 Site Key 即关闭。
					官方测试密钥：Site Key <code>1x00000000000000000000AA</code>（始终通过）、Secret Key <code>1x0000000000000000000000000000000AA</code>，可用于本地联调。
				</p>
			</div>
		</div>
	</div>
</div>

<?php admin_footer(); ?>
