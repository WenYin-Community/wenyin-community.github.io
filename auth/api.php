<?php
// wenyinos 认证中心 · API 入口（仅 POST JSON）
require __DIR__ . '/core.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
	wy_json(2000, '仅支持 POST');
}

$raw_body = file_get_contents('php://input');
$body = json_decode($raw_body, true);
if(!is_array($body))
{
	wy_json(2001, '请求体不是合法 JSON');
}

// 凭证走 HTTP 头（body 只放 action+data，避免签名与 body 循环依赖）
$action = isset($body['action']) ? trim((string)$body['action']) : '';
$app_id = isset($_SERVER['HTTP_X_WY_APP']) ? trim((string)$_SERVER['HTTP_X_WY_APP']) : '';
$timestamp = isset($_SERVER['HTTP_X_WY_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_WY_TIMESTAMP'] : '';
$nonce = isset($_SERVER['HTTP_X_WY_NONCE']) ? (string)$_SERVER['HTTP_X_WY_NONCE'] : '';
$sign = isset($_SERVER['HTTP_X_WY_SIGN']) ? (string)$_SERVER['HTTP_X_WY_SIGN'] : '';
$data = isset($body['data']) && is_array($body['data']) ? $body['data'] : array();

if($action === '')
{
	wy_json(2002, '缺少 action');
}

// 验签（app_id 必须存在且启用；时间窗 + nonce 防重放）
$app = wy_app_verify($app_id, $action, $raw_body, $timestamp, $nonce, $sign);

// IP 维度失败限速（兑换类接口同样受限，防止被扫）
if(wy_ip_fail_exceeded())
{
	wy_json(1008, '请求过于频繁，请稍后再试');
}

switch($action)
{
	case 'verify':    wy_api_verify($app, $data);    break;
	case 'ticket':    wy_api_ticket($app, $data);    break;
	case 'password':  wy_api_password($app, $data);  break;
	case 'migrate':   wy_api_migrate($app, $data);   break;
	case 'revoke':    wy_api_revoke($app, $data);    break;
	default: wy_json(2003, '未知 action');
}

// ---------------- verify：账号 + 密码(md5) ----------------

function wy_api_verify($app, $data)
{
	$account = isset($data['account']) ? trim((string)$data['account']) : '';
	$password = isset($data['password']) ? strtolower(trim((string)$data['password'])) : '';

	if($account === '' || $password === '') wy_json(3001, '参数不完整');
	if(!preg_match('/^[a-f0-9]{32}$/', $password)) wy_json(3002, '密码参数须为 32 位 md5');

	$user = wy_find_user($account);
	if(!$user)
	{
		wy_audit($app['app_id'], 0, 'verify_fail');
		wy_json(1001, '用户不存在');
	}

	if(intval($user['status']) !== 1) wy_json(1003, '账号已禁用');

	$remain = wy_lock_remaining($user);
	if($remain > 0) wy_json(1005, '账号已锁定，请 ' . ceil($remain / 60) . ' 分钟后再试');

	if(!hash_equals($user['password'], $password) && !wy_try_legacy($user, $password, $app['app_id']))
	{
		// 老论坛凭证升级路径由 wy_try_legacy 处理（BBS 休眠号 md5(md5(明文).salt)）
		wy_login_fail($user, $app['app_id']);
		wy_json(1002, '密码错误');
	}

	// 未验证账号拦截（注册待邮件验证 / 管理员通过后放行；超管豁免）
	$blocked = wy_verify_blocked($user);
	if($blocked !== '')
	{
		wy_audit($app['app_id'], $user['uid'], 'verify_unverified');
		wy_json(1009, $blocked);
	}

	// 站点准入
	$apps = wy_user_apps($user['uid']);
	if(!in_array($app['app_id'], $apps, true))
	{
		wy_audit($app['app_id'], $user['uid'], 'verify_denied');
		wy_json(1004, '该账号未开通本站访问');
	}

	wy_login_success($user, $app['app_id']);
	wy_json(0, 'ok', wy_user_payload($user));
}

// ---------------- ticket：票据兑换（分站登录态继承） ----------------

function wy_api_ticket($app, $data)
{
	$ticket = isset($data['ticket']) ? (string)$data['ticket'] : '';
	$info = $ticket === '' ? false : wy_ticket_parse($ticket);
	if(!$info) wy_json(2001, '票据无效或已过期');

	$user = wy_find_user_by_uid($info['uid']);
	if(!$user) wy_json(1001, '用户不存在');
	if(intval($user['status']) !== 1) wy_json(1003, '账号已禁用');

	$apps = wy_user_apps($user['uid']);
	if(!in_array($app['app_id'], $apps, true))
	{
		wy_audit($app['app_id'], $user['uid'], 'ticket_denied');
		wy_json(1004, '该账号未开通本站访问');
	}

	wy_audit($app['app_id'], $user['uid'], 'ticket_ok');
	wy_json(0, 'ok', wy_user_payload($user));
}

// ---------------- password：分站改密/改资料反向同步 ----------------

function wy_api_password($app, $data)
{
	$account = isset($data['account']) ? trim((string)$data['account']) : '';
	$password = isset($data['password']) ? strtolower(trim((string)$data['password'])) : '';
	$email = isset($data['email']) ? trim((string)$data['email']) : '';
	$realname = isset($data['realname']) ? trim((string)$data['realname']) : '';

	if($account === '' || !preg_match('/^[a-f0-9]{32}$/', $password)) wy_json(3001, '参数不完整或密码格式错误');

	$user = wy_find_user($account);
	if(!$user) wy_json(1001, '用户不存在');

	// 密码更新
	$stmt = wy_db()->prepare('UPDATE users SET password = ? WHERE uid = ?');
	$stmt->execute(array($password, $user['uid']));
	wy_audit($app['app_id'], $user['uid'], 'password_sync');

	// 昵称可选更新
	if($realname !== '' && $realname !== $user['realname'])
	{
		$stmt = wy_db()->prepare('UPDATE users SET realname = ? WHERE uid = ?');
		$stmt->execute(array(mb_substr($realname, 0, 60), $user['uid']));
		wy_audit($app['app_id'], $user['uid'], 'realname_sync');
	}

	// 基本资料可选更新（isset 语义：提供即更新，允许清空）
	if(isset($data['mobile']) && (string)$data['mobile'] !== (string)$user['mobile'])
	{
		$stmt = wy_db()->prepare('UPDATE users SET mobile = ? WHERE uid = ?');
		$stmt->execute(array(substr(trim((string)$data['mobile']), 0, 20), $user['uid']));
	}
	if(isset($data['qq']) && (string)$data['qq'] !== (string)$user['qq'])
	{
		$stmt = wy_db()->prepare('UPDATE users SET qq = ? WHERE uid = ?');
		$stmt->execute(array(substr(trim((string)$data['qq']), 0, 20), $user['uid']));
	}
	if(isset($data['gender']) && in_array($data['gender'], array('u', 'f', 'm'), TRUE) && $data['gender'] !== $user['gender'])
	{
		$stmt = wy_db()->prepare('UPDATE users SET gender = ? WHERE uid = ?');
		$stmt->execute(array($data['gender'], $user['uid']));
	}
	if(isset($data['birthday']))
	{
		$birthday = trim((string)$data['birthday']);
		$birthday = preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) ? $birthday : NULL;
		$stmt = wy_db()->prepare('UPDATE users SET birthday = ? WHERE uid = ?');
		$stmt->execute(array($birthday, $user['uid']));
	}

	// 邮箱可选更新（冲突则跳过该字段：密码已更新，返回 1006）
	$email_conflict = 0;
	if($email !== '' && $email !== $user['email'])
	{
		$stmt = wy_db()->prepare('SELECT uid FROM users WHERE email = ? AND uid != ? LIMIT 1');
		$stmt->execute(array($email, $user['uid']));
		if($stmt->fetch())
		{
			$email_conflict = 1;
		}
		else
		{
			$stmt = wy_db()->prepare('UPDATE users SET email = ? WHERE uid = ?');
			$stmt->execute(array($email, $user['uid']));
			wy_audit($app['app_id'], $user['uid'], 'email_sync');
		}
	}

	if($email_conflict) wy_json(1006, '密码已更新；邮箱与中心其他账号冲突，未同步', array('email_conflict' => 1));
	wy_json(0, 'ok');
}

