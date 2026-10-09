# 玟茵开源社区 · 官网

**玟茵开源社区（WenYin Open Source Community）** 官方网站，基于 Bootstrap 5 构建，支持中英双语。

## ✨ 特性

- Bootstrap 5 响应式设计
- 中英双语（自动识别 + 手动切换）
- 现代 UI 与动效组件
- 移动端优先布局

## 🔐 统一身份认证

本站承载**玟茵开源社区统一认证中心**（`auth/` 目录）：一个账号通行全社区——一次登录即在社区各站点（论坛、禅道、PasteBin）保持登录，支持集中用户管理与站点级准入控制，并内置自助注册与密码找回（SMTP 发信）。

安全基线：双层密码哈希（bcrypt 覆盖旧 md5，透明迁移）、密码复杂度策略（8-64 位且须含字母和数字）、频率限制、反爬虫防护与会话 Cookie 加固。

`auth/` 为轻量 PHP 服务——**已在本仓库完整开源**。所有敏感配置（数据库凭证、票据密钥、Cookie 域）均从本地 `.env` 读取（模板见 `auth/.env.example`，本身不入库）。

## 🛠 部署说明

本仓库为「静态站点 + 轻量 PHP 认证中心」混合结构，无任何构建步骤。

**一、静态部分**

将仓库内容放置到 Web 服务器站点根目录即可（凡能直接返回 html / css / js 的服务器均可）；本地预览直接用浏览器打开 `index.html`。

**二、认证中心（`auth/`）**

1. **环境要求**：PHP 8.0+（推荐 8.5），需启用 `pdo_mysql`、`mbstring`、`curl` 扩展；MySQL 5.7+（utf8mb4）
2. **建库建表**：创建空数据库后执行建表语句——完整表结构 SQL 见 `auth/BBS-禅道SSO集中认证方案.md` 第四节
3. **配置文件**：复制 `auth/.env.example` 为 `auth/.env`，填写数据库连接、`WY_TICKET_KEY`（用 `openssl rand -hex 32` 生成）、Cookie 域与各站点地址
4. **Web 服务器**：将 `/auth` 下的 PHP 请求转交 PHP-FPM（Nginx 参考：`location ~ \.php$ {}` 内配置 `fastcgi_pass` 指向 PHP-FPM 套接字并引入 `fastcgi.conf`）
5. **后台配置**：登录中心后台可配置 SMTP 发信（注册验证 / 找回密码）、注册开关、人机验证等

**三、社区站点接入**

其他社区站点（论坛 / 禅道 / PasteBin 等）通过中心 HMAC 签名 API 接入：在中心 `apps` 表登记应用与密钥后，站点侧按其架构对接（完整接入清单见 `auth/BBS-禅道SSO集中认证方案.md` 15.18 节）。

> 安全提示：生产环境请启用 HTTPS、关闭 PHP `display_errors`，并妥善保管 `auth/.env`（不入库）。

## 📄 许可

见 [LICENSE](LICENSE) 文件。

## 📞 联系我们

- **邮箱**：admin@wenyinos.com
