# wenyinos.com 统一认证中心方案（v4 终稿）

> 定稿日期：2026-10-07（v4 = v3 + 审核修复 13 项，此前版本作废）
> 数据来源：comm_wenyinos_2026-10-07_02-00-24（BBS 26 表 + EPS 57 表已废弃不采用）、dev_wenyinos_2026-10-07_02-00-32（禅道 208 表）
> 备份文件：/home/zemi/MyWork/sql/*.sql.gz（导出自 MySQL 5.7.44）
> 站点：主站 wenyinos.com（静态 Bootstrap 5，源码 /home/zemi/MyDev/wenyin-community.github.io，GitHub 仓库仅镜像，服务器手动部署）+ forum.wenyinos.com（Xiuno BBS 4.0.2）+ dev.wenyinos.com（禅道）
> 部署：同一生产服务器（Tengine）、同一 MySQL 实例；认证中心部署于 https://wenyinos.com/auth
> 决策记录：EPS（主站遗留会员/商城系统，65 账号 2017 年后休眠）已废弃，不导入、不接入、不迁移

## 全部已拍板决策

| # | 决策 | 结论 |
|---|---|---|
| 1 | 密码格式 | md5(明文)，以禅道为准 |
| 2 | 权限模型 | 按禅道组模型；中心后台控制用户可访问哪些站点 |
| 3 | 部署形态 | wenyinos.com/auth 路径，UI 引用主站静态资源；主站为自有服务器手动部署（GitHub 仓库仅镜像，/auth 不进仓库——.gitignore 排除 auth/） |
| 4 | 登录态继承 | wy_auth 跨子域 cookie（.wenyinos.com）+ 分站 hook 静默兑换 |
| 5 | 强制改密 | 禅道 upsert visits=1 绕过（changeWeak 已关，my.php 实测） |
| 6 | EPS 系统 | 废弃：不导入、不接入、不迁移 |
| 7 | 组准入 | super_admin/core/member=forum+dev 双站、forum_only=仅论坛、public=仅禅道、limited=无（v4 验收时按 4.1 表执行且符合预期；v4.10 组模型已重构，见 15.15） |
| 8 | BBS 休眠 6 号 | 无感迁移：首次登录 BBS 本地验证成功 → /api/migrate 上报中心 |
| 9 | 中心超管 | 复用 ruojiner（禅道密码导入即中心密码），不另建账号 |
| 10 | 联调数据 | 两份备份可导入本机 MySQL 容器；导入后全部密码改写为统一测试值 + 生成虚拟用户补充联调 |
| 11 | 反向同步 | 分站改密/改邮箱推回中心；邮箱冲突拒绝该字段不覆盖 |
| 12 | 降级语义 | 中心宕机 → 分站本地密码验证照常，已登录用户不受影响 |
| 13 | v4 审核修复 | 13 项（4 阻断 + 9 缺口）已并入正文，见 v4 修订对照表（第十四节） |

## 一、目标

1. 统一账号：认证中心管理全部用户，密码格式 `md5(明文)`（以禅道为准）
2. API 静默认证：BBS、禅道登录时调中心 verify 接口验证并自动 upsert
3. 登录态继承：主站 /auth 登录后，切到任意分站点自动保持登录（wy_auth 跨子域票据）
4. 站点准入权限：后台按用户/组控制可访问哪些网站（禅道式组模型）
5. 反向同步：分站点改密码/邮箱推回中心
6. 双方零核心改动：BBS 走插件机制，禅道走官方 ext 扩展机制

## 二、实测账号对账（2026-10-07 备份）

### 2.1 账号总览（EPS 已废弃，不在方案范围）

| 系统 | 账号数 | 密码格式 | 最后活跃 |
|---|---|---|---|
| 禅道 zt_user | 8 | md5(明文) | wenyinos 2026-10-06（最活跃）、ruojiner 2026-08-05 |
| BBS bbs_user | 10 | md5(md5(明文).salt) | ruojiner 2026-08-18（唯一近年活跃） |

comm_wenyinos 库中的 EPS 系统（eps_* 57 表）为已废弃遗留系统，不导入、不接入、不迁移；其账号密码格式虽与中心兼容（同为 md5），日后如需回收再另行决策。

### 2.2 活跃核心账号对账（4 个）

| 用户名 | BBS | 禅道 | 中心处理 |
|---|---|---|---|
| ruojiner | ✓ gid=1 管理员组 | ✓ 管理员组1+核心开发4，公司 admins 名单 | 三平台管理员；密码取禅道；中心 super_admin |
| weijie | ✓ gid=101 | ✓ 核心开发4 | email 两端一致（weijie001@139.com）；密码取禅道 |
| wenyinos | ✓ gid=101（email=ruojiner@163.com，ruojiner 代注） | ✓ 参与项目6（email=weijie001@foxmail.com） | 品牌号，实为 weijie 侧账号；email/密码以禅道为准 |
| narukeu | ✗ | ✓ 核心开发4 | 仅禅道；密码取禅道 |

注：禅道 weijie 与 wenyinos 密码哈希相同（同一明文），确认为同一人两个号。

### 2.3 其余账号

- **仅 BBS（6 个，2018-2021 注册后休眠）**：Windelight、pingtaip、lza07、pingtaisan、zccrs、MeredithJeames —— 密码为 md5(md5.盐) **无法转换为中心格式**，采用无感迁移（见第六节登录 hook 逻辑）
- **仅禅道（3 个）**：public1/2/3，公用账号组13，密码直接导入

### 2.4 密码权威切换（导入即生效）

| 账号 | 导入后全域密码 | 说明 |
|---|---|---|
| ruojiner / weijie / wenyinos / liuweizzuie / narukeu / public1-3 | 禅道现密码 | BBS 旧密码作废；首次登录 BBS 时 upsert 自动覆盖本地密码 |
| BBS 休眠 6 号 | 仍为 BBS 本地密码 | 无感迁移：首次登录 BBS 验证本地密码成功后上报中心，之后全域统一 |

## 三、架构

```
                MySQL 实例（原有）
   ┌──────────────┬────────────────┬──────────────┐
   dev_wenyinos    comm_wenyinos     wenyinos_auth（新建库）
   zt_user         bbs_user          users / apps / groups / user_group / user_app / nonces / audit_log
                                      ↑
                wenyinos.com/auth（认证中心，轻量纯 PHP 新服务）
                端点：/api/verify /api/ticket /api/password /api/migrate /api/revoke
                        ↑ HMAC-SHA256 签名 API（各站独立 secret）
          ┌─────────────┴─────────────┐
     禅道 ext 扩展                BBS 插件 xn_sso
```

### 登录流（API 静默认证）

```
禅道登录(明文密码→md5) ─┐
                        ├→ POST /api/verify (app_id, account, md5密码, ts, nonce, HMAC)
BBS登录(前端已md5) ──────┘      │ 验签+时间窗+nonce+限流 → 查 user_app 站点准入
                               ├ 未授权 → 专用错误码，提示"未开通本站访问"，不 upsert
                               └ 用户不存在（仅 BBS 场景）→ 本地密码验证 → 成功则
                                 POST /api/migrate 无感迁移（md5 明文哈希直传中心）
                               └ 授权 → 返回 {uid, username, email, realname, bbs_gid, zentao_groups[数组]}
                                     ↓
                 各自 upsert 本地用户表（禅道 visits=1 绕过强制改密）→ 原生登录逻辑命中
```

### 登录态继承（跨子域静默 SSO）

```
主站 /auth 登录成功
 └─ setcookie('wy_auth', ticket, domain='.wenyinos.com', HttpOnly+Secure+SameSite=Lax)
    ticket = uid|username|expire，HMAC-SHA256 签名，有效期 1~2 小时

切到分站点 → hook 注入检测：本地未登录 且 有 wy_auth
  ├─ POST /api/ticket（app_id+ticket+HMAC）→ 中心验签 + 实时授权确认 + 用户资料 + 角色映射
  ├─ upsert 本地用户
  └─ 建立本站登录态（BBS session+token；禅道 session+keepLogin）
（未授权/票据无效时负缓存标记，不重打；中心不可达时静默失败，本地已登录态不受影响）
此后各站独立运行，中心宕机不影响已登录用户。
```

### 反向同步与登出

- 禅道 my-changePassword / my-editProfile / user-edit → ext 覆盖 updatePassword()/update() → POST /api/password
- BBS 改密（route/my.php）→ 插件 hook → POST /api/password
- 中心更新 users 表；邮箱冲突返回错误码（分站记日志，本地照常保存）；中心不可达 3 秒超时降级
- 分站登出：清本地态 + POST /api/revoke 销毁票据；中心登出只清 cookie

## 四、中心数据库设计（wenyinos_auth 库）

```sql
CREATE TABLE users (
  uid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  password VARCHAR(60) NOT NULL,        -- 双层哈希 bcrypt(md5(明文))（v4.10.6 安全升级，见安全性报告 H-1）
  email VARCHAR(90) DEFAULT NULL UNIQUE,  -- NULL 允许多个（public 号无邮箱）；空串一律转 NULL
  realname VARCHAR(60) NOT NULL DEFAULT '',
  status TINYINT NOT NULL DEFAULT 1,    -- 1正常 0禁用
  fails TINYINT UNSIGNED NOT NULL DEFAULT 0,      -- 连续登录失败计数
  lock_until INT UNSIGNED NOT NULL DEFAULT 0,     -- 锁定截止时间戳
  bbs_password CHAR(32) DEFAULT NULL,   -- BBS 休眠号凭证 md5(md5(明文).salt)，校验通过后升级主密码并清空
  bbs_salt CHAR(16) DEFAULT NULL,
  create_date INT UNSIGNED NOT NULL DEFAULT 0,
  last_login INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE apps (
  app_id VARCHAR(16) PRIMARY KEY,       -- forum / dev
  name VARCHAR(30) NOT NULL,
  secret CHAR(64) NOT NULL,             -- 各站独立 HMAC 密钥
  status TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE,
  apps VARCHAR(64) NOT NULL,            -- 逗号串，如 'forum,dev'（MySQL 5.7 无 JSON_TABLE，用 FIND_IN_SET）
  bbs_gid SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- 下发给 BBS 的 gid；0=不下发（不给论坛权限）
  zentao_group INT UNSIGNED NOT NULL DEFAULT 0,   -- 下发给禅道的 zt_group.id；0=不下发
  sort TINYINT UNSIGNED NOT NULL DEFAULT 0,       -- BBS gid 合并优先级：小者优先
  remark VARCHAR(120) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_group (
  uid INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (uid, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_app (
  uid INT UNSIGNED NOT NULL,
  app_id VARCHAR(16) NOT NULL,
  PRIMARY KEY (uid, app_id)             -- 存在即授权；由 groups.apps 展开维护，支持用户级直配
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE nonces (
  nonce CHAR(40) PRIMARY KEY,
  expire INT UNSIGNED NOT NULL,
  KEY idx_expire (expire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  app_id VARCHAR(16) NOT NULL DEFAULT '',
  uid INT UNSIGNED NOT NULL DEFAULT 0,
  action VARCHAR(30) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  date INT UNSIGNED NOT NULL,
  KEY idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 组初始数据（id 与 4.1 表对应；v4.10 起为组名与禅道同步后的最终态，见 15.15）
INSERT INTO groups (id, name, apps, bbs_gid, zentao_group, sort, remark) VALUES
  (1, '超级管理', 'forum,dev', 1,   1,  1, '全域管理员'),
  (2, '开发团队', 'forum,dev', 101, 4,  2, '开发团队（核心开发与参与项目合并）'),
  (4, '注册用户', 'forum,dev', 101, 11, 4, '新注册默认：论坛注册用户 + 禅道只读'),
  (6, '受限用户', '',          0,   12, 9, '受限（无站点）');
```

### 4.1 组初始值（基于两站实测数据；v4.10 起组模型已重构为四组同步体系，见 15.15）

| 中心组 | 站点准入 | BBS 映射 gid | 禅道映射组 | 初始成员 | 依据 |
|---|---|---|---|---|---|
| super_admin | forum+dev | 1（管理员组） | 1（管理员，406 权限位） | ruojiner | zt_company admins=ruojiner；bbs gid=1 |
| core | forum+dev | 101 | 4（核心开发，214 权限位） | weijie、narukeu | zt_usergroup 实测 |
| member | forum+dev | 101 | 6（参与项目，181 权限位） | wenyinos | zt_usergroup 实测 |
| forum_only | 仅 forum | 101 | —（无禅道权限） | BBS 休眠 6 号（迁移后归入） | 新增，承接 BBS 独有账号 |
| public | 仅 dev | — | 13（公用账号，104 权限位） | public1/2/3 | zt_usergroup 实测 |
| limited | 无 | — | 12（restricted，1 权限位） | — | 对应禅道受限组 |

规则：中心组携带 bbs_gid / zentao_group 映射列。**多组归并口径**：zentao_groups 以数组下发（upsert 时按数组重写 zt_usergroup 全部成员关系，ruojiner 保持组1+组4 两行，与禅道现状一致）；bbs_gid 单值 = 用户所属组中 sort 最小的组的 bbs_gid（超级管理 > 开发团队 > 注册用户；v4.10 起组名同步），如 ruojiner 取 gid=1。禅道 admins 名单（zt_company）保持本地权威不动；BBS 101+ 等级由发帖数自治不受影响。

### 4.2 两站组权限实测数据（供中心后台展示参考）

- BBS bbs_group 实际存在 gid：0/1/2/4/5/6/7/101-105（无 gid=3）；权限位 13 个，101-105 为发帖数 0/50/200/1000/10000 分档
- 禅道 zt_grouppriv 实测权限条数：组1=406、组4=214、组6=181、组11=91、组12=1、组13=104
- zt_config 实测：禅道自带 sso turnon=0（未启用，与本方案无冲突）

## 五、认证中心服务（wenyinos.com/auth，从头实施）

目录结构（服务器上位于主站根目录下，.gitignore 排除 auth/，不随镜像仓库分发）：

```
auth/
├── config.php            # 数据库连接、ticket 密钥、admin uid、时区
├── index.php             # 前端控制器（路由分发 + 公共函数：签名校验/nonce/限流/JSON 输出）
├── login.php             # 登录页（引用主站 /assets/lib/bootstrap 资源，紫色品牌风格）
├── admin/                # 管理后台（super_admin 登录后可用）
│   ├── index.php         # 用户列表/创建/启停/重置密码
│   ├── groups.php        # 组管理（勾选站点准入、BBS/禅道映射）
│   ├── users.php         # 用户分配组
│   └── apps.php          # 应用密钥管理与轮换
├── api.php               # API 入口（仅 POST，JSON 响应）
└── logout.php            # 中心登出（清 wy_auth cookie）
```

### API 协议（统一规范）

所有请求：`POST`，`Content-Type: application/json`；请求体仅含 `{"action":"...","data":{...}}`；凭证走 HTTP 头：`X-Wy-App / X-Wy-Timestamp / X-Wy-Nonce / X-Wy-Sign`（sign 在 body 之外，签名覆盖完整 body 原文，无循环依赖）。签名：`sign = hash_hmac('sha256', app_id|action|md5(raw_body)|timestamp|nonce, secret)`（raw_body = 完整请求体原文）。所有响应 JSON：`{"code": 0, "message": "...", "data": {...}}`。

| 端点 | 附加参数 | 成功返回 data | 错误码语义 |
|---|---|---|---|
| /api/verify | account, password(32位md5) | uid, username, email, realname, bbs_gid, zentao_groups(数组) | 1001 用户不存在 / 1002 密码错误 / 1003 账号禁用 / 1004 未开通本站 / 1005 已锁定 |
| /api/ticket | ticket（wy_auth 值） | 同 verify（实时授权复核） | 2001 票据无效/过期 |
| /api/password | account, password(新md5), email(可选) | 无 | 1001 用户不存在 / 1006 邮箱冲突（该字段未更新，密码已更新） |
| /api/migrate | account, password(md5), email, realname | uid | 1007 用户名已存在于中心（迁移放弃，走正常 verify 流程）；email 冲突时置空继续迁移（返回 data.email_conflict=1） |
| /api/revoke | ticket | 无 | 恒 0（幂等） |

安全细则：时间窗 ±300 秒；nonce 表一次性去重（过期清理随请求顺带 DELETE）；登录失败计数写 users.fails —— 同账号 5 次失败置 lock_until=now+900（另按来源 IP 维度限流：同 IP 60 秒内 verify 失败 >20 次拒绝）；verify 密码错误与锁定均记 audit_log；所有写操作记 audit_log。

### 票据（wy_auth）格式

```
ticket = base64url( uid . '|' . username . '|' . expire ) . '.' . hmac_sha256( 同串, ticket_key )
ticket_key 仅存中心 config.php（与 apps.secret 相互独立，不下发任何分站）
有效期 2 小时；域 .wenyinos.com
```

**分站不做本地验签**（v4 修订：分站持有 ticket_key 会引入伪造任意 uid 的能力，收益仅省一次 API 调用，故取消）——分站检测到 wy_auth cookie 即直接 POST /api/ticket，由中心统一验签 + 授权复核。中心不可达时兑换静默失败，不影响本地已登录态。

## 六、BBS 侧改动（插件 plugin/xn_sso/，零核心）

```
plugin/xn_sso/
├── conf.json
├── hook/user_login_get_post.php         # 屏蔽内置登录：login 分支最前 302 → 中心登录页
├── hook/user_create_get_post.php        # 屏蔽前台注册（账号统一由中心管理）
├── hook/index_inc_route_before.php      # 登录态继承：票据兑换（/api/ticket→upsert→建本地态）
├── hook/user_logout_start.php           # 全域登出：revoke 票据 + 清本地会话 + 302 → 中心 logout
├── hook/my_password_post_end.php        # 改密推送 /api/password（route/my.php setpw）
├── hook/model_inc_end.php               # 载入 sso.func.php
├── install.php / unstall.php            # kv 写入默认配置
├── setting.php + setting.htm            # 后台配置页（根目录，Xiuno 自动识别 plugin-setting-xn_sso.htm）
└── sso.func.php                         # 中心 API 客户端（签名/curl/超时降级）
```

（v4.1 修订：登录/退出统一定向中心——登录页直接 302 中心；退出 = revoke + 清本地 + 中心 logout 全域登出。原 user_login_post_start 的 verify+migrate 逻辑删除：登录入口已无本地窗口，休眠号迁移改走中心 legacy 凭证（见 8.3）。）

关键点：
- `index.inc.php:51` hook 位于 $uid/$user 判定后、路由分发前，本地 bbs_token 在场时不触发中心检查
- upsert 密码格式：`md5(中心md5哈希 . salt)` = BBS 原生格式；ticket 场景不带密码（新用户生成随机占位）
- upsert 时 email 为空或与现有账号冲突则跳过该字段（bbs_user.email 有唯一键，防御性处理）
- 票据兑换 hook：本地未登录 && wy_auth cookie 在场 && 该票据未被拒过 → POST /api/ticket → 授权通过则 upsert（含 bbs_gid 下发）→ 重载 $uid/$user/$gid/$group/$forumlist_show + user_token_set()；未授权/无效时按**票据值**记负缓存（同票据不重打，换新票据自动重试）
- gid 下发规则：管理类 gid<100 由中心权威覆盖；普通组（>=100）保留 BBS 发帖数等级自治
- BBS 改密（route/my.php setpw 入口）通过插件 hook 拦截推送 /api/password

## 七、禅道侧改动（ext 扩展，零核心）

```
module/user/ext/
├── config/wyauth.php                    # 中心地址/appId/secret/超时/登录登出地址 + cookie 白名单注册
├── model/wyauth.php                     # identifyByWyAuth()：/api/ticket→upsert→session+keepLogin
│                                        # + updatePassword/update 覆盖（反向同步）
├── model/hook/identify.php              # 登录注入：密码<32位时调 verify+upsert（API 等旁路兜底）
├── control/login.php                    # 覆盖 login：屏蔽内置登录页，302 → 中心（已登录则回首页）
└── control/logout.php                   # 覆盖 logout：revoke 票据 + 清会话 + 定向中心 logout 全域登出
module/common/ext/model/hook/checkPriv.php  # 一行：未登录且 wy_auth cookie → identifyByWyAuth()
```

- 注入点：`common/model.php:1112 checkPriv()`（1118-1119 已有自动登录链，同构扩展）
- identifyByWyAuth 模仿 `identifyByCookie()`（`user/model.php:696-711`）：组装 user 对象（rights/groups/visits 更新）+ session->set('user') + keepLogin
- upsert：查 zt_user 无此 account 则 insert（password=md5(明文)、role、realname、email、visits=1）+ 按 zentao_groups 数组重写 zt_usergroup；已有则更新 password（realname/email 不覆盖）
- identify hook（v4 修正触发条件）：仅当传入密码长度 **<32**（明文交互登录路径，原生逻辑 `strlen($password)<32` 走 md5 比对）且中心 verify 成功时执行 upsert，随后不 return 让原生查询命中；密码为 32 位（cookie 随机哈希）或 40 位（sha1）时是自动登录路径，不调中心
- 无 admin 白名单（v4 移除）：ruojiner 也是中心普通用户，密码跟随中心；应急降级路径 = 中心宕机时禅道本地密码验证
- 反向同步：updatePassword()/update() 覆盖中 parent:: 成功后 POST /api/password；中心不可达 3 秒超时，记日志不阻塞

## 八、账号导入实施（一次性 SQL）

### 8.1 建库建表 + 应用注册

```sql
CREATE DATABASE wenyinos_auth DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- 建表与组初始数据：见第四节完整 SQL
INSERT INTO apps (app_id, name, secret, status) VALUES
  ('forum', '论坛', '<随机64位hex>', 1),
  ('dev',   '禅道', '<随机64位hex>', 1);

-- 站点准入展开（user_app 由 user_group × groups.apps 生成，MySQL 5.7 兼容：FIND_IN_SET）
INSERT IGNORE INTO wenyinos_auth.user_app (uid, app_id)
SELECT ug.uid, a.app_id
FROM wenyinos_auth.user_group ug
JOIN wenyinos_auth.groups g ON g.id = ug.group_id
JOIN wenyinos_auth.apps a ON FIND_IN_SET(a.app_id, g.apps) > 0;
```

### 8.2 禅道 8 账号导入（密码零转换）

```sql
-- email 空串转 NULL（users.email 唯一索引允许多 NULL）；deleted 为 enum 列，必须字符串比较（= '0'，数字 0 会按枚举索引匹配导致 0 行）
INSERT INTO wenyinos_auth.users (username, password, email, realname, status, create_date)
SELECT account, password, NULLIF(email, ''), realname, 1, UNIX_TIMESTAMP()
FROM dev_wenyinos.zt_user WHERE deleted = '0';

-- 组关系映射（zentao 组 → 中心组；多组用户产生多行，与禅道 zt_usergroup 一致）
-- v4.10 起映射：1→超级管理、4/6→开发团队、11→注册用户、12→受限用户（兼容新旧禅道组值）
INSERT INTO wenyinos_auth.user_group (uid, group_id)
SELECT u.uid,
  CASE g.group WHEN 1 THEN 1 WHEN 4 THEN 2 WHEN 6 THEN 2 WHEN 11 THEN 4 WHEN 12 THEN 6 ELSE 6 END
FROM dev_wenyinos.zt_usergroup g
JOIN wenyinos_auth.users u ON u.username = g.account;
```

### 8.3 BBS 账号处理

- 同名 4 号（ruojiner/weijie/wenyinos/liuweizzuie）：已被禅道导入覆盖，无需操作（密码以禅道为准）
- 独有 6 休眠号（Windelight/lza07/pingtaisan/MeredithJeames/pingtaip/zccrs）：**BBS 登录页已屏蔽后不再有本地输入窗口**，迁移改为**中心 legacy 凭证**（v4.1 修订）：

```sql
-- 休眠号导入中心：携带原 BBS 哈希作为 legacy 凭证（主密码留空）
INSERT IGNORE INTO wenyinos_auth.users (username, password, email, realname, status, create_date, bbs_password, bbs_salt)
SELECT username, '', NULLIF(email,''), realname, 1, UNIX_TIMESTAMP(), password, salt
FROM comm_wenyinos.bbs_user
WHERE username NOT IN ('ruojiner','weijie','wenyinos','liuweizzuie');

-- 归「注册用户」组（id=4）并展开准入（勿遗漏；v4.10 起组名与禅道同步）
INSERT IGNORE INTO wenyinos_auth.user_group (uid, group_id)
SELECT uid, 4 FROM wenyinos_auth.users WHERE bbs_password IS NOT NULL;
INSERT IGNORE INTO wenyinos_auth.user_app (uid, app_id)
SELECT ug.uid, a.app_id FROM wenyinos_auth.user_group ug
JOIN wenyinos_auth.groups g ON g.id = ug.group_id
JOIN wenyinos_auth.apps a ON FIND_IN_SET(a.app_id, g.apps) > 0
WHERE ug.uid IN (SELECT uid FROM wenyinos_auth.users WHERE bbs_password IS NOT NULL);
```

用户在中心登录框输入原密码 → 中心用 `md5(输入 . bbs_salt) == bbs_password` 校验 legacy → 通过后**自动升级**（主密码 = 该 md5 值，清空 legacy 字段，记 audit legacy_upgrade）→ 此后全域正常。

## 九、安全设计

- HMAC-SHA256 签名 + 时间窗（±300s）+ nonce 一次性去重防重放
- 密码全程不出明文：中心与禅道同为 md5(明文)；BBS 传 md5(明文) 哈希、存 md5(哈希.salt)
- wy_auth cookie：HttpOnly + Secure + SameSite=Lax，域 .wenyinos.com，短有效期；ticket_key 仅存中心
- 登录失败限流：同账号 5 次锁 15 分钟（users.fails/lock_until）+ 同 IP 60 秒 20 次上限
- 各接入方 secret 独立，可单独吊销；全站 HTTPS
- BBS 前台注册关闭，新用户只在中心创建
- 降级：中心宕机 → 分站本地密码验证照常（BBS 休眠 6 号在迁移前完全不受影响）
- 已知边界：md5(明文) 弱哈希（已拍板以禅道格式为准）

## 十、实施顺序与交付物

| 步骤 | 内容 | 产出 |
|---|---|---|
| 1 | 本地容器建三库，导入两份备份，密码改写为统一测试值，生成虚拟联调用户（各组成员） | 本地联调数据 |
| 2 | 认证中心服务（第四节 SQL + 第五节目录结构全部文件） | auth/ 目录 |
| 3 | 禅道 ext 扩展（第七节） | module/*/ext/ 文件 |
| 4 | BBS xn_sso 插件（第六节） | plugin/xn_sso/ |
| 5 | 三站联调，跑完第十一节验收清单 | 验收记录 |
| 6 | 服务器上线：auth/ 上传主站根（.gitignore 排除）、密钥生成、正式库导入 SQL、两站扩展部署 | 生产部署 |

