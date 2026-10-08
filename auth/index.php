<?php
// wenyinos 认证中心 · 账号面板（登录后）
require __DIR__ . '/core.php';

$user = wy_current_user();
if(!$user)
{
	header('Location: login.php');
	exit;
}

$groups = wy_user_groups($user['uid']);
$apps = wy_user_apps($user['uid']);
$sites = wy_config('sites');
$is_super = wy_is_super($user['uid']);
$group_names = array();
foreach($groups as $g) $group_names[] = $g['name'];

$site_meta = array(
	'forum' => array('name' => '社区论坛', 'desc' => '交流 · 反馈 · 分享', 'icon' => 'fa-comments'),
	'dev'   => array('name' => '项目管理', 'desc' => '需求 · 任务 · 缺陷', 'icon' => 'fa-diagram-project'),
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>我的账号 · 玟茵开源社区</title>
<link rel="stylesheet" href="../assets/lib/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../assets/lib/font-awesome/css/all.min.css">
<style>
html, body { height: 100%; }
body { margin: 0; font-family: -apple-system, "Segoe UI", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif; }

.panel-page { min-height: 100%; display: flex; }

/* ── 左侧品牌区（与登录页同款）──── */
.brand-side {
	position: relative;
	flex: 0 0 42%;
	overflow: hidden;
	color: #fff;
	background: linear-gradient(160deg, #402a75 0%, #6d4bb8 48%, #8f6ed6 100%);
}
.brand-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.deco-1 { width: 420px; height: 420px; right: -120px; top: -140px; background: radial-gradient(circle, rgba(255,255,255,.18) 0%, rgba(255,255,255,0) 70%); }
.deco-2 { width: 560px; height: 560px; left: -180px; bottom: -260px; background: radial-gradient(circle, rgba(255,255,255,.14) 0%, rgba(255,255,255,0) 70%); }
.brand-inner { position: relative; z-index: 1; padding: 11vh 8% 0 9%; max-width: 560px; }
.brand-logo { width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-size: 25px; font-weight: 700; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); margin-bottom: 26px; }
.brand-title { font-size: 32px; font-weight: 700; letter-spacing: 2px; margin: 0 0 14px; }
.brand-sub { font-size: 15px; opacity: .85; margin: 0 0 34px; letter-spacing: 1px; }
.brand-user { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); border-radius: 12px; padding: 16px 18px; max-width: 340px; }
.brand-user .bu-label { font-size: 12px; opacity: .75; margin-bottom: 4px; }
.brand-user .bu-name { font-size: 18px; font-weight: 600; letter-spacing: .5px; }
.brand-user .bu-tags { margin-top: 10px; }
.brand-user .bu-tags span { display: inline-block; font-size: 12px; padding: 2px 10px; border-radius: 20px; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3); margin: 0 6px 6px 0; }
.brand-foot { position: absolute; left: 9%; bottom: 28px; font-size: 12px; opacity: .7; }
.brand-foot a { color: #fff; opacity: .85; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.35); }

/* ── 右侧内容区 ──────────────── */
.panel-side { flex: 1; background: #faf8ff; display: flex; align-items: center; padding: 48px 24px; }
.panel-box { width: min(520px, 100%); margin: 0 auto; }
.welcome { font-size: 14px; color: #8a81a3; margin-bottom: 8px; }
.sys-name { font-size: 26px; font-weight: 700; color: #2d2640; margin: 0 0 28px; letter-spacing: 1px; }

.card-block { background: #fff; border: 1px solid #eee8fa; border-radius: 14px; padding: 22px 24px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(109, 75, 184, .05); }
.card-title { font-size: 14px; font-weight: 600; color: #2d2640; margin: 0 0 16px; display: flex; align-items: center; gap: 8px; }
.card-title i { color: #6d4bb8; }

.info-row { display: flex; font-size: 14px; padding: 9px 0; border-bottom: 1px solid #f4f0fb; }
.info-row:last-child { border-bottom: 0; }
.info-row .k { flex: 0 0 96px; color: #8a81a3; }
.info-row .v { color: #2d2640; word-break: break-all; }
.tag { display: inline-block; font-size: 12px; padding: 2px 10px; border-radius: 20px; background: #f0eafc; color: #6d4bb8; margin: 0 6px 4px 0; }

.site-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.site-card {
	display: flex; align-items: center; gap: 14px;
	border: 1px solid #e3ddf0; border-radius: 12px; padding: 16px;
	color: #2d2640; text-decoration: none;
	transition: all .15s;
}
.site-card:hover { border-color: #6d4bb8; box-shadow: 0 6px 18px rgba(109, 75, 184, .12); color: #5c3da3; transform: translateY(-1px); }
.site-card .sc-icon { flex: 0 0 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; background: #f0eafc; color: #6d4bb8; }
.site-card .sc-name { font-size: 15px; font-weight: 600; }
.site-card .sc-desc { font-size: 12px; color: #9a91b0; margin-top: 2px; }
.site-card .sc-arrow { margin-left: auto; color: #c9bfe3; }

.no-site { font-size: 13px; color: #9a91b0; background: #faf8ff; border: 1px dashed #e3ddf0; border-radius: 10px; padding: 14px 16px; }

.panel-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 4px; }
.panel-actions .left-links a { font-size: 13px; color: #6d4bb8; text-decoration: none; margin-right: 18px; }
.panel-actions .left-links a:hover { text-decoration: underline; }
.btn-logout { border: 1px solid #e3ddf0; border-radius: 8px; font-size: 13px; color: #5c5470; padding: 6px 16px; text-decoration: none; }
.btn-logout:hover { border-color: #b7a5dd; color: #5c3da3; }

.panel-footer { margin-top: 30px; padding-top: 14px; border-top: 1px solid #eee8fa; text-align: center; font-size: 12px; color: #9a91b0; }

@media (max-width: 900px) {
	.brand-side { display: none; }
	.panel-side { align-items: flex-start; padding: 40px 20px; }
	.site-grid { grid-template-columns: 1fr; }
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
			<h1 class="brand-title">玟茵开源社区</h1>
			<p class="brand-sub">统一身份认证 · 全站通行</p>
			<div class="brand-user">
				<div class="bu-label">当前登录</div>
				<div class="bu-name"><?php echo wy_h($user['username']); ?><?php if($user['realname']): ?><span style="font-size:13px; opacity:.8;"> · <?php echo wy_h($user['realname']); ?></span><?php endif; ?></div>
				<div class="bu-tags">
					<?php foreach($group_names as $gn): ?><span><?php echo wy_h($gn); ?></span><?php endforeach; ?>
				</div>
			</div>
		</div>
		<div class="brand-foot">玟茵开源社区 · wenyinos.com　<a href="https://wenyinos.com">返回主站</a></div>
	</div>

	<!-- 右侧内容区 -->
	<div class="panel-side">
		<div class="panel-box">
			<div class="welcome">欢迎回来</div>
			<h2 class="sys-name">我的账号</h2>

			<!-- 账号信息 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-id-card"></i> 账号信息</div>
				<div class="info-row"><div class="k">用户名</div><div class="v"><?php echo wy_h($user['username']); ?></div></div>
				<?php if($user['realname']): ?><div class="info-row"><div class="k">姓名</div><div class="v"><?php echo wy_h($user['realname']); ?></div></div><?php endif; ?>
				<?php if($user['email']): ?><div class="info-row"><div class="k">邮箱</div><div class="v"><?php echo wy_h($user['email']); ?></div></div><?php endif; ?>
				<div class="info-row"><div class="k">角色</div><div class="v"><?php foreach($group_names as $gn): ?><span class="tag"><?php echo wy_h($gn); ?></span><?php endforeach; ?></div></div>
			</div>

			<!-- 可访问站点 -->
			<div class="card-block">
				<div class="card-title"><i class="fas fa-globe"></i> 可访问站点</div>
				<?php if($apps): ?>
				<div class="site-grid">
					<?php foreach(array_keys($sites) as $app_id): if(!in_array($app_id, $apps, true) || !isset($site_meta[$app_id])) continue; $m = $site_meta[$app_id]; ?>
					<a class="site-card" href="<?php echo wy_h($sites[$app_id]); ?>">
						<div class="sc-icon"><i class="fas <?php echo $m['icon']; ?>"></i></div>
						<div>
							<div class="sc-name"><?php echo $m['name']; ?></div>
							<div class="sc-desc"><?php echo $m['desc']; ?></div>
						</div>
						<div class="sc-arrow"><i class="fas fa-arrow-right"></i></div>
					</a>
					<?php endforeach; ?>
				</div>
				<?php else: ?>
				<div class="no-site">当前账号未开通任何站点访问权限，请联系社区管理员。退出登录后，可以游客身份浏览社区内容。</div>
				<?php endif; ?>
			</div>

			<!-- 操作区 -->
			<div class="panel-actions">
				<div class="left-links">
					<a href="profile.php"><i class="fas fa-user-pen"></i> 账号设置</a>
					<?php if($is_super): ?><a href="admin/index.php"><i class="fas fa-gear"></i> 管理后台</a><?php endif; ?>
				</div>
				<a class="btn-logout" href="logout.php"><i class="fas fa-right-from-bracket"></i> 退出登录</a>
			</div>

			<div class="panel-footer">
				账号开通 / 密码问题请联系社区管理员
			</div>
		</div>
	</div>

</div>
</body>
</html>
