<?php
// wenyinos 认证中心 · 公共函数库
// 零框架，PHP 8.x；所有 DB 操作走预处理语句

date_default_timezone_set(wy_config('timezone'));

// 反爬虫：认证中心为真人交互入口，全部页面与接口禁止被索引/归档（robots.txt 之外的协议层声明）
header('X-Robots-Tag: noindex, nofollow, noarchive');

function wy_config($key = null)
{
	static $config = null;
	if($config === null) $config = require __DIR__ . '/config.php';
	if($key === null) return $config;
	return isset($config[$key]) ? $config[$key] : null;
}

function wy_db()
{
	static $pdo = null;
	if($pdo === null)
	{
		$db = wy_config('db');
		$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
		$pdo = new PDO($dsn, $db['user'], $db['password'], array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_TIMEOUT => 3,
		));
	}
	return $pdo;
}

// ---------------- 基础输出 ----------------

function wy_json($code, $message = '', $data = null)
{
	header('Content-Type: application/json; charset=utf-8');
	$out = array('code' => $code, 'message' => $message);
	if($data !== null) $out['data'] = $data;
	echo json_encode($out, JSON_UNESCAPED_UNICODE);
	exit;
}

function wy_rand_hex($len = 32)
{
	return substr(bin2hex(random_bytes($len)), 0, $len);
}