交付物汇总：
- `auth/` 认证中心（登录页 + 管理后台 + 5 个 API 端点，约 1500 行 PHP）
- 禅道 ext 扩展 5 个文件（约 300 行）
- BBS 插件 xn_sso（约 400 行）
- 导入 SQL 脚本（建库建表 + 账号导入 + 准入展开）
- 主站 index.html 加"登录"入口链接（指向 /auth，随镜像仓库发布）

## 十一、验收清单

- [ ] 禅道 8 账号导入中心后，原密码可登录禅道与 BBS（ruojiner 用禅道密码）
- [ ] 中心创建用户仅授权 forum → 禅道登录提示未开通；仅授权 dev → BBS 同理
- [ ] BBS 休眠账号（如 zccrs）用 BBS 原密码登录 BBS 成功，且登录后中心出现该账号（无感迁移）
- [ ] 迁移后的 BBS 账号在禅道用同一密码可登录（upsert 生效）
- [ ] 主站 /auth 登录 → 切 forum / dev 均已登录（首次各一次 API 兑换）
- [ ] 分站登出后票据已销毁，切站不再自动登录
- [ ] 禅道改密 → 中心已更新 → BBS 用新密码可登录；BBS 改密反向同理
- [ ] 签名错误/过期/重放请求被拒；连续错误密码触发锁定
- [ ] 停中心服务 → 各站已登录用户不受影响，本地密码验证可用
- [ ] 权限组验证：core 组用户 upsert 后禅道归组4、BBS gid=101

