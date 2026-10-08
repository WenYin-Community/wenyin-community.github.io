# WenYin Open Source Community

Official website for **WenYin Open Source Community** (玟茵开源社区), built with Bootstrap 5 and bilingual support.

## 🌐 Live Site
[https://wenyinos.com](https://wenyinos.com)

## ✨ Features

- Bootstrap 5 responsive design
- Bilingual support (Chinese/English) with auto-detection and manual toggle
- Modern UI with animated components
- Mobile-first layout

## 🔐 Unified Authentication

This site hosts the **WenYin Open Source Community unified authentication center** at [`/auth`](https://wenyinos.com/auth/): one account for the whole community — sign in once and you stay signed in across the community sites (forum & ZenTao), with centralized user management and per-site access control. Self-service registration and password recovery (via SMTP) are included.

- Login entry: <https://wenyinos.com/auth/login.php>
- Account panel: <https://wenyinos.com/auth/index.php>
- The `auth/` directory is a lightweight PHP service — **fully open-sourced in this repository**. All secrets (database credentials, ticket key, cookie domain) are read from a local `.env` file (template: `auth/.env.example`, itself excluded from the repo); the static site remains build-free.

## 🚀 Getting Started

```bash
git clone https://github.com/WenYin-Community/wenyin-community.github.io.git
cd wenyin-community.github.io
```

Open `index.html` in any browser. No build step required.

## 📄 License

See [LICENSE](LICENSE) file.

## 📞 Contact

- **Email**: admin@wenyinos.com
- **Forum**: [forum.wenyinos.com](http://forum.wenyinos.com/)
- **GitHub**: [github.com/WenYin-Community](https://github.com/WenYin-Community)
