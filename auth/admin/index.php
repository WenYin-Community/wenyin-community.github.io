<?php
// wenyinos 认证中心 · 后台：用户管理
require __DIR__ . '/_layout.php';

$notice = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	admin_csrf_check();
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
	$uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

	if($action === 'create')
	{
		$username = trim((string)(isset($_POST['username']) ? $_POST['username'] : ''));
		$email = trim((string)(isset($_POST['email']) ? $_POST['email'] : ''));
		$realname = trim((string)(isset($_POST['realname']) ? $_POST['realname'] : ''));
		$password = (string)(isset($_POST['password']) ? $_POST['password'] : '');
		$group_ids = isset($_POST['groups']) && is_array($_POST['groups']) ? array_map('intval', $_POST['groups']) : array();

		if(!preg_match('/^[A-Za-z0-9_\.\-]{2,32}$/', $username))
		{
			$error = '用户名需为 2-32 位字母/数字/下划线';
		}
		elseif(strlen($password) < 6)
		{
			$error = '密码至少 6 位';
		}
		elseif(wy_find_user($username))
		{
			$error = '用户名已存在';
		}
		elseif($email !== '' && wy_find_user($email))
		{
			$error = '邮箱已被使用';
		}
		else
		{
			$stmt = wy_db()->prepare('INSERT INTO users (username, password, email, realname, status, create_date) VALUES (?, ?, NULLIF(?, \'\'), ?, 1, ?)');
			$stmt->execute(array($username, md5($password), $email, $realname, time()));
			$new_uid = wy_db()->lastInsertId();
			foreach($group_ids as $gid)
			{
				$stmt = wy_db()->prepare('INSERT IGNORE INTO user_group (uid, group_id) VALUES (?, ?)');
				$stmt->execute(array($new_uid, $gid));
			}
			wy_rebuild_user_apps($new_uid);
			wy_audit('auth', $admin_user['uid'], 'admin_user_create');
			$notice = '用户 ' . $username . ' 创建成功';
		}
	}
	elseif($action === 'toggle' && $uid > 0)
	{
		$target = wy_find_user_by_uid($uid);
		if($target && $target['uid'] != $admin_user['uid'])
		{
			$new_status = intval($target['status']) === 1 ? 0 : 1;
			$stmt = wy_db()->prepare('UPDATE users SET status = ?, fails = 0, lock_until = 0 WHERE uid = ?');
			$stmt->execute(array($new_status, $uid));
			wy_audit('auth', $admin_user['uid'], $new_status === 1 ? 'admin_user_enable' : 'admin_user_disable');
			$notice = '用户状态已更新';
		}
		else
		{
			$error = '不能操作自己';
		}
	}
	elseif($action === 'resetpw' && $uid > 0)
	{
		$newpw = (string)(isset($_POST['newpassword']) ? $_POST['newpassword'] : '');
		if(strlen($newpw) < 6)
		{
			$error = '密码至少 6 位';
		}
		else
		{
			$stmt = wy_db()->prepare('UPDATE users SET password = ?, fails = 0, lock_until = 0 WHERE uid = ?');
			$stmt->execute(array(md5($newpw), $uid));
			wy_audit('auth', $admin_user['uid'], 'admin_password_reset');
			$notice = '密码已重置';
		}
	}
	elseif($action === 'verify' && $uid > 0)
	{
		$stmt = wy_db()->prepare('UPDATE users SET verified = 1, reset_token = NULL, reset_expire = 0 WHERE uid = ?');
		$stmt->execute(array($uid));
		wy_audit('auth', $admin_user['uid'], 'admin_user_verify');
		$notice = '用户已通过验证';
	}
}

$users = wy_db()->query('SELECT * FROM users ORDER BY uid ASC')->fetchAll();
$all_groups = wy_db()->query('SELECT * FROM groups ORDER BY sort ASC, id ASC')->fetchAll();

admin_header('用户管理');
?>

<?php if($notice): ?><div class="alert alert-success py-2"><?php echo wy_h($notice); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger py-2"><?php echo wy_h($error); ?></div><?php endif; ?>

