<?php
// wenyinos 认证中心配置
// 敏感值（数据库/票据密钥/Cookie 域）从同目录 .env 读取；模板见 .env.example
// .env 不入库（已 gitignore），因此本目录代码可全量开源
// 未提供 .env 时的回退默认值适配「本地联调」

if(!function_exists('wy_env'))
{
	function wy_env($key, $default = NULL)
	{
		static $env = NULL;
		if($env === NULL)
		{
			$env = array();
			$file = __DIR__ . '/.env';
			if(is_file($file))
			{
				foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line)
				{
					$line = trim($line);
					if($line === '' || $line[0] === '#') continue;
					$pos = strpos($line, '=');
					if($pos === FALSE) continue;
					$k = trim(substr($line, 0, $pos));
					$v = trim(substr($line, $pos + 1));
					if(strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
					$env[$k] = $v;
				}
			}
		}
		return array_key_exists($key, $env) ? $env[$key] : $default;
	}
}

return array(
	'db' => array(
		'host' => wy_env('WY_DB_HOST', '127.0.0.1'),
		'port' => intval(wy_env('WY_DB_PORT', 3306)),
		'name' => wy_env('WY_DB_NAME', 'wenyinos_auth'),
		'user' => wy_env('WY_DB_USER', 'root'),
		'password' => wy_env('WY_DB_PASS', 'root'),
	),

	// 票据签名密钥（生产：64 位随机 hex，openssl rand -hex 32）
	'ticket_key' => wy_env('WY_TICKET_KEY', ''),
	'ticket_ttl'  => intval(wy_env('WY_TICKET_TTL', 7200)),

	// Cookie（本地：.wenyinos.test + http；生产：.wenyinos.com + secure true）
	'cookie_domain' => wy_env('WY_COOKIE_DOMAIN', '.wenyinos.test'),
	'cookie_name'   => 'wy_auth',
	'cookie_secure' => wy_env('WY_COOKIE_SECURE', 'false') === 'true',
	'cookie_httponly' => TRUE,

	'timezone' => wy_env('WY_TIMEZONE', 'Asia/Shanghai'),

	// 登录防护
	'lock_fails'   => 5,
	'lock_seconds' => 900,
	'ip_fail_limit' => 20,   // 同 IP 60 秒内失败上限
	'ip_fail_window' => 60,

	// 后台超级管理组名（中心 groups.name，与禅道组名同步）
	'super_group' => '超级管理',

	// 站点前端地址（账号面板跳转用）
	'sites' => array(
		'forum' => wy_env('WY_SITE_FORUM', 'http://forum.wenyinos.test:8080/'),
		'dev'   => wy_env('WY_SITE_DEV', 'http://dev.wenyinos.test:8080/'),
	),
);
