<?php
// wenyinos 认证中心 · 页面壳（左侧品牌区 + 右侧表单区），供 login / register / forgot / reset 使用

function wy_page_head($title, $box_welcome = '欢迎登录', $box_title = '统一认证中心')
{
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo wy_h($title); ?> · 玟茵开源社区</title>
<link rel="stylesheet" href="../assets/lib/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../assets/lib/font-awesome/css/all.min.css">
<style>
html { height: 100%; }
body { min-height: 100%; margin: 0; font-family: -apple-system, "Segoe UI", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif; }

/* 流式布局：内容超出视口时页面自然滚动，品牌区与表单区随内容等高拉伸（避免居中裁切与白条） */
.login-page { min-height: 100vh; display: flex; }

/* ── 左侧品牌区（紫色渐变自绘）──── */
.brand-side {
	position: relative;
	flex: 0 0 56%;
	overflow: hidden;
	color: #fff;
	background: linear-gradient(160deg, #402a75 0%, #6d4bb8 48%, #8f6ed6 100%);
}
.brand-deco { position: absolute; border-radius: 50%; pointer-events: none; }
.deco-1 { width: 420px; height: 420px; right: -120px; top: -140px; background: radial-gradient(circle, rgba(255,255,255,.18) 0%, rgba(255,255,255,0) 70%); }
.deco-2 { width: 560px; height: 560px; left: -180px; bottom: -260px; background: radial-gradient(circle, rgba(255,255,255,.14) 0%, rgba(255,255,255,0) 70%); }
.brand-inner { position: relative; z-index: 1; padding: 12vh 8% 0 9%; max-width: 620px; }
.brand-logo { width: 52px; height: 52px; border-radius: 12px; display: grid; place-items: center; font-size: 25px; font-weight: 700; background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.35); margin-bottom: 26px; }
.brand-title { font-size: 34px; font-weight: 700; letter-spacing: 2px; margin: 0 0 14px; }
.brand-sub { font-size: 15px; opacity: .85; margin: 0 0 34px; letter-spacing: 1px; }
.brand-points { list-style: none; margin: 0; padding: 0; font-size: 14px; line-height: 2.4; opacity: .9; }
.brand-points li::before { content: '·'; margin-right: 10px; font-weight: 700; }
.brand-foot { position: absolute; left: 9%; bottom: 28px; font-size: 12px; opacity: .7; }
.brand-foot a { color: #fff; opacity: .85; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,.35); }

/* ── 右侧表单区 ──────────────── */
.login-side { flex: 1; display: flex; align-items: center; justify-content: center; background: #fff; padding: 24px; }
.login-box { width: min(380px, 100%); padding-bottom: 40px; }
.welcome { font-size: 14px; color: #8a81a3; margin-bottom: 8px; }
.sys-name { font-size: 28px; font-weight: 700; color: #2d2640; margin: 0 0 30px; letter-spacing: 1px; }
.form-label { font-size: 13px; color: #5c5470; }
.form-control { border-radius: 8px; padding: 9px 12px; border-color: #e3ddf0; }
.form-control:focus { border-color: #6d4bb8; box-shadow: 0 0 0 3px rgba(109, 75, 184, .12); }
.field-group { margin-bottom: 20px; }
.field-hint { font-size: 12px; color: #b3aac9; margin-top: 5px; }
.submit { width: 100%; height: 44px; font-size: 16px; border-radius: 8px; letter-spacing: 4px; background: #6d4bb8; border-color: #6d4bb8; color: #fff; }
.submit:hover { background: #5c3da3; border-color: #5c3da3; color: #fff; }
.link-row { display: flex; justify-content: space-between; margin-top: 16px; font-size: 13px; }
.link-row a { color: #6d4bb8; text-decoration: none; }
.link-row a:hover { text-decoration: underline; }
.login-footer { margin-top: 46px; padding-top: 14px; border-top: 1px solid #efeaf8; text-align: center; font-size: 12px; color: #9a91b0; }
.login-footer a { color: #8a7ab8; text-decoration: none; }
.success-box { border: 1px solid #d9f0dd; background: #f2fbf4; color: #2e7d43; border-radius: 10px; padding: 18px 20px; font-size: 14px; line-height: 1.8; }
.success-box a { color: #2e7d43; font-weight: 600; }

@media (max-width: 900px) {
	.brand-side { display: none; }
	.login-side { align-items: flex-start; padding: 40px 24px; }
}
</style>
</head>
<body>
<div class="login-page">

	<!-- 左侧品牌区 -->
	<div class="brand-side">
		<div class="brand-deco deco-1"></div>
		<div class="brand-deco deco-2"></div>
		<div class="brand-inner">
			<div class="brand-logo">玟</div>
			<h1 class="brand-title">玟茵开源社区</h1>
			<p class="brand-sub">统一身份认证 · 全站通行</p>
			<ul class="brand-points">
				<li>一次登录 · 论坛与项目管理自动通行</li>
				<li>账号统一管理 · 站点访问权限可控</li>
				<li>修改密码全域同步 · 安全可靠</li>
			</ul>
		</div>
		<div class="brand-foot">玟茵开源社区 · wenyinos.com　<a href="https://wenyinos.com">返回主站</a></div>
	</div>

	<!-- 右侧表单区 -->
	<div class="login-side">
		<div class="login-box">
			<div class="welcome"><?php echo wy_h($box_welcome); ?></div>
			<h2 class="sys-name"><?php echo wy_h($box_title); ?></h2>
<?php
}

function wy_page_foot($footer_note = '账号开通 / 密码问题请联系社区管理员')
{
?>
			<div class="login-footer">
				<?php echo wy_h($footer_note); ?>
			</div>
		</div>
	</div>

</div>
</body>
</html>
<?php
}

// 表单错误提示块
function wy_error_box($error)
{
	if($error === '') return;
	echo '<div class="alert alert-danger py-2 small" style="border-radius:8px;">' . wy_h($error) . '</div>';
}
