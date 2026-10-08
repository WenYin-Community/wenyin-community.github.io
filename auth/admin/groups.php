<?php
// wenyinos 认证中心 · 后台：组管理
require __DIR__ . '/_layout.php';

$APPS = array('forum' => '论坛（forum）', 'dev' => '禅道（dev）');

$notice = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
	admin_csrf_check();
	$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

	if($action === 'save')
	{
		$id = intval($_POST['id']);
		$g = wy_db()->prepare('SELECT * FROM groups WHERE id = ?');
		$g->execute(array($id));
		$group = $g->fetch();
		if(!$group)
		{
			$error = '组不存在';
		}
		else
		{
			$apps_arr = isset($_POST['apps']) && is_array($_POST['apps']) ? array_intersect(array_keys($APPS), $_POST['apps']) : array();
			$apps = implode(',', $apps_arr);
			$bbs_gid = intval($_POST['bbs_gid']);
			$zentao_group = intval($_POST['zentao_group']);
			$sort = intval($_POST['sort']);
			$remark = trim((string)$_POST['remark']);

			$stmt = wy_db()->prepare('UPDATE groups SET apps = ?, bbs_gid = ?, zentao_group = ?, sort = ?, remark = ? WHERE id = ?');
			$stmt->execute(array($apps, $bbs_gid, $zentao_group, $sort, $remark, $id));
			wy_audit('auth', $admin_user['uid'], 'admin_group_save');

			// 该组所有成员的站点准入全量重算
			$members = wy_db()->prepare('SELECT uid FROM user_group WHERE group_id = ?');
			$members->execute(array($id));
			foreach($members->fetchAll() as $row) wy_rebuild_user_apps($row['uid']);

			$notice = '组「' . $group['name'] . '」已保存，成员站点准入已重算';
		}
	}
	elseif($action === 'create')
	{
		$name = trim((string)$_POST['name']);
		if(!preg_match('/^[a-z0-9_\-]{2,30}$/', $name))
		{
			$error = '组名需为 2-30 位小写字母/数字/下划线';
		}
		else
		{
			$apps_arr = isset($_POST['apps']) && is_array($_POST['apps']) ? array_intersect(array_keys($APPS), $_POST['apps']) : array();
			$stmt = wy_db()->prepare('INSERT INTO groups (name, apps, bbs_gid, zentao_group, sort, remark) VALUES (?, ?, ?, ?, ?, ?)');
			$stmt->execute(array($name, implode(',', $apps_arr), intval($_POST['bbs_gid']), intval($_POST['zentao_group']), intval($_POST['sort']), trim((string)$_POST['remark'])));
			wy_audit('auth', $admin_user['uid'], 'admin_group_create');
			$notice = '组已创建';
		}
	}
	elseif($action === 'delete')
	{
		$id = intval($_POST['id']);
		$member_count = wy_db()->prepare('SELECT COUNT(*) AS n FROM user_group WHERE group_id = ?');
		$member_count->execute(array($id));
		if(intval($member_count->fetch()['n']) > 0)
		{
			$error = '组内仍有成员，先移出成员再删除';
		}
		else
		{
			$stmt = wy_db()->prepare('DELETE FROM groups WHERE id = ?');
			$stmt->execute(array($id));
			wy_audit('auth', $admin_user['uid'], 'admin_group_delete');
			$notice = '组已删除';
		}
	}
}

$groups = wy_db()->query('SELECT * FROM groups ORDER BY sort ASC, id ASC')->fetchAll();
$member_counts = array();
foreach(wy_db()->query('SELECT group_id, COUNT(*) AS n FROM user_group GROUP BY group_id')->fetchAll() as $row)
{
	$member_counts[intval($row['group_id'])] = intval($row['n']);
}

admin_header('组管理');
?>

<?php if($notice): ?><div class="alert alert-success py-2"><?php echo wy_h($notice); ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger py-2"><?php echo wy_h($error); ?></div><?php endif; ?>

<div class="alert alert-light border small mb-4">
	说明：组的「站点」勾选决定成员可访问哪些站点；BBS gid / 禅道组为下发到两端分站的角色映射；排序值越小优先级越高（决定下发给 BBS 的 gid 归并结果，「超级管理」应为 1）。
</div>

<?php foreach($groups as $g):
	$apps_arr = $g['apps'] === '' ? array() : explode(',', $g['apps']);
