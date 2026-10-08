<?php
// wenyinos 认证中心 · 后台公共布局
require dirname(__DIR__) . '/core.php';
wy_session_start();

$admin_user = wy_require_admin();

function admin_csrf()
{
	if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = wy_rand_hex(32);
	return $_SESSION['csrf'];
}

function admin_csrf_check()
{
	if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf']))
	{
		http_response_code(400);
		exit('CSRF 校验失败');
	}
}

function admin_header($title)
{
	global $admin_user;
	$navs = array(
		'index.php' => '用户管理',
		'groups.php' => '组管理',
		'apps.php' => '应用密钥',
		'settings.php' => '系统设置',
	);
	$current = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo wy_h($title); ?> · 认证中心后台</title>
<link rel="stylesheet" href="../../assets/lib/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/lib/font-awesome/css/all.min.css">
<style>
body { background: #f7f5fc; }
.navbar-brand { color: #6d4bb8 !important; font-weight: 600; }
.nav-link.active { color: #6d4bb8 !important; font-weight: 600; }
.card { border: 1px solid #e8e2f5; border-radius: 12px; }
.table { font-size: 14px; }
.btn-brand { background: #6d4bb8; border-color: #6d4bb8; }
.btn-brand:hover { background: #5c3da3; border-color: #5c3da3; }
.badge-g { background: #f0eafc; color: #6d4bb8; font-weight: 500; margin-right: 4px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
	<div class="container">
		<a class="navbar-brand" href="index.php"><i class="fas fa-shield-halved"></i> 认证中心后台</a>
		<ul class="navbar-nav me-auto">
			<?php foreach($navs as $url => $name): ?>
			<li class="nav-item"><a class="nav-link <?php echo $current === $url ? 'active' : ''; ?>" href="<?php echo $url; ?>"><?php echo $name; ?></a></li>
			<?php endforeach; ?>
		</ul>
		<span class="navbar-text me-3 small"><?php echo wy_h($admin_user['username']); ?></span>
		<a class="btn btn-sm btn-outline-secondary" href="../index.php">返回面板</a>
	</div>
</nav>
<div class="container pb-5">
<?php
}

function admin_footer()
{
?>
</div>
</body>
</html>
<?php
}
