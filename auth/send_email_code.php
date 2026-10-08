<?php
// wenyinos 认证中心 · 发送改邮箱验证码（AJAX 端点，需登录 + CSRF）
require __DIR__ . '/core.php';
wy_session_start();

header('Content-Type: application/json; charset=utf-8');

$user = wy_current_user();
if(!$user)
{
	echo json_encode(array('code' => 1, 'message' => '请先登录'));
	exit;
}

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
	echo json_encode(array('code' => 1, 'message' => '请求方式错误'));
	exit;
}

if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
{
	echo json_encode(array('code' => 1, 'message' => '会话已过期，请刷新页面'));
	exit;
}

// 60 秒间隔
if(!empty($_SESSION['email_code_time']) && time() - intval($_SESSION['email_code_time']) < 60)
{
	echo json_encode(array('code' => 1, 'message' => '发送过于频繁，请稍后再试'));
	exit;
}

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
if(!is_email_format($email))
{
	echo json_encode(array('code' => 1, 'message' => '请输入正确的邮箱地址'));
	exit;
}
if($email === (string)$user['email'])
{
	echo json_encode(array('code' => 1, 'message' => '新邮箱与当前邮箱相同'));
	exit;
}
if(wy_find_user($email))
{
	echo json_encode(array('code' => 1, 'message' => '该邮箱已被其他账号使用'));
	exit;
}
if(!wy_email_code_allowed($user['uid']))
{
	echo json_encode(array('code' => 1, 'message' => '发送次数过多，请稍后再试'));
	exit;
}

$code = (string)random_int(100000, 999999);
$_SESSION['email_code'] = $code;
$_SESSION['email_code_email'] = $email;
$_SESSION['email_code_time'] = time();
$_SESSION['email_code_expire'] = time() + 600;

$result = wy_smtp_send($email, '【玟茵开源社区】修改邮箱验证码', wy_code_mail_html($code, '修改邮箱'));
if($result !== TRUE)
{
	unset($_SESSION['email_code'], $_SESSION['email_code_email'], $_SESSION['email_code_expire']);
	error_log('[auth] email code mail failed: ' . $result . ' (uid=' . $user['uid'] . ')');
	echo json_encode(array('code' => 1, 'message' => '邮件发送失败：' . $result));
	exit;
}

wy_audit('auth', $user['uid'], 'email_code');
echo json_encode(array('code' => 0, 'message' => '验证码已发送至 ' . $email . '，请查收（10 分钟内有效）'));
