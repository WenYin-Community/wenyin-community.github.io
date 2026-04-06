# WenYin Open Source Community - Official Website

This repository contains the source code for the official website of **WenYin Open Source Community** (玟茵开源社区). The site has been rebuilt using **Bootstrap 5** with modern UI components, bilingual support, and responsive design.

## 🌐 Live Demo
Visit the website: [https://wenyinos.com](https://wenyinos.com)

## ✨ Features

- **Bootstrap 5 Framework**: Modern, responsive, and mobile-first design
- **Bilingual Support**: Automatic language detection (Chinese/English) with manual toggle switch
- **Responsive Layout**: Optimized for desktop, tablet, and mobile devices
- **Modern UI Components**:
  - Mega menu navigation with icons
  - Animated cards and sections
  - Image carousel
  - FAQ accordion
  - Custom language toggle switch
- **Auto-generated Favicon**: Created from the community logo using ImageMagick
- **Smooth Animations**: Scroll-triggered fade-in effects and button click animations

## 📁 Project Structure

```
WenYin-New-Page/
├── index.html              # Main HTML file (Bilingual)
├── LICENSE                 # License file
├── README.md               # This file
└── assets/
    ├── css/
    │   └── style.css       # Custom styles and animations
    ├── js/
    │   └── main.js         # Language switching & interactions
    └── img/
        ├── favicon/        # Generated favicons (ico, png)
        ├── wenyin-logo.png # Community logo
        ├── new.png         # Alternative logo
        ├── Screenshot_20221217.png
        ├── btsp1.png       # OS section image
        ├── btsp2.png       # Translation section image
        ├── btsp3.png       # Software section image
        ├── card1.png       # UI Design card
        ├── card2.png       # Usability card
        ├── card3.png       # Community card
        ├── car1.jpg        # Carousel images
        ├── car2.jpg
        ├── car3.jpg
        ├── wenyin_qr.png   # WeChat QR code
        └── qiniu-transparent.png
```

## 🛠️ Technologies Used

- **HTML5** - Semantic markup
- **CSS3** - Custom styles, animations, variables
- **JavaScript (ES6)** - Language detection, DOM manipulation
- **Bootstrap 5.3.3** - UI framework
- **Font Awesome 6.5.1** - Icon library
- **ImageMagick** - Favicon generation

## 🌍 Language Support

The website automatically detects the browser's language setting:
- `zh-CN` (Chinese): Default for Chinese browsers
- `en` (English): Default for English browsers

Users can manually switch languages using the toggle switch in the top navigation bar. Language preference is saved in `localStorage`.

## 🚀 Getting Started

1. Clone the repository:
   ```bash
   git clone https://github.com/WenYin-Community/wenyin-new-page.git
   cd wenyin-new-page
   ```

2. Open `index.html` in any modern web browser. No build step required!

## 📝 Customization

### Adding New Translations
Add `data-zh` and `data-en` attributes to any HTML element:
```html
<span data-zh="中文文本" data-en="English text">中文文本</span>
```

### Modifying Styles
Edit `assets/css/style.css`. Key CSS variables:
```css
:root {
    --purple-primary: #6f42c1;
    --purple-dark: #4a2c82;
    --gradient-success: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --bg-purple: #5a3e8e;
}
```

### Regenerating Favicon
If you update the logo, regenerate favicons using:
```bash
convert assets/img/wenyin-logo.png -define icon:auto-resize="16,32,48,64,128,256" assets/img/favicon/favicon.ico
convert assets/img/wenyin-logo.png -resize 32x32 assets/img/favicon/favicon-32x32.png
convert assets/img/wenyin-logo.png -resize 16x16 assets/img/favicon/favicon-16x16.png
convert assets/img/wenyin-logo.png -resize 180x180 assets/img/favicon/apple-touch-icon.png
```

## 📄 License

This project is licensed under the terms specified in the [LICENSE](LICENSE) file.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## 📞 Contact

- **Email**: admin@wenyinos.com
- **Forum**: [http://forum.wenyinos.com/](http://forum.wenyinos.com/)
- **GitHub**: [https://github.com/WenYin-Community](https://github.com/WenYin-Community)
