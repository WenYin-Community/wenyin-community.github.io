document.addEventListener('DOMContentLoaded', function() {
    // Update copyright year
    const yearElements = document.querySelectorAll('.current-year');
    yearElements.forEach(function(el) {
        el.textContent = new Date().getFullYear();
    });

    // ======================== Navbar scroll effect ========================
    const navbar = document.getElementById('navbar-main');

    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // ======================== Smooth scroll to anchors ========================
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const navbarHeight = navbar.offsetHeight;
                const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - navbarHeight;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

                // Close mobile nav menu
                const navbarCollapse = document.getElementById('navbar_global');
                if (navbarCollapse.classList.contains('show')) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse) bsCollapse.hide();
                }
            }
        });
    });

    // ======================== Scroll reveal animations ========================
    var revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -60px 0px', threshold: 0.1 });

    document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .feature-row, .stagger-children').forEach(function(el) {
        revealObserver.observe(el);
    });

    // ======================== Typewriter effect (Hero tagline) ========================
    var typewriterEl = document.getElementById('typewriterText');
    var typewriterTimer = null;

    function runTypewriter(el) {
        if (!el) return;
        var fullText = el.getAttribute('data-' + currentLang) || el.textContent;
        var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduced) {
            el.textContent = fullText;
            return;
        }

        if (typewriterTimer) clearInterval(typewriterTimer);
        el.textContent = '';
        var idx = 0;
        typewriterTimer = setInterval(function() {
            idx++;
            el.textContent = fullText.slice(0, idx);
            if (idx >= fullText.length) {
                clearInterval(typewriterTimer);
                typewriterTimer = null;
            }
        }, 90);
    }

    // ======================== GitHub Stars 计数 ========================
    var starsEl = document.getElementById('githubStars');
    if (starsEl) {
        var CACHE_KEY = 'wenyin-github-stars';
        var CACHE_TTL = 6 * 60 * 60 * 1000; // 6 hours
        var cached = null;

        try {
            var raw = localStorage.getItem(CACHE_KEY);
            if (raw) {
                cached = JSON.parse(raw);
                if (Date.now() - cached.time > CACHE_TTL) cached = null;
            }
        } catch (e) { /* ignore */ }

        if (cached) {
            starsEl.textContent = cached.count;
        } else {
            // Fetch all repos and sum stars
            fetch('https://api.github.com/users/wenyinos/repos?per_page=100')
                .then(function(res) { return res.json(); })
                .then(function(repos) {
                    if (!Array.isArray(repos)) return;
                    var total = repos.reduce(function(sum, r) {
                        return sum + (r.stargazers_count || 0);
                    }, 0);
                    starsEl.textContent = total;
                    try {
                        localStorage.setItem(CACHE_KEY, JSON.stringify({ count: total, time: Date.now() }));
                    } catch (e) { /* ignore */ }
                })
                .catch(function() {
                    starsEl.textContent = '★';
                });
        }
    }

    // ======================== Canvas 水墨遮罩效果 (GPU合成优化版) ========================
    // 使用 canvas 合成操作代替逐像素计算：
    //   destination-out + 预渲染笔刷精灵 → 凿开遮罩
    //   source-over 低透明度填充 → 遮罩自动恢复
    // 无任何逐像素 JS 循环，全程 GPU 加速
    var canvas = document.getElementById('inkCanvas');
    var heroSection = document.getElementById('hero');
    if (canvas && heroSection) {
        var canHover = window.matchMedia('(hover: hover)').matches;
        if (canHover) {
            var ctx = canvas.getContext('2d');
            if (ctx) {
                var MASK_COLOR = 'rgb(90, 62, 142)';
                var DPR = Math.min(window.devicePixelRatio || 1, 1.5);
                var BRUSH_BASE = 110;      // 笔刷基础半径 (CSS px)
                var BRUSH_MIN = 16;        // 快速移动时最小半径
                var STAMP_STEP = 14;       // 插值步进距离
                var CARVE_ALPHA = 0.6;     // 单次凿开强度
                var RESTORE_RATE = 0.028;  // 每帧恢复速率
                var GROWTH_MS = 420;       // 墨迹生长时长

                var w = 0, h = 0;
                var animating = false;
                var lastMoveTime = 0;
                var lastX = null, lastY = null;

                // --- 预渲染不规则墨迹笔刷精灵 (只计算一次) ---
                var BRUSH_SZ = 256;
                var brushSprite = document.createElement('canvas');
                brushSprite.width = BRUSH_SZ;
                brushSprite.height = BRUSH_SZ;
                (function() {
                    var bctx = brushSprite.getContext('2d');
                    var c = BRUSH_SZ / 2;
                    var seed = Math.random() * Math.PI * 2;
                    var N = 180;
                    // 不规则边缘轮廓 (与原版相同的谐波公式)
                    bctx.beginPath();
                    for (var k = 0; k <= N; k++) {
                        var ang = (k / N) * Math.PI * 2;
                        var wob = 0.78 +
                            0.14 * Math.sin(ang * 3 + seed) +
                            0.08 * Math.sin(ang * 7 + seed * 2.1) +
                            0.05 * Math.sin(ang * 13 + seed * 0.7);
                        var rr = c * 0.92 * wob;
                        var px = c + Math.cos(ang) * rr;
                        var py = c + Math.sin(ang) * rr;
                        if (k === 0) bctx.moveTo(px, py);
                        else bctx.lineTo(px, py);
                    }
                    bctx.closePath();
                    // 径向渐变填充 → 柔和衰减边缘
                    var grad = bctx.createRadialGradient(c, c, 0, c, c, c * 0.92);
                    grad.addColorStop(0, 'rgba(0,0,0,1)');
                    grad.addColorStop(0.5, 'rgba(0,0,0,0.85)');
                    grad.addColorStop(0.8, 'rgba(0,0,0,0.4)');
                    grad.addColorStop(1, 'rgba(0,0,0,0)');
                    bctx.fillStyle = grad;
                    bctx.fill();
                })();

                // --- 活跃墨迹队列 ---
                var brushes = [];

                function resizeCanvas() {
                    var rect = heroSection.getBoundingClientRect();
                    w = rect.width;
                    h = rect.height;
                    canvas.width = Math.round(w * DPR);
                    canvas.height = Math.round(h * DPR);
                    canvas.style.width = w + 'px';
                    canvas.style.height = h + 'px';
                    brushes = [];
                    lastX = null;
                    lastY = null;
                    // 重填遮罩底色
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.globalAlpha = 1;
                    ctx.fillStyle = MASK_COLOR;
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                }
                resizeCanvas();

                var resizeTimer = null;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(resizeCanvas, 150);
                });

                function stampAt(x, y, radius) {
                    brushes.push({
                        x: x, y: y,
                        r: radius,
                        born: performance.now(),
                        grown: false
                    });
                    if (brushes.length > 50) brushes.splice(0, brushes.length - 50);
                }

                function stampAlong(x, y) {
                    var now = performance.now();
                    // 根据移动速度动态调整笔刷大小 (快→小，慢→大)
                    var speed = 0;
                    if (lastX !== null) {
                        speed = Math.hypot(x - lastX, y - lastY);
                    }
                    var radius = Math.max(BRUSH_MIN, BRUSH_BASE - speed * 0.4);

                    if (lastX === null) {
                        stampAt(x, y, radius);
                    } else {
                        var dx = x - lastX, dy = y - lastY;
                        var dist = Math.hypot(dx, dy);
                        var steps = Math.max(1, Math.ceil(dist / STAMP_STEP));
                        for (var i = 1; i <= steps; i++) {
                            stampAt(lastX + (dx * i) / steps, lastY + (dy * i) / steps, radius);
                        }
                    }
                    lastX = x;
                    lastY = y;
                    lastMoveTime = now;
                    startLoop();
                }

                // --- 动画主循环 ---
                function startLoop() {
                    if (!animating) {
                        animating = true;
                        requestAnimationFrame(frame);
                    }
                }

                function frame() {
                    var now = performance.now();
                    var cw = canvas.width, ch = canvas.height;

                    // 1. 恢复遮罩 (GPU 单次填充，代替逐像素 restore)
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.globalAlpha = RESTORE_RATE;
                    ctx.fillStyle = MASK_COLOR;
                    ctx.fillRect(0, 0, cw, ch);

                    // 2. 凿开墨迹 (GPU 精灵绘制，代替逐像素 carve)
                    ctx.globalCompositeOperation = 'destination-out';
                    var hasActive = false;

                    for (var i = brushes.length - 1; i >= 0; i--) {
                        var b = brushes[i];
                        var age = now - b.born;

                        if (age > GROWTH_MS + 150) {
                            brushes.splice(i, 1);
                            continue;
                        }
                        hasActive = true;

                        if (age < GROWTH_MS) {
                            // 生长阶段：半径缓动扩大，强度递减
                            var t = age / GROWTH_MS;
                            var eased = 1 - (1 - t) * (1 - t) * (1 - t);
                            var r = (BRUSH_MIN + (b.r - BRUSH_MIN) * eased) * DPR;
                            ctx.globalAlpha = CARVE_ALPHA * (1 - t * 0.75);
                            ctx.drawImage(brushSprite, b.x * DPR - r, b.y * DPR - r, r * 2, r * 2);
                            b.grown = true;
                        } else if (!b.grown) {
                            b.grown = true;
                        }
                    }

                    // 3. 空闲超过 1.6s 后一次性补满 (消除 8bit 量化残留)，然后停止循环
                    if (!hasActive && now - lastMoveTime > 1600) {
                        ctx.globalCompositeOperation = 'source-over';
                        ctx.globalAlpha = 1;
                        ctx.fillStyle = MASK_COLOR;
                        ctx.fillRect(0, 0, cw, ch);
                        animating = false;
                        return;
                    }

                    requestAnimationFrame(frame);
                }

                heroSection.addEventListener('mousemove', function(e) {
                    var rect = heroSection.getBoundingClientRect();
                    stampAlong(e.clientX - rect.left, e.clientY - rect.top);
                });

                heroSection.addEventListener('mouseleave', function() {
                    lastX = null;
                    lastY = null;
                });
            }
        }
    }

    // ======================== Language Switching ========================
    var langToggle = document.getElementById('langToggle');
    var currentLang = 'zh';

    function detectBrowserLanguage() {
        var lang = (navigator.language || navigator.userLanguage || 'zh-CN').toLowerCase();
        if (lang.startsWith('en')) return 'en';
        return 'zh';
    }

    function setLanguage(lang) {
        currentLang = lang;
        document.documentElement.lang = lang === 'en' ? 'en' : 'zh-CN';
        localStorage.setItem('wenyin-lang', lang);

        // Update all translatable elements (skip typewriter element)
        document.querySelectorAll('[data-zh][data-en]').forEach(function(el) {
            if (el.id === 'typewriterText') return;
            var text = el.getAttribute('data-' + lang);
            if (text) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.placeholder = text;
                } else {
                    el.innerHTML = text;
                }
            }
        });

        // Update page title
        var titleEl = document.querySelector('title[data-zh][data-en]');
        if (titleEl) {
            document.title = titleEl.getAttribute('data-' + lang);
        }

        // Update toggle switch state
        if (langToggle) {
            langToggle.checked = lang === 'en';
        }

        // Re-run typewriter with new language
        runTypewriter(typewriterEl);
    }

    // Initialize language
    var savedLang = localStorage.getItem('wenyin-lang');
    var initLang = savedLang || detectBrowserLanguage();
    setLanguage(initLang);

    // Toggle language on switch change
    if (langToggle) {
        langToggle.addEventListener('change', function() {
            setLanguage(this.checked ? 'en' : 'zh');
        });
    }
});
