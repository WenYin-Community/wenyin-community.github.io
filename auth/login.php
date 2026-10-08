<?php
// wenyinos 认证中心 · 登录页
require __DIR__ . '/core.php';
require __DIR__ . '/page.php';
wy_session_start();

// 已登录访问登录页 → 直接进入账号面板（受限用户从分站定向回来时看到面板提示）
if(wy_current_user())
{
	header('Location: index.php');
	exit;
}

$error = '';
$account = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$account = isset($_POST['account']) ? trim((string)$_POST['account']) : '';
	$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

	if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		$error = '会话已过期，请重试';
	}
	elseif($account === '' || $password === '')
	{
		$error = '请输入账号与密码';
	}
	elseif(wy_ip_fail_exceeded())
	{
		$error = '尝试过于频繁，请稍后再试';
	}
	else
	{
		$user = wy_find_user($account);
		if(!$user)
		{
			wy_audit('auth', 0, 'login_fail');
			$error = '账号或密码错误';
		}
		elseif(intval($user['status']) !== 1)
		{
			$error = '账号已禁用';
		}
		else
		{
			$remain = wy_lock_remaining($user);
			if($remain > 0)
			{
				$error = '账号已锁定，请 ' . ceil($remain / 60) . ' 分钟后再试';
			}
			elseif(!hash_equals($user['password'], md5($password)) && !wy_try_legacy($user, md5($password)))
			{
				wy_login_fail($user, 'auth');
				$error = '账号或密码错误';
			}
			elseif(wy_verify_blocked($user) !== '')
			{
				wy_audit('auth', $user['uid'], 'login_unverified');
				$error = wy_verify_blocked($user);
			}
			else
			{
				wy_login_success($user, 'auth');
				wy_set_auth_cookie(wy_ticket_make($user['uid'], $user['username']));
				header('Location: index.php');
				exit;
			}
		}
	}
}

if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);

wy_page_head('登录', '欢迎登录', '统一认证中心');
?>

<?php wy_error_box($error); ?>

<form method="post" action="login.php" autocomplete="off">
	<input type="hidden" name="csrf" value="<?php echo wy_h($_SESSION['csrf']); ?>">
	<div class="field-group">
		<label class="form-label" for="account">用户名或邮箱</label>
		<input type="text" class="form-control" id="account" name="account" value="<?php echo wy_h($account); ?>" placeholder="请输入用户名或邮箱" required autofocus>
	</div>
	<div class="field-group" style="margin-bottom:26px;">
		<label class="form-label" for="password">密码</label>
		<input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" required>
	</div>
	<button type="submit" class="btn submit">登 录</button>
</form>

<div class="link-row">
	<a href="forgot.php">忘记密码？</a>
	<a href="register.php">注册新账号 <i class="fas fa-arrow-right" style="font-size:11px;"></i></a>
</div>

<?php wy_page_foot(); ?>
