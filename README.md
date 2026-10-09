# 玟茵开源社区 · 官网

**玟茵开源社区（WenYin Open Source Community）** 官方网站，基于 Bootstrap 5 构建，支持中英双语。

## 🌐 在线站点

[https://wenyinos.com](https://wenyinos.com)

## ✨ 特性

- Bootstrap 5 响应式设计
- 中英双语（自动识别 + 手动切换）
- 现代 UI 与动效组件
- 移动端优先布局

## 🔐 统一身份认证

本站承载**玟茵开源社区统一认证中心**（[`/auth`](https://wenyinos.com/auth/)）：一个账号通行全社区——一次登录即在社区各站点（论坛、禅道、PasteBin）保持登录，支持集中用户管理与站点级准入控制，并内置自助注册与密码找回（SMTP 发信）。

安全基线：双层密码哈希（bcrypt 覆盖旧 md5，透明迁移）、密码复杂度策略（8-64 位且须含字母和数字）、频率限制、反爬虫防护与会话 Cookie 加固。

- 登录入口：<https://wenyinos.com/auth/login.php>
- 账号面板：<https://wenyinos.com/auth/index.php>
- `auth/` 为轻量 PHP 服务——**已在本仓库完整开源**。所有敏感配置（数据库凭证、票据密钥、Cookie 域）均从本地 `.env` 读取（模板见 `auth/.env.example`，本身不入库）；静态站点部分零构建。

## 🚀 快速开始

```bash
git clone https://github.com/WenYin-Community/wenyin-community.github.io.git
cd wenyin-community.github.io
```

直接用浏览器打开 `index.html` 即可，无需任何构建步骤。

## 📄 许可

见 [LICENSE](LICENSE) 文件。

## 📞 联系我们

- **邮箱**：admin@wenyinos.com
- **论坛**：[forum.wenyinos.com](http://forum.wenyinos.com/)
- **GitHub**：[github.com/WenYin-Community](https://github.com/WenYin-Community)