?>
<div class="card mb-3">
	<div class="card-body">
		<form method="post" class="row g-2 align-items-end">
			<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="id" value="<?php echo intval($g['id']); ?>">
			<div class="col-md-2">
				<label class="form-label small">组名</label>
				<input class="form-control form-control-sm" value="<?php echo wy_h($g['name']); ?>" disabled>
			</div>
			<div class="col-md-3">
				<label class="form-label small">可访问站点</label>
				<div class="d-flex gap-3">
				<?php foreach($APPS as $app_id => $app_name): ?>
					<div class="form-check form-check-inline me-0">
						<input class="form-check-input" type="checkbox" name="apps[]" value="<?php echo $app_id; ?>" id="a<?php echo intval($g['id']) . $app_id; ?>" <?php echo in_array($app_id, $apps_arr, true) ? 'checked' : ''; ?>>
						<label class="form-check-label small" for="a<?php echo intval($g['id']) . $app_id; ?>"><?php echo $app_name; ?></label>
					</div>
				<?php endforeach; ?>
				</div>
			</div>
			<div class="col-md-1"><label class="form-label small">BBS gid</label><input class="form-control form-control-sm" name="bbs_gid" value="<?php echo intval($g['bbs_gid']); ?>"></div>
			<div class="col-md-1"><label class="form-label small">禅道组</label><input class="form-control form-control-sm" name="zentao_group" value="<?php echo intval($g['zentao_group']); ?>"></div>
			<div class="col-md-1"><label class="form-label small">排序</label><input class="form-control form-control-sm" name="sort" value="<?php echo intval($g['sort']); ?>"></div>
			<div class="col-md-2"><label class="form-label small">备注</label><input class="form-control form-control-sm" name="remark" value="<?php echo wy_h($g['remark']); ?>"></div>
			<div class="col-md-2 d-flex gap-1">
				<button class="btn btn-sm btn-brand text-white flex-fill">保存</button>
			</div>
		</form>
		<div class="d-flex justify-content-between align-items-center mt-2">
			<span class="small text-muted">成员 <?php echo isset($member_counts[intval($g['id'])]) ? $member_counts[intval($g['id'])] : 0; ?> 人</span>
			<?php if(isset($member_counts[intval($g['id'])]) && $member_counts[intval($g['id'])] == 0): ?>
			<form method="post" class="d-inline" onsubmit="return confirm('确认删除该组？');">
				<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
				<input type="hidden" name="action" value="delete">
				<input type="hidden" name="id" value="<?php echo intval($g['id']); ?>">
				<button class="btn btn-sm btn-outline-danger">删除组</button>
			</form>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endforeach; ?>

<div class="card">
	<div class="card-body">
		<h6 class="mb-3">新建组</h6>
		<form method="post" class="row g-2 align-items-end">
			<input type="hidden" name="csrf" value="<?php echo wy_h(admin_csrf()); ?>">
			<input type="hidden" name="action" value="create">
			<div class="col-md-2"><label class="form-label small">组名（英文）</label><input class="form-control form-control-sm" name="name" required></div>
			<div class="col-md-3">
				<label class="form-label small">可访问站点</label>
				<div class="d-flex gap-3">
				<?php foreach($APPS as $app_id => $app_name): ?>
					<div class="form-check form-check-inline me-0">
						<input class="form-check-input" type="checkbox" name="apps[]" value="<?php echo $app_id; ?>" id="new<?php echo $app_id; ?>">
						<label class="form-check-label small" for="new<?php echo $app_id; ?>"><?php echo $app_name; ?></label>
					</div>
				<?php endforeach; ?>
				</div>
			</div>
			<div class="col-md-1"><label class="form-label small">BBS gid</label><input class="form-control form-control-sm" name="bbs_gid" value="0"></div>
			<div class="col-md-1"><label class="form-label small">禅道组</label><input class="form-control form-control-sm" name="zentao_group" value="0"></div>
			<div class="col-md-1"><label class="form-label small">排序</label><input class="form-control form-control-sm" name="sort" value="50"></div>
			<div class="col-md-2"><label class="form-label small">备注</label><input class="form-control form-control-sm" name="remark"></div>
			<div class="col-md-2"><button class="btn btn-sm btn-brand text-white w-100">创建</button></div>
		</form>
	</div>
</div>

<?php admin_footer(); ?>