## 十二、本地联调环境（已备）

- Podman：mysql:5.7 容器（已验证 5.7.44 + utf8mb4 + 宽松 sql_mode）+ php:8.5-fpm 镜像（已构建，含 mysqli/pdo_mysql/gd/zip/opcache）+ nginx:alpine 配置三 vhost
- 环境：/home/zemi/MyDev/wenyinos-env/（env.sh 管理脚本、三域名 *.wenyinos.test hosts 映射 127.0.0.1:8080、自检页已就位）
- 本地联调特殊配置：wy_auth cookie 域 .wenyinos.test；**secure_cookie=0**（本地 http，Secure 标记会被浏览器丢弃，上线改 1）；各端点 URL 由配置项指向本地域名
- 联调数据策略：导入备份后将全部 password 改写为 md5('test123456')，另生成虚拟用户（各中心组至少 1 名）覆盖验收场景；本地库名与线上一致（comm_wenyinos / dev_wenyinos / wenyinos_auth），第八节 SQL 原样可用
- 服务器对齐：MySQL 5.7.44（与备份导出版本一致）/ PHP 8.5 / Nginx(Tengine)

## 十三、风险与边界（定稿口径）

- md5(明文) 弱哈希为既定决策；暴露面经 HMAC 签名/时间窗/nonce/限流/HTTPS 收窄
- 主站镜像仓库公开（GitHub），auth/ 含密钥必须排除在仓库外（.gitignore + 服务器手动部署）
- weijie 与 wenyinos 为同一人两号（密码哈希相同），中心各自保留为独立账号，不做合并
- BBS url_rewrite_on=0（?sso-xxx.htm 形式），插件路由按此格式注册
- 禅道 requestType=PATH_INFO（服务器现状），本地 nginx 已配 PATH_INFO 透传
- 票据兑换依赖 .wenyinos.com 跨子域 cookie；若浏览器拦截第三方 cookie 策略变化（SameSite=Lax 顶级导航携带，当前兼容），后续如失效可升级为跳转式兑换

