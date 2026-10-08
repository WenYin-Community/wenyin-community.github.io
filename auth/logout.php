<?php
// wenyinos 认证中心 · 登出（销毁中心票据）
require __DIR__ . '/core.php';

$ticket = isset($_COOKIE[wy_config('cookie_name')]) ? $_COOKIE[wy_config('cookie_name')] : '';
if($ticket !== '')
{
	// 本地直接撤销（等价 /api/revoke 的内部调用，无需走网络）
	$pos = strrpos($ticket, '.');
	if($pos !== false)
	{
		$payload = base64_decode(strtr(substr($ticket, 0, $pos), '-_', '+/'), true);
		if($payload !== false)
		{
			$arr = explode('|', $payload);
			if(count($arr) === 4 && hash_equals(hash_hmac('sha256', $payload, wy_config('ticket_key')), substr($ticket, $pos + 1)))
			{
				wy_ticket_revoke($arr[3], intval($arr[2]));
				wy_audit('auth', intval($arr[0]), 'ticket_revoke');
			}
		}
	}
}
wy_clear_auth_cookie();
header('Location: login.php');
exit;