<div class="card mb-4">
	<div class="card-body">
		<h6 class="mb-3"><i class="fas fa-user-plus"></i> 创建用户</h6>
		<form method="post" class="row g-2 align-items-end">
			<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
			<input type="hidden" name="action" value="create">
			<div class="col-md-2"><label class="form-label small">用户名</label><input class="form-control form-control-sm" name="username" required></div>
			<div class="col-md-2"><label class="form-label small">邮箱（可空）</label><input class="form-control form-control-sm" name="email" type="email"></div>
			<div class="col-md-2"><label class="form-label small">姓名（可空）</label><input class="form-control form-control-sm" name="realname"></div>
			<div class="col-md-2"><label class="form-label small">初始密码</label><input class="form-control form-control-sm" name="password" required minlength="6"></div>
			<div class="col-md-3">
				<label class="form-label small">加入组（可多选）</label>
				<div class="d-flex flex-wrap gap-2">
				<?php foreach($all_groups as $g): ?>
					<div class="form-check form-check-inline me-0">
						<input class="form-check-input" type="checkbox" name="groups[]" value="<?php echo intval($g['id']); ?>" id="cg<?php echo intval($g['id']); ?>">
						<label class="form-check-label small" for="cg<?php echo intval($g['id']); ?>"><?php echo wy_h($g['name']); ?></label>
					</div>
				<?php endforeach; ?>
				</div>
			</div>
			<div class="col-md-1"><button class="btn btn-sm btn-brand text-white w-100">创建</button></div>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-body p-0">
		<table class="table table-hover mb-0 align-middle">
			<thead class="table-light">
				<tr>
					<th class="ps-3">UID</th><th>用户名</th><th>姓名</th><th>邮箱</th><th>组</th><th>可访问站点</th><th>状态</th><th>验证</th><th>最后登录</th><th class="text-end pe-3">操作</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach($users as $u):
				$ugs = wy_user_groups($u['uid']);
				$uapps = wy_user_apps($u['uid']);
			?>
				<tr>
					<td class="ps-3"><?php echo intval($u['uid']); ?></td>
					<td><a href="users.php?uid=<?php echo intval($u['uid']); ?>"><?php echo wy_h($u['username']); ?></a></td>
					<td><?php echo wy_h($u['realname']); ?></td>
					<td class="small"><?php echo wy_h((string)$u['email']); ?></td>
					<td class="small"><?php foreach($ugs as $g): ?><span class="badge badge-g"><?php echo wy_h($g['name']); ?></span><?php endforeach; ?></td>
					<td class="small"><?php echo $uapps ? wy_h(implode('、', $uapps)) : '<span class="text-muted">无</span>'; ?></td>
					<td><?php echo intval($u['status']) === 1 ? '<span class="text-success">正常</span>' : '<span class="text-danger">禁用</span>'; ?></td>
					<td><?php echo intval($u['verified']) === 1 ? '<span class="text-success">已验证</span>' : '<span class="text-warning">待验证</span>'; ?></td>
					<td class="small text-muted"><?php echo $u['last_login'] ? date('Y-m-d H:i', $u['last_login']) : '—'; ?></td>
					<td class="text-end pe-3">
						<?php if(intval($u['verified']) !== 1): ?>
						<form method="post" class="d-inline" onsubmit="return confirm('确认为该用户通过验证？');">
							<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
							<input type="hidden" name="action" value="verify">
							<input type="hidden" name="uid" value="<?php echo intval($u['uid']); ?>">
							<button class="btn btn-sm btn-brand text-white">通过验证</button>
						</form>
						<?php endif; ?>
						<form method="post" class="d-inline" onsubmit="return confirm('确认操作？');">
							<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
							<input type="hidden" name="action" value="toggle">
							<input type="hidden" name="uid" value="<?php echo intval($u['uid']); ?>">
							<button class="btn btn-sm btn-outline-secondary"><?php echo intval($u['status']) === 1 ? '禁用' : '启用'; ?></button>
						</form>
						<form method="post" class="d-inline" onsubmit="var p = prompt('输入新密码（至少 6 位）'); if(!p) return false; this.newpassword.value = p; return true;">
							<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
							<input type="hidden" name="action" value="resetpw">
							<input type="hidden" name="uid" value="<?php echo intval($u['uid']); ?>">
							<input type="hidden" name="newpassword" value="">
							<button class="btn btn-sm btn-outline-secondary">重置密码</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php admin_footer(); ?>
