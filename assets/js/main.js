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
    }, { rootMargin: '0px 0px -80px 0px', threshold: 0.1 });

    document.querySelectorAll('.reveal-up, .reveal-left, .reveal-right, .feature-row, .stagger-children').forEach(function(el) {
        revealObserver.observe(el);
    });

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

    // ======================== Canvas 水墨遮罩效果 (Mimo风格) ========================
    // 像素级 alpha buffer：管理每个像素的遮罩透明度
    // 墨迹凿开遮罩（alpha → 0），鼠标移走后遮罩自动恢复（alpha → 1）
    var canvas = document.getElementById('inkCanvas');
    var heroSection = document.getElementById('hero');
    if (canvas && heroSection) {
        var canHover = window.matchMedia('(hover: hover)').matches;
        if (canHover) {
            var ctx = canvas.getContext('2d');
            if (ctx) {
                var MASK_R = 90, MASK_G = 62, MASK_B = 142;
                var R_START = 8;
                var R_END = 128;
                var R_VARY = 0.45;
                var FADE_LIFETIME = 520;   // 凿开阶段 ms
                var RESTORE_TIME = 800;    // 恢复阶段 ms
                var STAMP_STEP = 12;
                var MAX_STAMPS = 160;
                var DPR = Math.min(window.devicePixelRatio || 1, 2);

                var w = 0, h = 0, cw = 0, ch = 0;
                var alphaBuf = null;

                function resizeCanvas() {
                    var rect = heroSection.getBoundingClientRect();
                    w = rect.width;
                    h = rect.height;
                    cw = Math.round(w * DPR);
                    ch = Math.round(h * DPR);
                    canvas.width = cw;
                    canvas.height = ch;
                    canvas.style.width = w + 'px';
                    canvas.style.height = h + 'px';
                    // 重新填满遮罩色
                    alphaBuf = new Float32Array(cw * ch);
                    for (var i = 0; i < alphaBuf.length; i++) alphaBuf[i] = 1;
                    ctx.globalCompositeOperation = 'source-over';
                    ctx.fillStyle = 'rgb(' + MASK_R + ',' + MASK_G + ',' + MASK_B + ')';
                    ctx.fillRect(0, 0, cw, ch);
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);

                var stamps = [];
                var lastX = null, lastY = null;
                var running = false;

                function addStamp(x, y) {
                    if (stamps.length >= MAX_STAMPS) stamps.shift();
                    stamps.push({
                        x: x, y: y,
                        born: performance.now(),
                        seed: Math.random() * Math.PI * 2,
                        rmax: R_END * (1 - R_VARY + Math.random() * R_VARY)
                    });
                }

                function stampAlong(x, y) {
                    if (lastX === null) {
                        addStamp(x, y);
                    } else {
                        var dx = x - lastX, dy = y - lastY;
                        var dist = Math.hypot(dx, dy);
                        var steps = Math.max(1, Math.ceil(dist / STAMP_STEP));
                        for (var i = 1; i <= steps; i++) {
                            addStamp(lastX + (dx * i) / steps, lastY + (dy * i) / steps);
                        }
                    }
                    lastX = x;
                    lastY = y;
                }

                // 像素坐标
                function stampToPixels(s, alpha, op) {
                    var cx = Math.round(s.x * DPR);
                    var cy = Math.round(s.y * DPR);
                    var rad = Math.round(s.rmax * DPR * 1.2) + 2;
                    var x0 = Math.max(0, cx - rad);
                    var y0 = Math.max(0, cy - rad);
                    var x1 = Math.min(cw, cx + rad);
                    var y1 = Math.min(ch, cy + rad);
                    var seed = s.seed;

                    for (var py = y0; py < y1; py++) {
                        var rowOff = py * cw;
                        for (var px = x0; px < x1; px++) {
                            var adx = px - cx, ady = py - cy;
                            var dist = Math.sqrt(adx * adx + ady * ady);
                            var ang = Math.atan2(ady, adx);
                            var wob = 0.78 +
                                0.14 * Math.sin(ang * 3 + seed) +
                                0.08 * Math.sin(ang * 7 + seed * 2.1) +
                                0.05 * Math.sin(ang * 13 + seed * 0.7);
                            var rr = s.rmax * DPR * wob;
                            if (dist >= rr) continue;
                            var falloff = 1 - dist / rr;
                            var strength = falloff * falloff * alpha;
                            var idx = rowOff + px;
                            if (op === 'carve') {
                                alphaBuf[idx] = Math.max(0, alphaBuf[idx] - strength);
                            } else {
                                alphaBuf[idx] = Math.min(1, alphaBuf[idx] + strength);
                            }
                        }
                    }
                }

                function draw() {
                    running = false;
                    var now = performance.now();
                    var active = false;
                    var imgData = ctx.createImageData(cw, ch);
                    var pixels = imgData.data;

                    for (var i = 0; i < stamps.length; i++) {
                        var s = stamps[i];
                        var elapsed = now - s.born;
                        var phaseEnd = FADE_LIFETIME + RESTORE_TIME;

                        if (elapsed >= phaseEnd) {
                            // 完全恢复，跳过
                            stampToPixels(s, 0, 'restore');
                            continue;
                        }
                        active = true;

                        if (elapsed < FADE_LIFETIME) {
                            // 阶段一：凿开遮罩
                            var t = elapsed / FADE_LIFETIME;
                            var ease = 1 - Math.pow(1 - t, 3);
                            s.rmax_curr = R_START + (s.rmax - R_START) * ease;
                            var alpha = 1 - t;
                            var tmp = s.rmax;
                            s.rmax = s.rmax_curr;
                            stampToPixels(s, alpha, 'carve');
                            s.rmax = tmp;
                        } else {
                            // 阶段二：遮罩恢复
                            var restoreT = (elapsed - FADE_LIFETIME) / RESTORE_TIME;
                            var restoreAlpha = Math.min(1, restoreT * 2);
                            var tmp = s.rmax;
                            s.rmax = s.rmax_curr || s.rmax;
                            stampToPixels(s, restoreAlpha, 'restore');
                            s.rmax = tmp;
                        }
                    }

                    // 清理已完成的 stamp
                    stamps = stamps.filter(function(s) {
                        return (now - s.born) < (FADE_LIFETIME + RESTORE_TIME);
                    });

                    // 渲染 alpha buffer → canvas
                    for (var j = 0, len = cw * ch; j < len; j++) {
                        var a = alphaBuf[j];
                        if (a < 1) {
                            var off = j * 4;
                            pixels[off] = MASK_R;
                            pixels[off + 1] = MASK_G;
                            pixels[off + 2] = MASK_B;
                            pixels[off + 3] = Math.round(a * 255);
                        }
                    }
                    ctx.putImageData(imgData, 0, 0);

                    if (active) {
                        running = true;
                        requestAnimationFrame(draw);
                    }
                }

                heroSection.addEventListener('mousemove', function(e) {
                    var rect = heroSection.getBoundingClientRect();
                    stampAlong(e.clientX - rect.left, e.clientY - rect.top);
                    if (!running) {
                        running = true;
                        requestAnimationFrame(draw);
                    }
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

    function detectBrowserLanguage() {
        var lang = (navigator.language || navigator.userLanguage || 'zh-CN').toLowerCase();
        if (lang.startsWith('en')) return 'en';
        return 'zh';
    }

    function setLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'zh-CN';
        localStorage.setItem('wenyin-lang', lang);

        // Update all translatable elements
        document.querySelectorAll('[data-zh][data-en]').forEach(function(el) {
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
    }

    // Initialize language
    var savedLang = localStorage.getItem('wenyin-lang');
    var currentLang = savedLang || detectBrowserLanguage();
    setLanguage(currentLang);

    // Toggle language on switch change
    if (langToggle) {
        langToggle.addEventListener('change', function() {
            setLanguage(this.checked ? 'en' : 'zh');
        });
    }
});