## 十四、v4 修订对照表（v3 → v4）

| # | 级别 | 修订内容 | 落点 |
|---|---|---|---|
| 1 | 阻断 | users.email 改 NULL DEFAULT NULL，导入用 NULLIF(email,'')——避免 public1-3 空串撞唯一键 | 第四节/8.2 |
| 2 | 阻断 | apps_json 改逗号串 apps 字段 + FIND_IN_SET，弃 JSON_TABLE（MySQL 5.7 不支持） | 第四节/8.1 |
| 3 | 阻断 | 禅道 identify hook 触发条件改为"密码长度 <32"（32/40 为自动登录路径不调中心） | 第七节 |
| 4 | 阻断 | zentao_groups 数组下发（upsert 重写 zt_usergroup 多行，保留 ruojiner 组1+组4）；bbs_gid 按组 sort 最小值归并单值 | 第五节/4.1 规则 |
| 5 | 缺口 | users 增 fails/lock_until 列，限流落地到表结构 | 第四节 |
| 6 | 缺口 | 禅道补 logout 覆盖调 /api/revoke | 第七节 |
| 7 | 缺口 | BBS 前台注册 user-create 由插件关闭 | 第六节 |
| 8 | 缺口 | ticket_key 不下发分站；分站不做本地验签，统一走 /api/ticket | 第五节票据格式 |
| 9 | 缺口 | /api/migrate email 冲突时置空继续（data.email_conflict=1） | 第五节 API 表 |
| 10 | 缺口 | BBS upsert email 冲突跳过该字段 | 第六节 |
| 11 | 缺口 | 本地 http 联调 secure_cookie=0 配置项 | 第十二节 |
| 12 | 缺口 | BBS 插件结构调整：setting 置插件根目录、删前台路由注册 hook | 第六节 |
| 13 | 缺口 | 票据未授权负缓存（$_SESSION['wy_sso_denied']） | 第六节 |

v4 附带优化：移除 ruojiner admin 白名单（密码跟随中心，防脱钩）；本地库名与线上一致；audit_log 加 date 索引；verify 限流补 IP 维度。

**实施中发现的补充修订（2026-10-07 导库实测）**：
- 8.2 导入 SQL 的 `WHERE deleted = 0` 修正为 `deleted = '0'`——zt_user.deleted 是 enum('0','1')，enum 与数字比较按枚举索引匹配（0 永远不命中），原写法导入 0 行
- 备份库中的 `view_datasource_*` / `ztv_*` 视图为禅道 BI 数据源功能残留（引用新版禅道才有的 zt_task.vision 等列，线上导出时即标注 failed），导入需 `--force` 跳过，不影响表与数据

## 十五、实施记录（2026-10-07 完成）

### 15.1 实施与验收状态：全部完成

三端已在本地 podman 环境（MySQL 5.7.44 / PHP 8.5 / nginx）落地并联调通过，10 项验收清单全过：

| # | 验收项 | 实测结果 |
|---|---|---|
| 1 | 禅道 8 账号导入后原密码登录禅道与 BBS | ✓ ruojiner/test123456 双端登录 |
| 2 | 仅授权 forum → 禅道拒；仅授权 dev → BBS 拒 | ✓ vtest_forum 禅道拒（1004）；public1 BBS 拒 |
| 3 | BBS 休眠号原密码登录 + 无感迁移 | ✓ zccrs、pingtaip（中心出现 forum_only 记录，audit user_migrate） |
| 4 | 权限联动：后台改组 → 分站实时生效 | ✓ pingtaip 入 core → 禅道登录成功且组重写为[4]；移回 forum_only → 再登录被拒 |
| 5 | 主站 /auth 登录 → forum/dev 均已登录 | ✓ 票据兑换（ticket_ok）双站自动登录 |
| 6 | 分站登出后票据销毁、切站不自动登录 | ✓ BBS/禅道登出均写 revoked 黑名单，再兑换被拒 |
| 7 | 双向改密闭环 | ✓ 禅道改密→BBS 新密码登录成功；BBS 改密→中心同步→禅道新密码登录成功 |
| 8 | 签名/过期/重放拒绝 + 锁定 | ✓ api-test.py 20/20（含 2101/2105/1005/1008） |
| 9 | 中心宕机降级 + 已登录不受影响 | ✓ 黑洞地址模拟不可达：双端本地密码降级登录成功，已登录会话正常 |
| 10 | 组映射下发（core → 禅道组4、BBS gid=101） | ✓ ruojiner 禅道组保持[1,4]，weijie 组[4] |

配套测试工具：`/home/zemi/MyDev/wenyinos-env/sql/api-test.py`（中心 API 全场景自动化，20 断言）、`setup-local.sql`（本地建库/导入/虚拟用户）。

### 15.2 实施中修复的 8 个关键坑（部署必读）

| # | 端 | 坑 | 处理 |
|---|---|---|---|
| 1 | 禅道 | `extensionLevel` 默认 0（扩展机制关闭），ext 全部不加载 | my.php 增加 `$config->framework->extensionLevel = 1;` |
| 2 | 禅道 | `validater::filterParam` 在 loadModule 阶段删除不在白名单的 cookie（wy_auth 被清，登出 revoke 拿不到票据） | ext/config 注册 `$filter->rules->wyticket` + `$filter->default->cookie['wy_auth']` |
| 3 | 禅道 | ext config 由方法内 include，直接访问 `$filter` 是 null（Fatal） | 文件头 `global $config, $filter;` |
| 4 | 禅道 | `empty($this->cookie->xx)` 恒真（super 魔术对象无 __isset，empty 不触发 __get） | 用直接判断（与禅道原生 `$this->cookie->za` 写法一致） |
| 5 | BBS | hook 文件是纯代码片段，不能带 `<?php` 标签（编译后语法错误） | 全部 hook 文件去标签 |
| 6 | BBS | 直接放入插件目录后编译缓存不刷新 | 清 `tmp/` 编译缓存（后台正常安装/启用流程会自动清） |
| 7 | BBS | 改密 hook 处 `$password_new` 已被原生改写为存库哈希，需从 POST 重取原始 md5(明文) | hook 内 `param('password_new')` 重取 |
| 8 | 中心 | 签名若含自身则循环依赖 | sign 走 HTTP 头，body 只放 action+data，签名覆盖完整 body 原文 |

另：禅道 gid 下发保留 BBS 等级自治（仅管理类 gid<100 覆盖）；两端 upsert 对已存在用户不覆盖 email/realname（仅密码同步）。

### 15.2.1 上线联调追加修复（同日晚间三次）

| # | 端 | 现象 | 根因与处理 |
|---|---|---|---|
| 9 | BBS | 中心登录后点「论坛」未登录（禅道正常） | tmp/ 编译缓存被 git 跟踪的旧版覆盖（无插件 hook），首页票据兑换失效。处理：删除过期缓存（index.inc.php、model.inc.php）重建。**注意：xiuno-bbs 仓库跟踪了 tmp/ 编译缓存属历史包袱，建议后续 `git rm -r --cached tmp` + .gitignore**；手动改 hook 后必须手动清对应缓存（后台启用/停用插件会自动清） |
| 10 | BBS/禅道 | 用户登出后重新登录中心，分站仍不自动登录 | 负缓存原按"布尔"记录，同一 session 换新票据（重新登录）后不再重试。处理：改为按**票据值**记录（`wy_sso_denied_ticket`），新票据自动重试、同票据不重复请求 |
| 11 | 禅道 | 页面顶部显示 `Deprecated: Constant E_STRICT is deprecated since 8.4` | `framework/base/router.class.php:603` 的 setDebug() 设置 `error_reporting(E_ALL & ~E_STRICT)`，PHP 8.4+ 中 E_STRICT 常量本身弃用；仅 `debug=true` 时触发。处理：本地 my.php `debug=false`（与服务器一致）。服务器若开 debug 需注意此兼容问题 |
| 12 | 本地环境 | 重建 php 容器后禅道登录态丢失 | 禅道使用 PHP 文件会话，默认存容器内临时目录，容器重建即丢（BBS 用 DB 会话不受影响）。处理：dev.ini `session.save_path = /var/www/dev/tmp/php-sessions`（挂载目录，容器重建不丢），已端到端验证 |

### 15.5 登录与退出统一定向（v4.1 追加，2026-10-07）