// ---------------- migrate：BBS 休眠号无感迁移 ----------------

function wy_api_migrate($app, $data)
{
	$account = isset($data['account']) ? trim((string)$data['account']) : '';
	$password = isset($data['password']) ? strtolower(trim((string)$data['password'])) : '';
	$email = isset($data['email']) ? trim((string)$data['email']) : '';
	$realname = isset($data['realname']) ? trim((string)$data['realname']) : '';

	if($account === '' || !preg_match('/^[a-f0-9]{32}$/', $password)) wy_json(3001, '参数不完整或密码格式错误');
	if(strlen($account) > 32) wy_json(3003, '用户名超长');

	// 已存在则不迁移（调用方改为走 verify）
	$exist = wy_find_user($account);
	if($exist) wy_json(1007, '账号已存在于中心，请走常规登录', array('uid' => intval($exist['uid'])));

	// 邮箱冲突：置空继续
	$email_conflict = 0;
	if($email !== '')
	{
		$stmt = wy_db()->prepare('SELECT uid FROM users WHERE email = ? LIMIT 1');
		$stmt->execute(array($email));
		if($stmt->fetch())
		{
			$email = '';
			$email_conflict = 1;
		}
	}

	$stmt = wy_db()->prepare('INSERT INTO users (username, password, email, realname, status, create_date) VALUES (?, ?, NULLIF(?, \'\'), ?, 1, ?)');
	$stmt->execute(array($account, $password, $email, $realname, time()));
	$uid = wy_db()->lastInsertId();

	// 归「注册用户」组（id=4）并展开准入（migrate 仅来自 BBS）
	$stmt = wy_db()->prepare('INSERT IGNORE INTO user_group (uid, group_id) SELECT ?, id FROM groups WHERE id = 4');
	$stmt->execute(array($uid));
	wy_expand_user_apps($uid);

	wy_audit($app['app_id'], $uid, 'user_migrate');
	wy_json(0, 'ok', array('uid' => intval($uid), 'email_conflict' => $email_conflict));
}

// ---------------- revoke：登出销毁票据 ----------------

function wy_api_revoke($app, $data)
{
	$ticket = isset($data['ticket']) ? (string)$data['ticket'] : '';
	if($ticket !== '')
	{
		// 过期票据解析会失败，此时按其签名结构无法取得 jti——允许失败静默（幂等）
		$pos = strrpos($ticket, '.');
		if($pos !== false)
		{
			$payload = base64_decode(strtr(substr($ticket, 0, $pos), '-_', '+/'), true);
			if($payload !== false)
			{
				$arr = explode('|', $payload);
				if(count($arr) === 4)
				{
					// 仅当签名有效才允许撤销（防伪造占用黑名单）
					if(hash_equals(hash_hmac('sha256', $payload, wy_config('ticket_key')), substr($ticket, $pos + 1)))
					{
						wy_ticket_revoke($arr[3], intval($arr[2]));
						wy_audit($app['app_id'], intval($arr[0]), 'ticket_revoke');
					}
				}
			}
		}
	}
	wy_json(0, 'ok');
}
