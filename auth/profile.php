<?php
// wenyinos 认证中心 · 账号设置（自助修改：昵称 / 密码 / 邮箱）
require __DIR__ . '/core.php';
wy_session_start();

$user = wy_current_user();
if(!$user)
{
	header('Location: login.php');
	exit;
}

$error = '';
$error_card = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

	if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		$error = '会话已过期，请重试';
	}
	elseif($action === 'nickname')
	{
		$realname = trim((string)(isset($_POST['realname']) ? $_POST['realname'] : ''));
		if(strlen($realname) > 60)
		{
			$error = '昵称最长 60 个字符';
			$error_card = 'nickname';
		}
		else
		{
			$stmt = wy_db()->prepare('UPDATE users SET realname = ? WHERE uid = ?');
			$stmt->execute(array($realname, $user['uid']));
			wy_audit('auth', $user['uid'], 'profile_nickname');
			header('Location: profile.php?saved=nickname');
			exit;
		}
	}
	elseif($action === 'password')
	{
		$old = (string)(isset($_POST['oldpassword']) ? $_POST['oldpassword'] : '');
		$new = (string)(isset($_POST['newpassword']) ? $_POST['newpassword'] : '');
		$new2 = (string)(isset($_POST['newpassword2']) ? $_POST['newpassword2'] : '');

		if(!wy_password_verify(md5($old), $user['password']))
		{
			$error = '原密码不正确';
			$error_card = 'password';
		}
		elseif(strlen($new) < 6)
		{
			$error = '新密码至少 6 位';
			$error_card = 'password';
		}
		elseif($new !== $new2)
		{
			$error = '两次输入的新密码不一致';
			$error_card = 'password';
		}
		else
		{
			$stmt = wy_db()->prepare('UPDATE users SET password = ?, fails = 0, lock_until = 0 WHERE uid = ?');
			$stmt->execute(array(wy_password_hash(md5($new)), $user['uid']));
			wy_audit('auth', $user['uid'], 'profile_password');
			header('Location: profile.php?saved=password');
			exit;
		}
	}
	elseif($action === 'basic')
	{
		$mobile = trim((string)(isset($_POST['mobile']) ? $_POST['mobile'] : ''));
		$qq = trim((string)(isset($_POST['qq']) ? $_POST['qq'] : ''));
		$gender = isset($_POST['gender']) ? (string)$_POST['gender'] : 'u';
		$birthday = trim((string)(isset($_POST['birthday']) ? $_POST['birthday'] : ''));

		if($mobile !== '' && !preg_match('/^[0-9+\- ]{5,20}$/', $mobile))
		{
			$error = '手机号格式不正确';
			$error_card = 'basic';
		}
		elseif($qq !== '' && !preg_match('/^[0-9]{5,20}$/', $qq))
		{
			$error = 'QQ 号格式不正确';
			$error_card = 'basic';
		}
		elseif(!in_array($gender, array('u', 'f', 'm'), TRUE))
		{
			$error = '性别取值不正确';
			$error_card = 'basic';
		}
		elseif($birthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday))
		{
			$error = '生日格式不正确（YYYY-MM-DD）';
			$error_card = 'basic';
		}
		else
		{
			$stmt = wy_db()->prepare('UPDATE users SET mobile = ?, qq = ?, gender = ?, birthday = ? WHERE uid = ?');
			$stmt->execute(array($mobile, $qq, $gender, $birthday !== '' ? $birthday : NULL, $user['uid']));
			wy_audit('auth', $user['uid'], 'profile_basic');
			header('Location: profile.php?saved=basic');
			exit;
		}
	}
	elseif($action === 'email')
	{
		$newemail = trim((string)(isset($_POST['newemail']) ? $_POST['newemail'] : ''));
		$code = trim((string)(isset($_POST['email_code']) ? $_POST['email_code'] : ''));

		if(!is_email_format($newemail))
		{
			$error = '请输入正确的新邮箱地址';
			$error_card = 'email';
		}
		elseif(empty($_SESSION['email_code']) || empty($_SESSION['email_code_email']) || empty($_SESSION['email_code_expire'])
			|| $_SESSION['email_code_email'] !== $newemail
			|| intval($_SESSION['email_code_expire']) < time())
		{
			$error = '请先点击「发送验证码」并完成邮箱认证（验证码 10 分钟内有效）';
			$error_card = 'email';
		}
		elseif(!hash_equals((string)$_SESSION['email_code'], $code))
		{
			$error = '验证码不正确';
			$error_card = 'email';
		}
		else
		{
			// 一次性：无论后续是否成功都失效，防重放
			unset($_SESSION['email_code'], $_SESSION['email_code_email'], $_SESSION['email_code_expire'], $_SESSION['email_code_time']);

			$occupied = wy_find_user($newemail);
			if($occupied)
			{
				$error = '该邮箱已被其他账号使用';
				$error_card = 'email';
			}
			else
			{
				$stmt = wy_db()->prepare('UPDATE users SET email = ?, verified = 1 WHERE uid = ?');
				$stmt->execute(array($newemail, $user['uid']));
				wy_audit('auth', $user['uid'], 'profile_email');
				header('Location: profile.php?saved=email');
				exit;
			}
		}
	}
}