| 动作 | BBS | 禅道 |
|---|---|---|
| 登录页 | `user_login_get_post` hook 最前 302 → 中心登录页 | `ext/control/login.php` 覆盖 → 302 中心（已登录用户访问则回首页） |
| 退出 | `user_logout_start`：revoke 票据 + 清本地会话/token + 302 → 中心 logout（清 wy_auth cookie 后回登录页） | `ext/control/logout.php`：原生清理 + revoke + 定向中心 logout |
| 注册 | 保持拦截（提示走中心） | 无注册入口（原生） |

**休眠号迁移机制升级（替代原无感迁移）**：BBS 登录页屏蔽后本地无输入窗口，改为**中心 legacy 凭证**——休眠号哈希（md5(md5(明文).salt)）随账号一次性导入中心 `bbs_password/bbs_salt` 列；用户在中心登录框输入原密码时，中心按 legacy 规则校验 `md5(输入 . bbs_salt)`，通过即**自动升级**为主密码（清空 legacy 字段，audit 记录 legacy_upgrade），此后全域正常。已实测 Windelight 升级成功。
（/api/migrate 端点保留在中心代码中作兼容，BBS 侧调用已移除。）

新增配置项：BBS 插件设置页 `login_url / logout_url`；禅道 `config/wyauth.php` 的 `loginUrl / logoutUrl`。上线时均指向 `https://wenyinos.com/auth/...`。

实测结论：BBS 登录页 302 中心 ✓；禅道登录页 302 中心 ✓；两端退出均 302 中心 logout 且 wy_auth 清除、票据入黑名单 ✓；登出后回访站点为游客态 ✓；legacy 初始化 + 升级链路 ✓。

### 15.6 统一认证总开关（v4.2 追加）

两端均提供"一键停用统一认证、恢复原生"的开关，**原生认证能力完整保留**（BBS 全 hook 注入零核心改动；禅道登录拦截为前置 302、原生 login/logout 代码原样保留）：

| 端 | 开关位置 | 停用效果 |
|---|---|---|
| BBS | 后台 → 插件 → 统一认证 → 设置 → 「统一认证开关」（kv `xn_sso.enabled`） | 登录页/注册恢复原生；退出恢复原生；票据不再兑换 |
| 禅道 | `module/user/ext/.env` 的 `WY_SSO_ENABLED=false` | 登录页恢复原生表单；退出跳回原生登录页；本地密码验证；票据不再兑换 |

两端开关均即时生效（BBS 改后台、禅道改一行 .env），应变场景：中心故障 / 需要临时脱离统一认证。
实测：停用后两端原生登录功能可用（BBS 原生表单登录成功、禅道原生登录成功）；重新启用后全部回归（302 定向、票据兑换、全域退出）。
两端组件 README 已注明开关用法：`plugin/xn_sso/README.md`、`module/user/ext/README.md`。

### 15.7 密钥 .env 化（v4.3 追加，全量开源）

**认证密钥等敏感信息全部抽离到 `.env`，三处代码可全量提交至 GitHub 开源**：

| 位置 | 敏感值载体 | 模板（入库） | 实际值（不入库） |
|---|---|---|---|
| 认证中心 auth/ | `auth/config.php` 读取 | `auth/.env.example` | `auth/.env`（gitignore） |
| 禅道扩展 | `module/user/ext/config/wyauth.php` 读取 | `module/user/ext/.env.example` | `module/user/ext/.env`（gitignore） |
| BBS 插件 | 设置页写数据库 kv | —（无需 .env） | 存于 DB，不涉及代码 |

主站 `.gitignore` 由"整目录排除 auth/"调整为仅排除 `auth/.env`；禅道 `.gitignore` 追加 `module/user/ext/.env`。
.env 解析为无依赖的轻量实现（约 15 行），缺省时回退本地联调默认值。
实测：中心（登录页 + API verify）、禅道（302 定向 / 票据兑换 / 全域退出 / 开关 .env 化往返）全部回归通过。

### 15.3 交付物清单（已就位）

```
主站仓库：wenyin-community.github.io/auth/   （11 个 PHP 文件，已 gitignore；服务器手动部署时随站点上传）
BBS：    xiuno-bbs/plugin/xn_sso/            （12 个文件：6 hook + 函数库 + 设置页 + 安装脚本）
禅道：    zentaopms_983/module/user/ext/     （config/model/hook/control 5 文件）
         zentaopms_983/module/common/ext/    （checkPriv hook）
本地环境：wenyinos-env/（env.sh、sql/setup-local.sql、sql/api-test.py、.local-secrets）
```

### 15.4 服务器部署（v4.9.2 修订：全量源码覆盖策略）

**一、源码部署 = 全量覆盖 + 排除清单**（三站各自排除，覆盖将造成配置/密钥/数据事故）：

| 站点 | 必须排除 | 原因 |
|---|---|---|
| 禅道 | `config/my.php` | 生产配置（db / extensionLevel=1 / changeWeak 等）——指定不覆盖 |
| 禅道 | `module/user/ext/.env` | 生产 SSO 密钥（本地值覆盖 → SSO 全挂） |
| 禅道 | `tmp/`、`www/data/` | 运行时缓存 / 用户上传与数据 |
| BBS | `conf/conf.php`、`conf/smtp.conf.php` | 生产 db 与 SMTP 配置 |
| BBS | `tmp/`、`log/`、`upload/` | 运行时编译缓存与日志 / 用户附件 |
| 主站 | `auth/.env`、`.git/` | 生产密钥 / 仓库元数据 |

rsync 参考（首轮建议不带 `--delete`，确认无恙后再加）：
```bash
rsync -av --exclude 'config/my.php' --exclude 'module/user/ext/.env' --exclude 'tmp/' --exclude 'www/data/'  本地zentaopms/ 服务器:/禅道目录/
rsync -av --exclude 'conf/conf.php' --exclude 'conf/smtp.conf.php' --exclude 'tmp/' --exclude 'log/' --exclude 'upload/'  本地xiuno-bbs/ 服务器:/论坛目录/
rsync -av --exclude 'auth/.env' --exclude '.git/'  本地wenyin-community.github.io/ 服务器:/主站目录/
```

**二、部署后必做**
1. **清一次生产 BBS 的 `tmp/`**（编译缓存须按新代码重建，否则插件 hook 不生效；之后后台启用/停用插件也会自动清）
2. 服务器若开 opcache：`reload` php-fpm（三站）
3. 禅道 `config/my.php` 确认 `extensionLevel = 1`（未被覆盖则天然保留）

**三、数据部署（服务器操作；v4.10 追加权限组模型）**

前置事实（已确认）：生产两站库与开发基线**同源且备份后无新数据**；SSO 接入**未改动两站任何表结构** → 两站库**无需数据迁移**（保留原样即正确；生产密码从未被改动，无需"还原"）。

1. 生产 MySQL 执行 `wenyinos-env/sql/production-init-ready.sql`（建中心库建表建组；密钥已由 `make-production.sh` 注入）
2. **同实例导入账号**（生产 MySQL 直接执行；库名与本方案 SQL 一致，无需改）：
   - 第八节 8.2：禅道账号导入 + 组关系映射（`WHERE deleted = '0'`）
   - 第八节 8.3：BBS 休眠号 legacy 凭证导入 + 组 4 与准入展开
   - 站点准入展开（8.1 的 FIND_IN_SET 语句）
3. **权限组模型落地**（见 15.15）：
   - 禅道库执行 `wenyinos-env/sql/production-zentao-groups.sql`（组 4=开发 权限收紧；组 11=访客只读；组 6/13 与 public 账号清除）
   - 中心库执行 `wenyinos-env/sql/production-auth-groups.sql`（**须在账号导入之后**执行：组名与禅道同名同步（超级管理/开发团队/注册用户/受限用户）、member 并入开发团队、public 清除、pingtaip/zemin 组对齐、站点准入全量重算）
4. 禅道侧：配置全在 `.env` 文件（无其他库操作）
5. BBS 侧：后台启用插件 → 设置页填写（推荐，避免手拼 JSON）：中心 API/登录/登出地址 + app_id + secret（与中心 apps 表一致）

> `deploy-prepare.sh`（本地还原+导出）**本期不使用**：生产无回滚需求、两站库无需迁移；该脚本仅当未来需要"以本地库为基线另建部署"时备用。
> 操作前建议对生产库做一次全量备份（安全习惯）。

**四、文件级配置（不随库迁移，生产单独创建）**
- 中心：`auth/.env`（复制 `.env.example`；`WY_TICKET_KEY=openssl rand -hex 32`、cookie 域 `.wenyinos.com` + `secure=true`、站点 HTTPS 域名）
- 禅道：`module/user/ext/.env`（复制 `.env.example`；填生产 apiUrl/appId/secret）
- 密钥一致性：中心 apps 表 secret ↔ 禅道 .env / BBS 后台设置；生产导入后建议在中心后台各轮换一次

**五、验收**：跑一轮第十一节验收清单（重点：跨域名 cookie 需 HTTPS）。



### 15.8 自助注册与找回密码（v4.4 追加；v4.5 修订验证机制）

认证中心新增两套自助机制，均配套后台配置：

**① 自助注册**（`register.php`，登录页有「注册新账号」入口）
- 后台「系统设置」页控制：注册开关（开放/关闭）+ 新用户默认组（下拉选组，初始默认「注册用户」）+ **人机验证（Cloudflare Turnstile，仅注册页）** + **SMTP 认证开关**
- 校验：用户名规则 / 邮箱合法且唯一 / 密码≥6 双确认 / Turnstile 人机验证（配置两项密钥后生效，清空 Site Key 即关闭；本地联调可用官方测试密钥）
- 防刷：同 IP 每小时注册上限 3 个
- **注册后为「待验证」状态，验证通过前不可登录**（中心页与 API 双通道拦截，错误码 1009；超管豁免，users.verified 列，存量/管理创建/导入用户默认已验证）
- **两种验证通道**（后台 SMTP 认证开关切换）：
  - SMTP 认证**开启**：注册后自动发送验证邮件（24 小时一次性链接 `verify.php`），点击即通过
  - SMTP 认证**关闭**：不发邮件，由管理员在后台「用户管理」点击「**通过验证**」按钮一键放行（用户列表带验证状态列；找回密码成功也顺带视为邮箱已验证）