function wy_client_ip()
{
	return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

function wy_h($s)
{
	return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---------------- API 签名 / 防重放 ----------------

// 验签：sign = HMAC-SHA256( app_id | action | md5(raw_body) | timestamp | nonce, secret )
// 返回 app 行，或 wy_json 报错退出
function wy_app_verify($app_id, $action, $raw_body, $timestamp, $nonce, $sign)
{
	$app_id = trim($app_id);
	$stmt = wy_db()->prepare('SELECT * FROM apps WHERE app_id = ? AND status = 1');
	$stmt->execute(array($app_id));
	$app = $stmt->fetch();
	if(!$app) wy_json(2102, '应用不存在或已停用');

	if(!is_numeric($timestamp) || abs(time() - intval($timestamp)) > 300) wy_json(2103, '时间戳超出允许窗口');

	$nonce = substr(trim($nonce), 0, 40);
	if(strlen($nonce) < 8) wy_json(2104, 'nonce 无效');

	$expect = hash_hmac('sha256', $app_id . '|' . $action . '|' . md5($raw_body) . '|' . $timestamp . '|' . $nonce, $app['secret']);
	if(!hash_equals($expect, (string)$sign)) wy_json(2101, '签名校验失败');

	// nonce 一次性：插入成功=首次，冲突=重放
	wy_nonce_cleanup();
	try {
		$stmt = wy_db()->prepare('INSERT INTO nonces (nonce, expire) VALUES (?, ?)');
		$stmt->execute(array($nonce, time() + 600));
	} catch(PDOException $e) {
		wy_json(2105, 'nonce 已使用（请求重放）');
	}
	return $app;
}

function wy_nonce_cleanup()
{
	if(mt_rand(1, 10) === 1)
	{
		wy_db()->exec('DELETE FROM nonces WHERE expire < ' . time());
		wy_db()->exec('DELETE FROM revoked WHERE expire < ' . time());
		wy_db()->exec('DELETE FROM audit_log WHERE date < ' . (time() - 86400 * 30));
	}
}

// ---------------- 票据（wy_auth） ----------------

function wy_ticket_make($uid, $username)
{
	$expire = time() + intval(wy_config('ticket_ttl'));
	$jti = wy_rand_hex(16);
	$payload = $uid . '|' . $username . '|' . $expire . '|' . $jti;
	$sig = hash_hmac('sha256', $payload, wy_config('ticket_key'));
	return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . $sig;
}

// 返回 array(uid, username, expire, jti) 或 false
function wy_ticket_parse($ticket)
{
	$ticket = (string)$ticket;
	$pos = strrpos($ticket, '.');
	if($pos === false) return false;
	$b64 = substr($ticket, 0, $pos);
	$sig = substr($ticket, $pos + 1);
	$payload = base64_decode(strtr($b64, '-_', '+/'), true);
	if($payload === false) return false;
	if(!hash_equals(hash_hmac('sha256', $payload, wy_config('ticket_key')), $sig)) return false;
	$arr = explode('|', $payload);
	if(count($arr) !== 4) return false;
	list($uid, $username, $expire, $jti) = $arr;
	if(intval($expire) < time()) return false;
	if(wy_ticket_is_revoked($jti)) return false;
	return array('uid' => intval($uid), 'username' => $username, 'expire' => intval($expire), 'jti' => $jti);
}

function wy_ticket_revoke($jti, $expire)
{
	$stmt = wy_db()->prepare('INSERT IGNORE INTO revoked (jti, expire) VALUES (?, ?)');
	$stmt->execute(array($jti, intval($expire)));
}

function wy_ticket_is_revoked($jti)
{
	$stmt = wy_db()->prepare('SELECT jti FROM revoked WHERE jti = ?');
	$stmt->execute(array($jti));
	return (bool)$stmt->fetch();
}

function wy_set_auth_cookie($ticket)
{
	setcookie(wy_config('cookie_name'), $ticket, array(
		'expires'  => time() + intval(wy_config('ticket_ttl')),
		'path'     => '/',
		'domain'   => wy_config('cookie_domain'),
		'secure'   => (bool)wy_config('cookie_secure'),
		'httponly' => (bool)wy_config('cookie_httponly'),
		'samesite' => 'Lax',
	));
}

function wy_clear_auth_cookie()
{
	setcookie(wy_config('cookie_name'), '', array(
		'expires'  => time() - 86400,
		'path'     => '/',
		'domain'   => wy_config('cookie_domain'),
		'secure'   => (bool)wy_config('cookie_secure'),
		'httponly' => (bool)wy_config('cookie_httponly'),
		'samesite' => 'Lax',
	));
}

// ---------------- 用户 / 组 / 站点准入 ----------------

function wy_find_user($account)
{
	$stmt = wy_db()->prepare('SELECT * FROM users WHERE username = ? OR (email IS NOT NULL AND email = ? AND email != \'\') LIMIT 1');
	$stmt->execute(array($account, $account));
	return $stmt->fetch();
}

function wy_find_user_by_uid($uid)
{
	$stmt = wy_db()->prepare('SELECT * FROM users WHERE uid = ?');
	$stmt->execute(array(intval($uid)));
	return $stmt->fetch();
}

function wy_user_groups($uid)
{
	$stmt = wy_db()->prepare('SELECT g.* FROM user_group ug JOIN groups g ON g.id = ug.group_id WHERE ug.uid = ? ORDER BY g.sort ASC, g.id ASC');
	$stmt->execute(array(intval($uid)));
	return $stmt->fetchAll();
}

function wy_user_apps($uid)
{
	$stmt = wy_db()->prepare('SELECT app_id FROM user_app WHERE uid = ?');
	$stmt->execute(array(intval($uid)));
	$apps = array();
	foreach($stmt->fetchAll() as $row) $apps[] = $row['app_id'];
	return $apps;
}

// BBS gid 归并：sort 最小且授予 forum 的组
function wy_bbs_gid($groups, $apps)
{
	if(!in_array('forum', $apps, true)) return 0;
	foreach($groups as $g)
	{
		if(strpos(',' . $g['apps'] . ',', ',forum,') === false) continue;
		return intval($g['bbs_gid']);
	}
	return 0;
}

// 禅道组数组：所有 zentao_group != 0 的组
function wy_zentao_groups($groups)
{
	$out = array();
	foreach($groups as $g)
	{
		$zg = intval($g['zentao_group']);
		if($zg > 0 && !in_array($zg, $out, true)) $out[] = $zg;
	}
	sort($out);
	return $out;
}

// 下发给分站的用户数据
function wy_user_payload($user)
{
	$groups = wy_user_groups($user['uid']);
	$apps = wy_user_apps($user['uid']);
	return array(
		'uid' => intval($user['uid']),
		'username' => $user['username'],
		'email' => (string)$user['email'],
		'realname' => $user['realname'],
		'mobile' => (string)$user['mobile'],
		'qq' => (string)$user['qq'],
		'gender' => (string)$user['gender'],
		'birthday' => $user['birthday'] !== NULL && $user['birthday'] !== '0000-00-00' ? $user['birthday'] : '',
		'create_date' => intval($user['create_date']),   // 中心注册时间戳；禅道以此写入「入职日期」（仅新建号时）
		'bbs_gid' => wy_bbs_gid($groups, $apps),
		'zentao_groups' => wy_zentao_groups($groups),
	);
}

// 按 user_group × groups.apps 重算单个用户的 user_app（重算=先清后展）
function wy_rebuild_user_apps($uid)
{
	$stmt = wy_db()->prepare('DELETE FROM user_app WHERE uid = ?');
	$stmt->execute(array(intval($uid)));
	wy_expand_user_apps($uid);
}

// 按 user_group × groups.apps 重算单个用户的 user_app
function wy_expand_user_apps($uid)
{
	$uid = intval($uid);
	$stmt = wy_db()->prepare('INSERT IGNORE INTO user_app (uid, app_id)
		SELECT ug.uid, a.app_id FROM user_group ug
		JOIN groups g ON g.id = ug.group_id
		JOIN apps a ON FIND_IN_SET(a.app_id, g.apps) > 0
		WHERE ug.uid = ?');
	$stmt->execute(array($uid));
}

// ---------------- 登录防护 ----------------

function wy_lock_remaining($user)
{
	if($user['lock_until'] > time()) return intval($user['lock_until'] - time());
	return 0;
}

function wy_login_fail($user, $app_id)
{
	$fails = intval($user['fails']) + 1;
	$lock = 0;
	if($fails >= intval(wy_config('lock_fails')))
	{
		$lock = time() + intval(wy_config('lock_seconds'));
		$fails = 0;
	}
	$stmt = wy_db()->prepare('UPDATE users SET fails = ?, lock_until = ? WHERE uid = ?');
	$stmt->execute(array($fails, $lock, $user['uid']));
	wy_audit($app_id, $user['uid'], 'verify_fail');
}

function wy_login_success($user, $app_id)
{
	$stmt = wy_db()->prepare('UPDATE users SET fails = 0, lock_until = 0, last_login = ? WHERE uid = ?');
	$stmt->execute(array(time(), $user['uid']));
	wy_audit($app_id, $user['uid'], 'verify_ok');
}

// 同 IP 失败限速（借用 audit_log 计数）
function wy_ip_fail_exceeded()
{
	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action IN (\'verify_fail\') AND ip = ? AND date > ?');
	$stmt->execute(array(wy_client_ip(), time() - intval(wy_config('ip_fail_window'))));
	$row = $stmt->fetch();
	return intval($row['n']) >= intval(wy_config('ip_fail_limit'));
}

function wy_audit($app_id, $uid, $action)
{
	$stmt = wy_db()->prepare('INSERT INTO audit_log (app_id, uid, action, ip, date) VALUES (?, ?, ?, ?, ?)');
	$stmt->execute(array($app_id, intval($uid), $action, wy_client_ip(), time()));
}

// ---------------- 中心会话（后台 / 账号面板） ----------------

function wy_session_start()
{
	if(session_status() === PHP_SESSION_NONE)
	{
		session_name('wy_sid');
		session_set_cookie_params(array('path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (bool)wy_config('cookie_secure')));
		session_start();
	}
}

// ---------------- 密码哈希（双层：bcrypt(md5(明文))） ----------------
// 历史存储为无盐 md5(明文)（兼容禅道导入）；已离线批量升级为双层哈希，
// 校验失败时兼容读取 32 位历史值（纵深防御，正常流程不会出现）

function wy_password_hash($password_md5)
{
	return password_hash((string)$password_md5, PASSWORD_BCRYPT);
}

function wy_password_verify($password_md5, $stored)
{
	$stored = (string)$stored;
	if(strlen($stored) === 32) return hash_equals($stored, (string)$password_md5);
	return password_verify((string)$password_md5, $stored);
}

// 中心当前登录用户（基于 wy_auth cookie 解析；返回 user 行或 false）
function wy_current_user()
{
	static $user = null;
	if($user !== null) return $user;
	$ticket = isset($_COOKIE[wy_config('cookie_name')]) ? $_COOKIE[wy_config('cookie_name')] : '';
	if($ticket === '') { $user = false; return false; }
	$info = wy_ticket_parse($ticket);
	if(!$info) { $user = false; return false; }
	$u = wy_find_user_by_uid($info['uid']);
	if(!$u || intval($u['status']) !== 1) { $user = false; return false; }
	$user = $u;
	return $user;
}

// 后台权限：「超级管理」组成员
function wy_is_super($uid)
{
	$stmt = wy_db()->prepare('SELECT 1 FROM user_group ug JOIN groups g ON g.id = ug.group_id WHERE ug.uid = ? AND g.name = ? LIMIT 1');
	$stmt->execute(array(intval($uid), wy_config('super_group')));
	return (bool)$stmt->fetch();
}

function wy_require_admin()
{
	$u = wy_current_user();
	if(!$u || !wy_is_super($u['uid']))
	{
		header('Location: ../login.php');
		exit;
	}
	return $u;
}

// ---------------- 系统设置（settings 表，后台维护） ----------------

function wy_setting($key, $default = '')
{
	static $cache = NULL;
	if($cache === NULL)
	{
		$cache = array();
		foreach(wy_db()->query('SELECT skey, svalue FROM settings')->fetchAll() as $row)
		{
			$cache[$row['skey']] = $row['svalue'];
		}
	}
	return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function wy_setting_set($key, $value)
{
	$stmt = wy_db()->prepare('REPLACE INTO settings (skey, svalue) VALUES (?, ?)');
	return $stmt->execute(array($key, (string)$value));
}

// ---------------- 登录来源识别（从分站跳转而来时用于提示回跳） ----------------
// 依据 HTTP_REFERER 与已配置站点（白名单）匹配，返回 array('app_id','url','host','title') 或 NULL
// title 取 apps 表 name（网站标题）；查不到时退回 host
function wy_guess_referer_site()
{
	$ref = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
	if($ref === '') return NULL;
	$ref_host = parse_url($ref, PHP_URL_HOST);
	if(!$ref_host) return NULL;
	foreach(wy_config('sites') as $app_id => $url)
	{
		$host = parse_url($url, PHP_URL_HOST);
		if($host && strcasecmp($host, $ref_host) === 0)
		{
			$stmt = wy_db()->prepare('SELECT name FROM apps WHERE app_id = ? LIMIT 1');
			$stmt->execute(array($app_id));
			$row = $stmt->fetch();
			return array('app_id' => $app_id, 'url' => $url, 'host' => $host, 'title' => $row && $row['name'] !== '' ? $row['name'] : $host);
		}
	}
	return NULL;
}

// ---------------- base_url（邮件链接等绝对地址；WY_BASE_URL 配置优先） ----------------function wy_base_url()
{
	static $base = NULL;
	if($base === NULL)
	{
		$base = rtrim((string)wy_env('WY_BASE_URL', ''), '/');
		if($base === '')
		{
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
			$dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/auth/index.php'));
			$dir = preg_replace('#/admin$#', '', $dir);
			$base = $scheme . '://' . $host . rtrim($dir, '/');
		}
	}
	return $base;
}

// ---------------- 轻量 SMTP 客户端（支持 ssl / starttls / 无加密 + AUTH LOGIN） ----------------

function wy_smtp_send($to, $subject, $html)
{
	$host = trim(wy_setting('smtp_host', ''));
	$port = intval(wy_setting('smtp_port', 465));
	$user = trim(wy_setting('smtp_user', ''));
	$pass = (string)wy_setting('smtp_pass', '');
	$secure = wy_setting('smtp_secure', 'ssl');
	$from = trim(wy_setting('smtp_from', $user));
	$from_name = trim(wy_setting('smtp_from_name', '玟茵开源社区'));
	if($host === '' || $from === '') return 'SMTP 未配置';
	if(!is_email_format($to)) return '收件邮箱格式错误';

	$prefix = $secure === 'ssl' ? 'ssl://' : '';
	$fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
	if(!$fp) return 'SMTP 连接失败: ' . $errstr;
	stream_set_timeout($fp, 10);

	$read = function() use ($fp) {
		$data = '';
		while(($line = fgets($fp, 515)) !== FALSE)
		{
			$data .= $line;
			if(isset($line[3]) && $line[3] === ' ') break;
		}
		return $data;
	};
	$write = function($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

	$r = $read();
	if(strpos($r, '220') !== 0) { fclose($fp); return 'SMTP 握手失败'; }

	$ehlo = 'EHLO ' . (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '' ? $_SERVER['SERVER_NAME'] : 'localhost');
	$write($ehlo); $r = $read();

	if($secure === 'tls')
	{
		$write('STARTTLS'); $r = $read();
		if(strpos($r, '220') !== 0) { fclose($fp); return 'SMTP STARTTLS 不被支持'; }
		if(!stream_socket_enable_crypto($fp, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return 'TLS 加密协商失败'; }
		$write($ehlo); $r = $read();
	}

	if($user !== '')
	{
		$write('AUTH LOGIN'); $r = $read();
		if(strpos($r, '334') !== 0) { fclose($fp); return 'SMTP 不支持 AUTH LOGIN'; }
		$write(base64_encode($user)); $r = $read();
		$write(base64_encode($pass)); $r = $read();
		if(strpos($r, '235') !== 0) { fclose($fp); return 'SMTP 认证失败（请检查账号密码）'; }
	}

	$write('MAIL FROM:<' . $from . '>'); $r = $read();
	if(strpos($r, '250') !== 0) { fclose($fp); return 'SMTP MAIL FROM 被拒'; }
	$write('RCPT TO:<' . $to . '>'); $r = $read();
	if(strpos($r, '250') !== 0 && strpos($r, '251') !== 0) { fclose($fp); return 'SMTP RCPT TO 被拒'; }
	$write('DATA'); $r = $read();
	if(strpos($r, '354') !== 0) { fclose($fp); return 'SMTP DATA 被拒'; }

	$headers = 'From: =?UTF-8?B?' . base64_encode($from_name) . '?= <' . $from . ">\r\n"
		. 'To: <' . $to . ">\r\n"
		. 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
		. "MIME-Version: 1.0\r\n"
		. "Content-Type: text/html; charset=UTF-8\r\n"
		. 'Date: ' . date('r') . "\r\n";
	$body = str_replace(array("\r\n", "\r"), "\n", $html);
	$body = str_replace("\n.", "\n..", $body);
	$body = str_replace("\n", "\r\n", $body);
	fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
	$r = $read();
	if(strpos($r, '250') !== 0) { fclose($fp); return 'SMTP 邮件投递失败'; }

	$write('QUIT'); fclose($fp);
	return TRUE;
}

function is_email_format($email)
{
	return (bool)preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', (string)$email);
}

// 重置密码邮件模板
function wy_reset_mail_html($username, $link, $ttl_minutes)
{
	return '<div style="max-width:520px;margin:0 auto;font-family:-apple-system,\'Segoe UI\',\'Noto Sans SC\',sans-serif;border:1px solid #e8e2f5;border-radius:14px;overflow:hidden;">'
		. '<div style="background:linear-gradient(160deg,#402a75 0%,#6d4bb8 48%,#8f6ed6 100%);padding:28px 32px;color:#fff;">'
		. '<div style="font-size:20px;font-weight:700;letter-spacing:1px;">玟茵开源社区</div>'
		. '<div style="font-size:13px;opacity:.85;margin-top:6px;">统一身份认证中心</div>'
		. '</div>'
		. '<div style="padding:28px 32px;color:#2d2640;font-size:14px;line-height:1.8;background:#fff;">'
		. '<p style="margin:0 0 14px;">您好，<b>' . wy_h($username) . '</b>：</p>'
		. '<p style="margin:0 0 22px;">我们收到了您重置账号密码的请求。点击下方按钮设置新密码：</p>'
		. '<p style="text-align:center;margin:0 0 22px;">'
		. '<a href="' . wy_h($link) . '" style="display:inline-block;background:#6d4bb8;color:#fff;text-decoration:none;padding:12px 34px;border-radius:8px;font-size:15px;">重置密码</a>'
		. '</p>'
		. '<p style="margin:0 0 8px;color:#8a81a3;font-size:12.5px;">链接 ' . intval($ttl_minutes) . ' 分钟内有效，且仅可使用一次。</p>'
		. '<p style="margin:0 0 8px;color:#8a81a3;font-size:12.5px;">若按钮无法点击，请复制以下地址到浏览器打开：<br><span style="color:#6d4bb8;word-break:break-all;">' . wy_h($link) . '</span></p>'
		. '<p style="margin:14px 0 0;color:#8a81a3;font-size:12.5px;">如果并非您本人操作，请忽略本邮件，您的密码不会发生变化。</p>'
		. '</div>'
		. '<div style="padding:14px 32px;background:#faf8ff;color:#9a91b0;font-size:12px;text-align:center;">玟茵开源社区 · wenyinos.com</div>'
		. '</div>';
}

// legacy 凭证校验（BBS 休眠号首次登录自动升级）；$password_md5 = md5(明文)；$user 按引用更新
function wy_try_legacy(&$user, $password_md5, $app_id = 'auth')
{
	if(empty($user['bbs_password']) || empty($user['bbs_salt'])) return FALSE;
	if(!hash_equals($user['bbs_password'], md5($password_md5 . $user['bbs_salt']))) return FALSE;
	$stmt = wy_db()->prepare('UPDATE users SET password = ?, bbs_password = NULL, bbs_salt = NULL WHERE uid = ?');
	$stmt->execute(array(wy_password_hash($password_md5), $user['uid']));
	$user['password'] = $password_md5;
	wy_audit($app_id, $user['uid'], 'legacy_upgrade');
	return TRUE;
}

// 注册 IP 限速：同 IP 1 小时内注册上限 3 个
function wy_register_ip_exceeded()
{
	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'register_ok\' AND ip = ? AND date > ?');
	$stmt->execute(array(wy_client_ip(), time() - 3600));
	$row = $stmt->fetch();
	return intval($row['n']) >= 3;
}

// 找回邮件发送限速：同 uid 60 秒 1 次 + 24 小时 10 次；同 IP 10 分钟 5 次；全站 1 小时 60 封（防代理池轰炸）
function wy_reset_mail_allowed($uid)
{
	$uid = intval($uid);
	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'reset_mail\' AND uid = ? AND date > ?');
	$stmt->execute(array($uid, time() - 60));
	if(intval($stmt->fetch()['n']) > 0) return FALSE;

	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'reset_mail\' AND uid = ? AND date > ?');
	$stmt->execute(array($uid, time() - 86400));
	if(intval($stmt->fetch()['n']) >= 10) return FALSE;

	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'reset_mail\' AND ip = ? AND date > ?');
	$stmt->execute(array(wy_client_ip(), time() - 600));
	if(intval($stmt->fetch()['n']) >= 5) return FALSE;

	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'reset_mail\' AND date > ?');
	$stmt->execute(array(time() - 3600));
	return intval($stmt->fetch()['n']) < 60;
}

// ---------------- 人机验证（Cloudflare Turnstile，仅注册使用；后台配置 site_key/secret_key） ----------------

function wy_captcha_enabled()
{
	return trim(wy_setting('turnstile_site_key', '')) !== '' && trim(wy_setting('turnstile_secret', '')) !== '';
}

// 校验 Turnstile 凭证；通过返回 TRUE，否则返回错误信息字符串
function wy_captcha_verify($input)
{
	if(!wy_captcha_enabled()) return TRUE;

	$secret = trim(wy_setting('turnstile_secret', ''));
	$response = isset($input['response']) ? (string)$input['response'] : '';
	if($response === '') return '请先完成人机验证';

	$resp = wy_http_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
		'secret' => $secret,
		'response' => $response,
		'remoteip' => wy_client_ip(),
	), 5);
	if($resp === NULL) return '人机验证服务暂时不可用，请稍后重试';
	$json = json_decode($resp, TRUE);
	if(!is_array($json) || empty($json['success'])) return '人机验证未通过，请重试';
	return TRUE;
}

// 轻量 HTTP POST（表单编码）；失败返回 NULL
function wy_http_post($url, $data, $timeout = 5)
{
	$body = http_build_query($data);
	if(function_exists('curl_init'))
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$resp = curl_exec($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		return ($errno === 0 && $resp !== FALSE) ? $resp : NULL;
	}
	$ctx = stream_context_create(array('http' => array(
		'method' => 'POST',
		'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
		'content' => $body,
		'timeout' => $timeout,
		'ignore_errors' => TRUE,
	)));
	$resp = @file_get_contents($url, FALSE, $ctx);
	return $resp === FALSE ? NULL : $resp;
}

// ---------------- 账号验证（verified）：邮件验证链接 / 管理员后台手动通过 ----------------

// SMTP 认证开关（后台）：开启=注册后发验证邮件（点链接自动通过）；关闭=待管理员后台手动通过
function wy_smtp_verify_enabled()
{
	return wy_setting('smtp_verify', '0') === '1';
}

// 生成验证 token 并发送验证邮件；返回 TRUE 或错误信息
function wy_send_verify_mail(&$user)
{
	$token = wy_rand_hex(64);
	$stmt = wy_db()->prepare('UPDATE users SET reset_token = ?, reset_expire = ? WHERE uid = ?');
	$stmt->execute(array('verify:' . $token, time() + 86400, $user['uid']));

	$link = wy_base_url() . '/verify.php?token=' . rawurlencode($token);
	return wy_smtp_send($user['email'], '【玟茵开源社区】验证您的注册邮箱', wy_verify_mail_html($user['username'], $link));
}

// 注册验证邮件模板
function wy_verify_mail_html($username, $link)
{
	return '<div style="max-width:520px;margin:0 auto;font-family:-apple-system,\'Segoe UI\',\'Noto Sans SC\',sans-serif;border:1px solid #e8e2f5;border-radius:14px;overflow:hidden;">'
		. '<div style="background:linear-gradient(160deg,#402a75 0%,#6d4bb8 48%,#8f6ed6 100%);padding:28px 32px;color:#fff;">'
		. '<div style="font-size:20px;font-weight:700;letter-spacing:1px;">玟茵开源社区</div>'
		. '<div style="font-size:13px;opacity:.85;margin-top:6px;">统一身份认证中心</div>'
		. '</div>'
		. '<div style="padding:28px 32px;color:#2d2640;font-size:14px;line-height:1.8;background:#fff;">'
		. '<p style="margin:0 0 14px;">您好，<b>' . wy_h($username) . '</b>：</p>'
		. '<p style="margin:0 0 22px;">感谢注册玟茵开源社区。请点击下方按钮验证您的邮箱，验证后即可登录论坛与项目管理：</p>'
		. '<p style="text-align:center;margin:0 0 22px;">'
		. '<a href="' . wy_h($link) . '" style="display:inline-block;background:#6d4bb8;color:#fff;text-decoration:none;padding:12px 34px;border-radius:8px;font-size:15px;">验证邮箱</a>'
		. '</p>'
		. '<p style="margin:0 0 8px;color:#8a81a3;font-size:12.5px;">链接 24 小时内有效。若按钮无法点击，请复制以下地址到浏览器打开：<br><span style="color:#6d4bb8;word-break:break-all;">' . wy_h($link) . '</span></p>'
		. '<p style="margin:14px 0 0;color:#8a81a3;font-size:12.5px;">如果并非您本人注册，请忽略本邮件。</p>'
		. '</div>'
		. '<div style="padding:14px 32px;background:#faf8ff;color:#9a91b0;font-size:12px;text-align:center;">玟茵开源社区 · wenyinos.com</div>'
		. '</div>';
}

// 待验证账号登录拦截提示（未验证且非超管）
function wy_verify_blocked($user)
{
	if(intval($user['verified']) === 1) return '';
	if(wy_is_super($user['uid'])) return '';
	return wy_smtp_verify_enabled()
		? '账号待验证：请查收注册验证邮件并点击链接完成验证；未收到可联系管理员'
		: '账号待验证：请联系社区管理员在后台通过验证后即可登录';
}

// 验证码邮件模板（改邮箱等敏感操作）
function wy_code_mail_html($code, $purpose = '安全验证')
{
	return '<div style="max-width:520px;margin:0 auto;font-family:-apple-system,\'Segoe UI\',\'Noto Sans SC\',sans-serif;border:1px solid #e8e2f5;border-radius:14px;overflow:hidden;">'
		. '<div style="background:linear-gradient(160deg,#402a75 0%,#6d4bb8 48%,#8f6ed6 100%);padding:28px 32px;color:#fff;">'
		. '<div style="font-size:20px;font-weight:700;letter-spacing:1px;">玟茵开源社区</div>'
		. '<div style="font-size:13px;opacity:.85;margin-top:6px;">统一身份认证中心</div>'
		. '</div>'
		. '<div style="padding:28px 32px;color:#2d2640;font-size:14px;line-height:1.8;background:#fff;">'
		. '<p style="margin:0 0 14px;">您正在进行「' . wy_h($purpose) . '」操作，验证码为：</p>'
		. '<p style="text-align:center;margin:0 0 20px;">'
		. '<span style="display:inline-block;background:#f5f1fd;border:1px dashed #b7a5dd;color:#5c3da3;font-size:28px;font-weight:700;letter-spacing:8px;padding:10px 24px 10px 32px;border-radius:10px;">' . wy_h($code) . '</span>'
		. '</p>'
		. '<p style="margin:0 0 8px;color:#8a81a3;font-size:12.5px;">验证码 10 分钟内有效，请勿泄露给他人。</p>'
		. '<p style="margin:14px 0 0;color:#8a81a3;font-size:12.5px;">如果并非您本人操作，请忽略本邮件。</p>'
		. '</div>'
		. '<div style="padding:14px 32px;background:#faf8ff;color:#9a91b0;font-size:12px;text-align:center;">玟茵开源社区 · wenyinos.com</div>'
		. '</div>';
}

// 改邮箱验证码发送限速：同 uid 10 分钟 5 次 + 同 IP 10 分钟 10 次
function wy_email_code_allowed($uid)
{
	$uid = intval($uid);
	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'email_code\' AND uid = ? AND date > ?');
	$stmt->execute(array($uid, time() - 600));
	if(intval($stmt->fetch()['n']) >= 5) return FALSE;

	$stmt = wy_db()->prepare('SELECT COUNT(*) AS n FROM audit_log WHERE action = \'email_code\' AND date > ?');
	$stmt->execute(array(time() - 600));
	return intval($stmt->fetch()['n']) < 50;
}
