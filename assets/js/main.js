document.addEventListener('DOMContentLoaded', function() {
    // Update copyright year
    const yearElements = document.querySelectorAll('.current-year');
    yearElements.forEach(function(el) {
        el.textContent = new Date().getFullYear();
    });

    // Navbar scroll effect
    const navbar = document.getElementById('navbar-main');
    let lastScroll = 0;

    window.addEventListener('scroll', function() {
        const currentScroll = window.pageYOffset;
        if (currentScroll > 50) {
            navbar.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.2)';
        } else {
            navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.15)';
        }
        lastScroll = currentScroll;
    });

    // Smooth scroll to anchors
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

    // Scroll animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeInUp');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.card, .icon-box, .accordion-item').forEach(function(el) {
        observer.observe(el);
    });

    // ======================== Language Switching ========================
    const langToggle = document.getElementById('langToggle');

    function detectBrowserLanguage() {
        const lang = (navigator.language || navigator.userLanguage || 'zh-CN').toLowerCase();
        if (lang.startsWith('en')) return 'en';
        return 'zh';
    }

    function setLanguage(lang) {
        document.documentElement.lang = lang === 'en' ? 'en' : 'zh-CN';
        localStorage.setItem('wenyin-lang', lang);

        // Update all translatable elements
        document.querySelectorAll('[data-zh][data-en]').forEach(function(el) {
            const text = el.getAttribute('data-' + lang);
            if (text) {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.placeholder = text;
                } else {
                    el.innerHTML = text;
                }
            }
        });

        // Update page title
        const titleEl = document.querySelector('title[data-zh][data-en]');
        if (titleEl) {
            document.title = titleEl.getAttribute('data-' + lang);
        }

        // Update toggle switch state
        if (langToggle) {
            langToggle.checked = lang === 'en';
        }
    }

    // Initialize language
    const savedLang = localStorage.getItem('wenyin-lang');
    const currentLang = savedLang || detectBrowserLanguage();
    setLanguage(currentLang);

    // Toggle language on switch change
    if (langToggle) {
        langToggle.addEventListener('change', function() {
            setLanguage(this.checked ? 'en' : 'zh');
        });
    }
});