- BBS 注册拦截文案同步更新为「引导至中心注册页」链接

**② 找回密码**（`forgot.php` + `reset.php`，登录页有「忘记密码？」入口）
- 流程：输入注册邮箱 → 发重置邮件（一次性 token，30 分钟有效）→ 点链接设置新密码
- 防枚举：无论邮箱是否存在均统一提示；同账号 60 秒 / 同 IP 10 分钟 5 封限速
- token 一次性（使用后立即失效）；重置后清空锁定与失败计数，并视为邮箱已验证
- legacy 用户（BBS 休眠号）同机制可用

**SMTP 配置**：后台「系统设置」页维护（服务器/端口/加密 SSL·STARTTLS·none/账号/密码/发件人），带「发送测试邮件」按钮；配置存 `settings` 表。实现为无依赖轻量 SMTP 客户端（支持 AUTH LOGIN）。密码留空保存表示不修改。

**base_url**：新增 `WY_BASE_URL` 配置（.env），用于邮件中重置/验证链接等绝对地址（避免回环 API 访问时 $_SERVER 不可信）；未配置时回退自动检测。

**实测**（本地假 SMTP 捕获 + CF 测试密钥）：注册（待验证状态 + 重复拒绝 + 开关关闭生效 + Turnstile widget 渲染与无 token 拒绝）✓；管理员一键通过 → 登录成功 ✓；SMTP 认证开启 → 注册发信 → 验证链接 → 自动通过 → 登录成功 ✓；未验证拦截（中心页面提示 + API 1009）✓；Turnstile 服务端 siteverify 真实调用链路 ✓；找回/重置（token 一次性）✓；中心登录页 legacy 升级 ✓。（排障记录：verify token 带前缀后 71 字符超出 reset_token CHAR(64) 被截断——已扩至 CHAR(80)）

### 15.9 账号设置（自助修改资料，v4.6 追加）

账号面板新增「账号设置」入口（`profile.php`），三张卡片自助维护：

| 功能 | 流程 | 要点 |
|---|---|---|
| 改昵称 | 直接保存 | 最长 60 字符；仅改中心侧（分站 realname 不覆盖策略不变） |
| 改密码 | 原密码校验 → 新密码≥6 双确认 | 修改后各分站下次登录时经中心 verify 自动同步；不强制登出当前会话 |
| 改邮箱 | **新邮箱输入 → 点「发送验证码」→ 查收邮件 → 填入验证码 → 「认证并保存邮箱」** | 验证码 6 位、10 分钟有效、一次性、须与新邮箱匹配；发送限速（会话 60 秒 + uid 10 分钟 5 次）；保存前二次查占用（防竞态）；保存成功即视为邮箱已验证(verified=1) |

配套：`send_email_code.php`（AJAX 发码端点，需登录 + CSRF，发信失败自动撤销会话验证码）；验证码邮件为品牌 HTML 模板（`wy_code_mail_html`）；全部成功操作写 audit（profile_nickname / profile_password / profile_email）。
采用 PRG 模式（POST 成功后 302 + saved 参数回显成功提示）。

**实测**：昵称修改落库 ✓；密码（错原密码拒 / 新密码登录 / 往返改回）✓；邮箱（发码邮件捕获 / 错码拒 / 正确码保存 + verified=1 + 落库）✓；端到端回归（中心登录 → 双站自动通行）✓。

### 15.10 用户资料同步矩阵（v4.7 核实与补齐）

应核实要求，对"分站用户级设置页修改资料能否同步中心"做了代码梳理 + 实测，并补齐两处缺口：

**核实结论（修复后最终矩阵）**：

| 修改位置（用户级，非管理后台） | 可改字段 | 同步链路 | 状态 |
|---|---|---|---|
| 禅道 my-editProfile | 昵称(realname) | 禅道→中心（update 覆盖推送 realname） | ✓ 实测 |
| 禅道 my-editProfile | 邮箱 | 禅道→中心（原有，实测确认） | ✓ |
| 禅道 my-changePassword | 密码 | 禅道→中心（updatePassword 覆盖推送） | ✓ |
| BBS my-password | 密码 | BBS→中心（my_password_post_end hook） | ✓ |
| BBS 前台 | 昵称/邮箱 | **BBS 上游无资料编辑页**（my-profile action 被注释） | N/A |
| 中心 profile.php | 昵称/邮箱/密码 | 中心→分站（分站下次登录/票据兑换时 upsert 回传） | ✓ 实测 |

**本次补齐的两处缺口**：
1. **分站→中心**：禅道 update 覆盖原只推 email，补推 `realname`；中心 /api/password 端点增加 realname 字段处理（mb_substr 60 截断）
2. **中心→分站**：原 upsert 策略"已存在用户不覆盖 email/realname"，与"中心统一管理资料"目标相悖——修订为**中心权威回传**：禅道 upsert 更新本地 realname/email；BBS upsert 更新（realname 按列宽截 16 字符、email 查重防撞唯一键）

**实测**：禅道 my-editProfile 改昵称+邮箱 → 中心即时同步 ✓；中心改昵称 → 禅道/BBS 票据兑换后三处一致（含恢复流程二次验证）✓。verifyPassword 校验（md5(库内密码md5 + session rand)）在实测脚本中已还原。

同步语义（定稿）：**密码/昵称/邮箱三项以中心为聚合权威，双向最终一致**；分站本地修改经"资料保存/改密"推送中心，中心修改经"登录/票据兑换 upsert"下发分站。

### 15.11 基本资料同步扩展（v4.8 追加）

对两站数据库用户表做了全字段盘点，按"跨站通用身份字段"标准取舍，新增 4 个字段进中心统一管理：

**全字段取舍清单**：

| 字段 | 取舍 | 理由 |
|---|---|---|
| mobile 手机号 / qq | **纳入中心** | 两端均有列承接，跨站通用联系方式 |
| gender 性别 / birthday 生日 | **纳入中心** | 通用个人资料；禅道有列承接，BBS 无此列（自动跳过） |
| 积分/金币/发帖数/visits/score 等 | 不同步 | 站点行为统计，各站本地数据 |
| dept/role/nickname | 不同步 | 禅道组织与特有字段（角色已按"组映射"策略处理） |
| 各 IM 字段（skype/wechat/dingtalk/slack/whatsapp/wangwang 等） | 不同步 | 平台专有字段，禅道本地管理 |
| address/zipcode/phone | 不同步 | 通讯录明细，禅道本地 |
| avatar 头像 | 不同步 | 两端文件存储机制不同，跨站同步成本高、收益低 |
| idnumber 证件号 / password_sms | 不同步 | 敏感 / BBS 遗留未用字段 |
| fails/locked/deleted/status | 不同步 | 各站本地防护与状态（中心 status 禁用已走 1003 拦截） |

**实现**：中心 users 表加 mobile/qq/gender/birthday 列；账号设置页新增「基本资料」卡片（手机号/QQ/性别/生日，格式校验）；`/api/password` 支持四字段（isset 语义，允许清空）；payload 下发 + 禅道 upsert 回传（gender 'u' 不下发、空值不覆盖）+ BBS upsert 回传 mobile/qq（列宽截断）；禅道 update 覆盖推送四字段（birthday '0000-00-00' 转空）。

**实测**：中心改四项 → 禅道全部回传 ✓（BBS mobile/qq ✓）；禅道改手机号 → 中心同步 ✓（含清空场景）；三处数据已还原。

### 15.12 个人资料修改统一定向（v4.9 追加）

**决策**：为避免"两端都能改、同步有延迟"造成的用户误解，在统一认证模式下**屏蔽两站的用户级资料修改入口，全部定向中心账号设置页**：

| 入口 | 处理 |
|---|---|
| 禅道 my-editProfile（修改档案） | checkPriv hook 前置 302 → 中心 profile.php |
| 禅道 my-changePassword（修改密码） | 同上 |
| BBS my-password（改密） | 新增 `hook/my_start.php` 前置 302 → 中心 profile.php |

**边界与说明**：
- 服务端同步链（分站推送/登录回传）**保留**——防御性设计，其他路径的改动仍会同步
- 禅道「修改档案」中不归中心管的本地字段（通讯地址、邮编、IM 账号等）暂时失去编辑入口（低频可接受；未来需要时在中心增列）
- 全程挂**总开关**：停用时三处入口全部恢复原生（已实测往返：启用=302 中心 / 停用=200 原生页面）

**实测**：启用态禅道 editProfile/changePassword、BBS my-password（已登录会话）均 302 中心 profile；停用态均恢复 200 原生；恢复启用后再次 302。

### 15.13 禅道 PHP 8.5 兼容修复（权限维护报错，v4.9.1）

**现象**：生产环境点击「组织 → 权限维护」报 `Fatal error: TypeError: join(): Argument #2 ($array) must be of type ?array, string given in module/group/control.php:189`。

**根因**：PHP 8.0 移除了 `join()`/`implode()` 的旧参数顺序 `join($array, $glue)`，代码仍按旧签名调用（PHP 7.4 起弃用未被兼容改造覆盖）。

**修复（5 处同类，本地复现后修复并验证）**：
- `module/group/control.php:189,193`（权限维护页）：`join($arr, ',')` → `join(',', $arr)`
- `module/extension/view/deactivate.html.php:24`、`erase.html.php:27`、`uninstall.html.php:52`（插件停用/擦除/卸载确认页）：`join($removeCommands, '<br />')` → `join('<br />', $removeCommands)`

