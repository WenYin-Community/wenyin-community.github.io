# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WenYin Open Source Community (玟茵开源社区) official website - a static site built with Bootstrap 5, featuring bilingual (Chinese/English) support. Deployed via GitHub Pages at https://wenyinos.com.

## Development

**No build step required.** Open `index.html` directly in a browser to preview.

**Regenerate favicons** (requires ImageMagick):
```bash
convert assets/img/wenyin-logo.png -define icon:auto-resize="16,32,48,64,128,256" assets/img/favicon/favicon.ico
convert assets/img/wenyin-logo.png -resize 32x32 assets/img/favicon/favicon-32x32.png
convert assets/img/wenyin-logo.png -resize 16x16 assets/img/favicon/favicon-16x16.png
convert assets/img/wenyin-logo.png -resize 180x180 assets/img/favicon/apple-touch-icon.png
```

## Architecture

- **index.html** - Single-page site with all content
- **404.html** - Custom error page
- **assets/css/style.css** - Custom styles; CSS variables defined in `:root`
- **assets/js/main.js** - Language switching, scroll effects, animations

## Bilingual System

All translatable text uses `data-zh` and `data-en` attributes:
```html
<span data-zh="中文文本" data-en="English text">中文文本</span>
```

The JavaScript in `main.js` handles:
- Browser language auto-detection
- Language preference stored in `localStorage` (key: `wenyin-lang`)
- Toggle switch in navbar for manual switching

## CSS Variables

```css
:root {
    --purple-primary: #6f42c1;
    --purple-dark: #4a2c82;
    --gradient-success: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --bg-purple: #5a3e8e;
}
```

## External Dependencies (CDN)

- Bootstrap 5.3.3
- Font Awesome 6.5.1
- GitHub Buttons