if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);

$saved = isset($_GET['saved']) ? (string)$_GET['saved'] : '';
$saved_msg = array(
	'nickname' => '昵称已更新',
	'basic' => '基本资料已更新（手机号 / QQ / 性别 / 生日）',
	'password' => '密码已修改（各社区站点将在下次登录时自动同步）',
	'email' => '邮箱已更新并通过认证',
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>账号设置 · 玟茵开源社区</title>
<link rel="stylesheet" href="../assets/lib/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../assets/lib/font-awesome/css/all.min.css">
<style>
html { height: 100%; }
body { min-height: 100%; margin: 0; font-family: -apple-system, "Segoe UI", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif; }

.panel-page { min-height: 100vh; display: flex; }

/* ── 左侧品牌区（与面板同款）──── */
.brand-side { position: relative; flex: 0 0 38%; overflow: hidden; color: #fff; background: linear-gradient(160deg, #402a75 0%, #6d4bb8 48%, #8f6ed6 100%); }
.brand-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.deco-1 { width: 420px; height: 420px; right: -120px; top: -140px; background: radial-gradient(circle, rgba(255,255,255,.18) 0%, rgba(255,255,255,0) 70%); }
.deco-2 { width: 560px; height: 560px; left: -180px; bottom: -260px; background: radial-gradient(circle, rgba(255,255,255,.14) 0%, rgba(255,255,255,0) 70%); }
.brand-inner { position: relative; z-index: 1; padding: 11vh 8% 0 9%; max-width: 560px; }
.brand-logo { width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-size: 25px; font-weight: 700; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); margin-bottom: 26px; }
.brand-title { font-size: 30px; font-weight: 700; letter-spacing: 2px; margin: 0 0 14px; }
.brand-sub { font-size: 15px; opacity: .85; margin: 0 0 34px; letter-spacing: 1px; }
.brand-user { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); border-radius: 12px; padding: 16px 18px; max-width: 340px; }
.brand-user .bu-label { font-size: 12px; opacity: .75; margin-bottom: 4px; }
.brand-user .bu-name { font-size: 18px; font-weight: 600; letter-spacing: .5px; }
.brand-foot { position: absolute; left: 9%; bottom: 28px; font-size: 12px; opacity: .7; }
.brand-foot a { color: #fff; opacity: .85; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.35); }

/* ── 右侧内容区 ──────────────── */
.panel-side { flex: 1; background: #faf8ff; padding: 48px 24px; }
.panel-box { width: min(560px, 100%); margin: 0 auto; }
.welcome { font-size: 14px; color: #8a81a3; margin-bottom: 8px; }
.sys-name { font-size: 26px; font-weight: 700; color: #2d2640; margin: 0 0 28px; letter-spacing: 1px; }

.card-block { background: #fff; border: 1px solid #eee8fa; border-radius: 14px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(109, 75, 184, .05); }
.card-title { font-size: 14px; font-weight: 600; color: #2d2640; margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }
.card-title i { color: #6d4bb8; }
.form-label { font-size: 13px; color: #5c5470; }
.form-control { border-radius: 8px; padding: 9px 12px; border-color: #e3ddf0; }
.form-control:focus { border-color: #6d4bb8; box-shadow: 0 0 0 3px rgba(109, 75, 184, .12); }
.btn-brand { background: #6d4bb8; border-color: #6d4bb8; color: #fff; border-radius: 8px; }
.btn-brand:hover { background: #5c3da3; border-color: #5c3da3; color: #fff; }
.field-hint { font-size: 12px; color: #b3aac9; margin-top: 5px; }
.code-row { display: flex; gap: 10px; }
.code-row .form-control { flex: 1; }
.code-row .btn { flex: 0 0 auto; white-space: nowrap; }
.panel-footer { margin-top: 30px; padding-top: 14px; border-top: 1px solid #eee8fa; text-align: center; font-size: 12px; color: #9a91b0; }
.panel-footer a { color: #8a7ab8; text-decoration: none; }

@media (max-width: 900px) {
	.brand-side { display: none; }
	.panel-side { padding: 40px 20px; }
}
</style>
</head>
<body>
<div class="panel-page">

	<!-- 左侧品牌区 -->
	<div class="brand-side">
		<div class="brand-deco deco-1"></div>
		<div class="brand-deco deco-2"></div>
		<div class="brand-inner">
			<div class="brand-logo">玟</div>
			<h1 class="brand-title">账号设置</h1>
			<p class="brand-sub">修改昵称 · 密码 · 邮箱</p>
			<div class="brand-user">
				<div class="bu-label">当前账号</div>
				<div class="bu-name"><?php echo wy_h($user['username']); ?></div>
			</div>
		</div>
		<div class="brand-foot">玟茵开源社区 · wenyinos.com　<a href="https://wenyinos.com">返回主站</a></div>
	</div>

	<!-- 右侧内容区 -->
	<div class="panel-side">
		<div class="panel-box">
			<div class="welcome">安全设置</div>
			<h2 class="sys-name">账号设置</h2>

			<?php if($saved !== '' && isset($saved_msg[$saved])): ?>
			<div class="alert alert-success py-2 small" style="border-radius:8px;"><i class="fas fa-check-circle"></i> <?php echo wy_h($saved_msg[$saved]); ?></div>
			<?php endif; ?>
			<?php if($error !== ''): ?>
			<div class="alert alert-danger py-2 small" style="border-radius:8px;"><?php echo wy_h($error); ?>（<?php echo $error_card === 'nickname' ? '昵称' : ($error_card === 'password' ? '密码' : '邮箱'); ?>设置）</div>
			<?php endif; ?>

			<!-- 昵称 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-user-pen"></i> 修改昵称</div>
				<form method="post" class="row g-2 align-items-end">
					<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
					<input type="hidden" name="action" value="nickname">
					<div class="col-8">
						<label class="form-label" for="realname">昵称 / 姓名</label>
						<input type="text" class="form-control" id="realname" name="realname" value="<?php echo wy_h($error_card === 'nickname' && isset($_POST['realname']) ? $_POST['realname'] : $user['realname']); ?>" maxlength="60" placeholder="最多 60 个字符">
					</div>
					<div class="col-4">
						<button type="submit" class="btn btn-brand w-100">保存昵称</button>
					</div>
				</form>
			</div>

			<!-- 基本资料 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-address-card"></i> 基本资料</div>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
					<input type="hidden" name="action" value="basic">
					<div class="row g-2 mb-3">
						<div class="col-md-6">
							<label class="form-label" for="mobile">手机号</label>
							<input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo wy_h($error_card === 'basic' && isset($_POST['mobile']) ? $_POST['mobile'] : $user['mobile']); ?>" placeholder="选填">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="qq">QQ</label>
							<input type="text" class="form-control" id="qq" name="qq" value="<?php echo wy_h($error_card === 'basic' && isset($_POST['qq']) ? $_POST['qq'] : $user['qq']); ?>" placeholder="选填">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="gender">性别</label>
							<?php $g = $error_card === 'basic' && isset($_POST['gender']) ? $_POST['gender'] : $user['gender']; ?>
							<select class="form-select" id="gender" name="gender" style="border-radius:8px;padding:9px 12px;border-color:#e3ddf0;">
								<option value="u" <?php echo $g === 'u' ? 'selected' : ''; ?>>保密</option>
								<option value="f" <?php echo $g === 'f' ? 'selected' : ''; ?>>女</option>
								<option value="m" <?php echo $g === 'm' ? 'selected' : ''; ?>>男</option>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label" for="birthday">生日</label>
							<input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo wy_h($error_card === 'basic' && isset($_POST['birthday']) ? $_POST['birthday'] : ($user['birthday'] && $user['birthday'] !== '0000-00-00' ? $user['birthday'] : '')); ?>">
						</div>
					</div>
					<button type="submit" class="btn btn-brand">保存基本资料</button>
					<div class="field-hint mt-2">手机号 / QQ / 生日将同步到支持这些字段的社区站点（禅道 / 论坛）。</div>
				</form>
			</div>

			<!-- 密码 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-key"></i> 修改密码</div>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
					<input type="hidden" name="action" value="password">
					<div class="mb-3">
						<label class="form-label" for="oldpassword">原密码</label>
						<input type="password" class="form-control" id="oldpassword" name="oldpassword" required autocomplete="current-password">
					</div>
					<div class="row g-2 mb-3">
						<div class="col-md-6">
							<label class="form-label" for="newpassword">新密码</label>
							<input type="password" class="form-control" id="newpassword" name="newpassword" placeholder="至少 6 位" required autocomplete="new-password">
						</div>
						<div class="col-md-6">
							<label class="form-label" for="newpassword2">确认新密码</label>
							<input type="password" class="form-control" id="newpassword2" name="newpassword2" required autocomplete="new-password">
						</div>
					</div>
					<button type="submit" class="btn btn-brand">保存新密码</button>
					<div class="field-hint mt-2">修改后，论坛与禅道将在下次登录时自动同步新密码。</div>
				</form>
			</div>

			<!-- 邮箱 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-envelope-circle-check"></i> 修改邮箱</div>
				<div class="field-hint mb-2">当前邮箱：<?php echo $user['email'] !== '' ? wy_h($user['email']) : '未设置'; ?>。修改需先向新邮箱发送验证码并认证通过才能保存。</div>
				<form method="post">
					<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
					<input type="hidden" name="action" value="email">
					<div class="mb-3">
						<label class="form-label" for="newemail">新邮箱</label>
						<input type="email" class="form-control" id="newemail" name="newemail" value="<?php echo wy_h($error_card === 'email' && isset($_POST['newemail']) ? $_POST['newemail'] : ''); ?>" placeholder="请输入新邮箱地址" required>
					</div>
					<div class="mb-3">
						<label class="form-label" for="email_code">邮箱验证码</label>
						<div class="code-row">
							<input type="text" class="form-control" id="email_code" name="email_code" maxlength="6" placeholder="6 位验证码" required>
							<button type="button" class="btn btn-outline-secondary" id="sendCodeBtn" style="border-radius:8px;">发送验证码</button>
						</div>
						<div class="field-hint" id="codeHint">点击「发送验证码」→ 查收新邮箱邮件 → 填入验证码后保存</div>
					</div>
					<button type="submit" class="btn btn-brand">认证并保存邮箱</button>
				</form>
			</div>

			<div class="panel-footer">
				<a href="index.php"><i class="fas fa-arrow-left"></i> 返回账号面板</a> · <a href="logout.php">退出登录</a>
			</div>
		</div>
	</div>

</div>

<script>
(function(){
	var btn = document.getElementById('sendCodeBtn');
	var hint = document.getElementById('codeHint');
	var emailInput = document.getElementById('newemail');
	btn.addEventListener('click', function(){
		var email = emailInput.value.trim();
		if(!email) { alert('请先填写新邮箱'); return; }
		btn.disabled = true;
		var form = new URLSearchParams();
		form.append('csrf', '<?php echo wy_h($_SESSION['csrf']); ?>');
		form.append('email', email);
		fetch('send_email_code.php', {method: 'POST', body: form, headers: {'Content-Type': 'application/x-www-form-urlencoded'}})
			.then(function(r){ return r.json(); })
			.then(function(r){
				hint.textContent = r.message;
				hint.style.color = r.code === 0 ? '#2e7d43' : '#c0392b';
				if(r.code === 0)
				{
					var s = 60;
					var t = setInterval(function(){
						if(--s <= 0) { clearInterval(t); btn.disabled = false; btn.textContent = '重新发送'; }
						else btn.textContent = s + ' 秒后重发';
					}, 1000);
				}
				else btn.disabled = false;
			})
			.catch(function(){ hint.textContent = '网络异常，请稍后重试'; hint.style.color = '#c0392b'; btn.disabled = false; });
	});
})();
</script>
</body>
</html>