**验证**：本地复现同报错 → 修复后权限维护页 200 正常渲染；全库 grep 复查无其他旧签名残留；lint 通过。
**部署**：上述 4 个文件拷至生产 `/www/wwwroot/dev.wenyinos/` 对应路径即可；若服务器 PHP 开启 opcache 需 reload php-fpm。

### 15.14 禅道入职日期 = 中心注册日期（v4.9.3 追加）

**规则**：通过认证中心注册的用户，首次在禅道建立账号时，`zt_user.join`（入职日期）写入其在中心的注册日期（`users.create_date`）。

**实现**：
- 中心 `wy_user_payload` 下发 `create_date`（int 时间戳）
- 禅道 `wyauthUpsert` 新建号分支：`join = date('Y-m-d', $payload['create_date'])`（缺省保持 `0000-00-00`）

**边界**：仅影响禅道本地不存在的新建号（中心注册新用户、BBS 休眠号迁移）；导入的历史账号与禅道既有账号的入职日期不被覆盖（update 分支不写 join）。

**实测**：模拟注册用户（中心 create_date = 2026-09-28 10:00）→ 禅道首次登录建号 `join=2026-09-28` ✓（测试数据已清理）。

### 15.15 禅道权限组模型重构（v4.10 追加，2026-10-08）

**需求**：①新注册用户默认能访问禅道（只读）②开发组：可创建/删除/修改内容 ③管理员组：完整权限 ④public 系列账号彻底清除；要求以禅道内置组/权限机制实现，数据库直接导入。

**关键实测（内置机制的边界，决定设计）**：禅道 9.8.3 内置「受限操作」（my-limited，界面文案"只能编辑与自己相关的内容"）实测结论：

- 受限用户被硬性禁止一切 `create*/batch*/link*/import*` 方法（`commonModel::hasDBPriv` 方法名前缀拦截）→ **与"可创建"不可兼得**
- 9.8.3 的对象归属检查未接入常规操作路径（`hasDBPriv` 全库仅 project 视图一处调用）→ 实测受限用户可直接打开**他人**任务的编辑页（HTTP 200）

→ 结论："可创建 + 不可改他人"组合无法以内置机制达成；开发组采用常规权限（全部修改行为留痕 zt_action，可审计追溯到人），如需强拦"改他人"须 ext 定制（未实施，待决策）。

**最终组模型（禅道库 zt_group / zt_grouppriv；组名与认证中心同名同步，均 4 个中文字）**：

| 组 | 名称 | 权限 | 用户 |
|---|---|---|---|
| 1 | 超级管理 | 406 项完整 | ruojiner（经 zt_company.admins 超管名单全权） |
| 4 | 开发团队 | 207 项（见下） | 除 ruojiner 外全部原有用户（weijie/narukeu/liuweizzuie/wenyinos/pingtaip/zemin） |
| 11 | 注册用户 | 91 项只读（无任何 create/edit/delete） | 新注册用户默认（经中心同名组下发）；亦为未登录访问（游客）权限源 |
| 12 | 受限用户 | 1 项 my-limited | 保留（中心同名组映射） |

**开发组权限（组 4：原 214 项 − 29 + 22 = 207）**：

- 去除 29 项管理/高危：回收站管理（action trash/undelete/hideOne）、模块管理（branch manage/sort）、公司资料编辑、**在线代码编辑器（editor，5 项）**、项目生命周期（project delete/close/start/suspend/activate/putoff/fixFirst）、产品删除/关闭、发版删除/状态变更、构建删除/移除关联、文档库管理、附件公开
- 补充 22 项：productplan 套件（9）、story 批量操作（3）、user 查看套件（10，原组 6 独有）

**成员与账号调整**：组 6（参与项目）删除、成员并入组 4；组 13（公用账号）与 public1/2/3 账号删除（zt_action 历史记录保留）。

**中心映射（中心库 groups；组名与禅道同名同步，均 4 个中文字）**：

| 中心组（id） | → 禅道组 | 准入 / 映射 |
|---|---|---|
| 超级管理（1） | 超级管理（1） | forum+dev；BBS gid=1 |
| 开发团队（2） | 开发团队（4） | forum+dev；BBS gid=101（原 core+member 合并） |
| 注册用户（4） | 注册用户（11） | forum+dev（禅道只读）；BBS gid=101；新注册默认组 |
| 受限用户（6） | 受限用户（12） | 无站点准入 |

**组对齐**：pingtaip（中心归入开发团队）、zemin（移除历史附加组）——禅道原有用户全部对齐开发团队；历史组 member/public 并入/清除。

**交付 SQL**（生产直接导入，幂等可重跑）：
- `wenyinos-env/sql/production-zentao-groups.sql`（禅道库执行）
- `wenyinos-env/sql/production-auth-groups.sql`（中心库执行，须在账号导入之后）

**验证（本地同源实测）**：
- 注册用户（组 11）：任务查看 200；建任务 / 编辑任务 → deny 跳转；我的地盘 200；SSO 登录建号 zt_usergroup=[11]
- 开发团队（组 4）：建任务 200；编辑他人任务 200（见上述边界）；公司资料编辑 / 在线代码编辑器 → deny；产品计划浏览 200
- 组重写一致性：SSO 登录兑换后 zt_usergroup 与中心映射一致（开发团队 [4]、注册用户 [11]），与手动 SQL 结果相同（中心权威 upsert 与导入 SQL 双保险）
- **准入物化重算**（v4.10.2 修复）：user_app 为 user_group × groups.apps 的物化展开——改组 apps 或调整成员后须全量重算（`production-auth-groups.sql` 第 5 节），否则存量用户（休眠号等）准入陈旧（注册用户组加 dev 准入后未重算会导致禅道侧被 1004 拒绝、静默落游客态）

**未登录访问（游客）机制与组名中文化的衔接（v4.10.1 补充）**：

禅道内置"游客"机制：`zt_company.guest=1`（当前生产/本地已为 1）时，未登录访问以运行时构造的 `guest` 身份浏览（common/model.php `setUser()`），其权限从**组名 'guest' 的组**读取（user/model.php `authorize('guest')` 按 name 查找）。注意：`guest` 不是 zt_user 表里的账号（表内无此行，且用户名 guest 禁止注册/创建），**不存在账号与组冲突**——两者是"权限绑定"关系。

组 11 更名「注册用户」后，按组名查找会失效，由 `module/user/ext/model/wyauth.php` 增加的 `authorize` 覆盖承接：`guest` 分支改为按**固定组 ID 11** 拉权限，其余账号完全走原生逻辑。效果：直接发送禅道链接，未登录用户即可只读浏览（任务/需求/文档等 91 项 view），发帖/修改一律拒绝，点"登录"则 302 至认证中心。

**部署顺序约束**：先源码全量覆盖（含上述 ext 覆盖），后执行组模型 SQL——旧代码按组名查找，若先改名会出现"游客无权限"窗口期；反向（先源码后 SQL）无缝。

**受限用户（无站点准入）前台表现（v4.10.4 起：定向回中心）**：

行为规格：**持中心票据访问分站任意页面 → 302 定向回认证中心面板**（看到"未开通"提示）；**在中心退出登录后，恢复游客浏览**。

| 位置 | 表现 |
|---|---|
| 认证中心 | 登录不受限；面板提示**「当前账号未开通任何站点访问权限，请联系社区管理员。退出登录后，可以游客身份浏览社区内容。」**，不显示任何站点入口卡片（已登录访问登录页自动进入面板） |
| 禅道 | 持有效票据访问任意前台页面 → 302 中心；中心退出（票据清除）后 → 游客只读浏览 |
| BBS | 同上：持有效票据访问任意前台页面 → 302 中心；中心退出后 → 游客浏览 |
| 判定口径 | ticket 兑换返回**业务拒绝**（1004 未开通 / 1003 禁用 / 1001 不存在）→ 弹回中心；**票据无效/过期（2xxx）/协议异常/中心不可达 → 静默游客**（降级不受影响，不弹） |
| 拒绝缓存 | 业务拒绝按票据值缓存于分站会话：同会话不重复请求中心；重新登录（换票据）自动重试；中心退出（清票据）后不再触发 |

实现：中心 `login.php` 已登录访问 → 302 面板；BBS `hook/index_inc_route_before.php`、禅道 `common/ext/model/hook/checkPriv.php` + `user/ext/model/wyauth.php`（identifyByWyAuth 三态返回 true / 'denied' / false）——均定向中心 `login_url`（已登录时自动进面板）。

实测（vtest_limited）：禅道首页/任务页、BBS 首页/版块页/发帖页 → 全部弹回面板 ✓；中心退出后两站恢复游客 ✓；已开通用户（vtest_forum）、无票据游客、伪造票据均不受影响 ✓。

### 15.16 安全加固落地（v4.10.6，2026-10-08）

按《统一认证安全性报告》（wenyinos-env/统一认证安全性报告.md）完成上线前必修与首轮建议加固，逐项证据见报告"修复核销表"：

**必修项**
1. **密码双层哈希**：中心与禅道密码统一为 `bcrypt(md5(明文))`——离线批处理全库转换（中心 14 个、禅道 6 个有效密码；空密码账号随首次登录/改密自动升级），表结构 `password VARCHAR(60)`；原密码照常登录、用户零感知。中心新增 `wy_password_hash` / `wy_password_verify`（兼容读取历史 32 位值作纵深防御）；禅道扩展覆盖 `identify`（双层校验）与 `create`/`update`/`updatePassword`（写后修正为双层）；upsert 写入双层。
2. **API 越权收敛**：`/api/password` 支持 `old_password` 校验——自助改密路径强制携带，管理员重置场景不带并由审计标记 `password_sync_admin`；`password` 参数改为可选（纯资料同步不再强制）。
3. **禅道暴露面封禁**：Tengine 封禁 `/install.php`、`/upgrade.php`、`/checktable.php`、`api-*`（getModel/sql/debug）、`editor-*` 及 `?m=api` / `?m=editor` 查询形式——**覆盖大小写与 pathinfo 变体**（实测 13 类路径全部 404：含 `install.php/`、`INSTALL.PHP`、`%2F`、`?m=EDITOR` 等；正常页面不受影响）。注意 `install.php` 在 `config/my.php` 完好时仅 302 拒绝，**一旦配置丢失任何访客可触发全新安装**（系统接管），封禁为必须项。**全新部署渠道保留**：开源代码本身不含任何安装限制，封禁仅存在于运行态 nginx 配置——全新部署阶段**不启用封禁段**（通过 install.php 完成安装向导），安装完成后启用；两阶段切换指引见生产片段文件 `wenyinos-env/nginx/production-snippets.conf`。
4. **BBS sitename 存储型 XSS（CVE-2020-21495）**：`index.htm` / `header.inc.htm` 输出转义——实测探针注入后原样转义输出（raw=0）。
5. **HTTPS/HSTS**：生产配置片段已交付（301 跳转 + HSTS + 安全响应头），部署时并入。

**建议项（首轮加固）**
6. 中心会话：session cookie 增加 `secure`（绑定 `WY_COOKIE_SECURE`）；登录成功后 `session_regenerate_id`（防会话固定）。
7. BBS cookie：`bbs_token` / `bbs_sid` 补 `HttpOnly` + `SameSite=Lax` + 按协议动态 `Secure`（响应头实测确认）。
8. 注册/找回页枚举文案收敛（"该用户名/邮箱暂不可用"）；找回页接入 Turnstile（后台配置后自动生效）。
9. 邮件限速增强：找回邮件增加"单账号 24h/10 封 + 全站 1h/60 封"（验证码邮件既有全站限速保持）。
10. **单点登出轻量校验（M-2）**：分站已登录用户写操作（POST）每 30 分钟校验一次中心票据——中心登出/撤票 → 本地登出（2xxx 静默）；站点准入撤销 → 本地登出并定向中心（1xxx）；中心不可达静默降级。双设备场景实测：撤销后首次操作即掉线、未撤销时不受影响。

**保持不变（经评审决定）**：M-1 密码传输协议维持 md5+HTTPS+HMAC（独立攻击面有限且双层哈希后中心库不再存明文 md5）；L-1 登出维持 GET（分站跳转架构决定，低危）。

**部署衔接**：本地两库已完成扩容与升级（随全量覆盖带走）；部署顺序仍为"先源码、后组模型 SQL"；`wenyinos-env/tool/upgrade-password-hash.php` 已纳入物料（若生产另行导入旧数据可重跑，幂等）。

### 15.17 认证中心反爬虫（v4.10.7，2026-10-08）

认证中心（/auth）为真人交互入口，**不需要任何爬虫访问**。三层实施：

| 层 | 措施 | 说明 |
|---|---|---|
| 协议声明 | 主站 `robots.txt` 增加 `Disallow: /auth/` | 对遵守协议的爬虫生效（随主站仓库发布） |
| 响应头 | 全部页面与接口统一输出 `X-Robots-Tag: noindex, nofollow, noarchive`（`auth/core.php` 统一发 + nginx 双保险） | 阻止搜索引擎收录与归档 |
| 运行拦截 | nginx 对 `/auth/` 路径：**限速**（5r/s，burst 15）+ **爬虫/空 UA 拒绝**（bot/spider/crawl/scrapy/python/wget 等关键词 → 403） | 对不守协议的扫描器/采集器生效 |

**api.php 豁免**：`location ~* ^/auth/api\.php$` 独立放行（不判 UA、不限速）——分站服务端调用（PHP curl 默认 UA 为空）必须畅通，其安全性由 HMAC 签名 + nonce 保障。

**实测**：浏览器 UA 200；Googlebot / bingbot / 百度 / GPTBot / Scrapy / python-requests / Wget / 空 UA → 403；连打 30 次触发限速（16×200 + 14×503，3 秒后恢复）；API 豁免正常（python UA 调 api.php → 200）；SSO 全链路（中心登录 → 禅道 [11] / BBS 兑换）回归不受影响；robots.txt 与响应头输出确认。

**部署**：nginx 配置见 `wenyinos-env/nginx/production-snippets.conf` 第五节（http 级 limit_req_zone + 两个 location，并入主站 server 块）。

### 15.18 PasteBin 接入统一认证（v4.11，2026-10-08）

第四站接入：https://paste.wenyinos.com/（Node.js / Express 5 + SQLite 自有项目）。按要求**不保留原有认证方式**——原注册 / 登录 / 图形验证码整体移除，本地不再保存任何密码。

**架构适配**（Node 无框架级扩展机制，采用"服务端票据兑换 + 本地 JWT"）：

- **后端** `GET /api/sso`：服务端读取 `wy_auth`（HttpOnly，前端 JS 不可读）→ HMAC-SHA256 签名调用中心 ticket API（Node 内置 fetch，零新依赖）→ 用户 upsert（`users.sso_uid` 映射中心 uid；**存量用户按用户名自动绑定**）→ 签发原有 JWT（24h）
- **前端**：页面加载时静默调 `/api/sso`（浏览器持中心票据即自动登录）；「登录」按钮跳中心登录页；「退出」清 JWT 并跳中心 logout（全域登出）；`GET /api/config` 下发登录/退出地址
- **受限用户屏蔽（v4.11.3，对齐 BBS/禅道语义）**：持票据但未开通本站 → `/api/sso` 返回 403 携带 loginUrl → 前端**自动定向回认证中心**（已登录时进入面板查看"未开通"提示，中心退出后恢复游客浏览）；写操作途中站点准入被撤销（M-2 校验 1xxx）同样定向中心；票据撤销（2xxx）则本地 401 转游客
- **配置**：`.env`（`process.loadEnvFile` 加载；`.env.example` 模板；`.gitignore` 已排除 .env / database.sqlite / .jwt-secret）
- **M-2 对齐**：写操作（POST/DELETE）每 30 分钟校验一次中心票据——撤销即 401（前端转为游客、用户重新登录）；中心不可达或浏览器无票据时静默降级不加锁
- **降级**：中心不可达仅游客浏览公开列表（无本地密码兜底，符合"不保留"要求）

**中心侧**：`apps` 表新增 `paste` 应用（第三枚站点密钥）；准入 = 超级管理 / 开发团队 / 注册用户（受限用户不可用）；账号面板新增「代码粘贴」站点卡片（`WY_SITE_PASTE`）。

**实测**：ruojiner SSO 兑换并绑定 `sso_uid` ✓；发布 / 删除 paste ✓；M-2 双设备场景（撤销后首次写操作 401「登录已失效」）✓；受限用户 403「未开通本站访问」✓；游客公开浏览 ✓；测试数据已还原。

**部署**：`make-production.sh`（v4.11 起）生成第四份生产 `.env`（PasteBin）；PasteBin 以宝塔 Node 项目 / PM2 启动，`database.sqlite` 随源码上传（已 gitignore）。**站点 nginx 已由宝塔面板反向代理功能配好**（`vhost/nginx/proxy/paste.wenyinos.com/*.conf`，指向 Node 端口）——无需改动，仅确认反代目标端口与 Node 监听一致。生产实际站点配置汇总见 `wenyinos-env/nginx/bt-panel/bt/`（四站点，含 [WY] 标记的变更点）。

**存量用户对接（已完成）**：`ruojiner` 按名自动绑定；`天知道` 更名为 `tianzhidao`（中文名保留于中心 realname 昵称）、`Sadosasaki` 中心建同名账号——两者中心 uid 已直接写入本地 `sso_uid`，paste 数据完整继承（实测 tianzhidao 可管理其历史片段）。中心侧初始密码已交付管理员分发给本人（建议首次登录后自行修改）。

**新增站点接入清单（组管理动态化，v4.11.1）**：组管理的站点勾选项改为**动态读取 `apps` 表**（新增站点零改码、停用应用自动隐藏）；账号面板卡片未配置 `$site_meta` 时以通用卡片兜底显示（不再跳过）。未来接入新站点的完整步骤：

1. **中心**：`apps` 表插入应用（app_id / name / secret）——「组管理」勾选项与「应用密钥」页自动出现；
2. **中心 `.env`**：增加 `WY_SITE_<APP>` 站点入口 URL（账号面板跳转用）；
3. （可选）中心 `index.php` 的 `$site_meta` 增加卡片名称/描述/图标（缺省有通用卡片兜底）；
4. **新站点侧**：按本站架构接入（PHP 站点用 ext/hook 模式、Node 站点用"服务端票据兑换 + 本地 JWT"模式），密钥从中心「应用密钥」页取得。

### 15.19 登录来源提示与回跳（v4.11.4，2026-10-08）

正常用户从分站引导至中心（分站「登录」入口跳转携带 Referer）时，中心自动识别来源站点（`wy_guess_referer_site()`：与已配置站点白名单按 host 匹配，输出用配置内 URL——无开放重定向面；**显示标题取自 apps 表 name**，与组管理/密钥页同一数据源，缺省退回 host）：

- **登录页**：表单上方提示「您刚才访问的是 **社区论坛**，登录后即可进入」——未登录时告知来源与去向；
- **账号面板**：顶部提示「您刚才访问的是 **社区论坛** —— **点击进入**」——一键回到来源站（来源记录存于会话，登录成功 session regenerate 后保留）；
- **边界**：无 Referer（直接访问中心）无提示；来源站点不在用户可访问范围（受限场景）不显示（"点击进入"仅在可进入时出现）；识别支持多站点（forum/dev/paste 通用）。

**实测**：带 forum / paste Referer 登录 → 登录页与面板提示正确（标题"社区论坛"/"代码粘贴" + 点击进入链接指向对应站点）✓；无 Referer 无提示 ✓；受限用户带 Referer 登录不显示 ✓；面板正常渲染回归 ✓。
