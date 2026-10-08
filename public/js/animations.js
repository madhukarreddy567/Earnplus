/* ============================================================
   EarnPlus animations — thin wrapper over anime.js (vendored).
   60fps rule: only transform + opacity are ever animated.
   Usage (in Blade):
     <div data-animate data-delay="120">…</div>      fade + rise
     <div data-animate="pop" data-delay="200">…</div> scale pop
     <span data-countup="100">0</span>               coin count-up
     <div class="hero-glow-coin" data-float>…</div>   gentle float
   ============================================================ */
(function () {
    'use strict';

    var EarnPlus = {};

    function animeReady() {
        return typeof window.anime === 'function';
    }

    /**
     * Page entrance: staggered fade + rise for [data-animate],
     * scale pop for [data-animate="pop"]. Respects data-delay (ms).
     */
    EarnPlus.initPage = function () {
        if (!animeReady()) {
            document.documentElement.classList.add('no-anime');
            return;
        }

        var items = document.querySelectorAll('[data-animate]');
        items.forEach(function (el) {
            var kind = el.getAttribute('data-animate') || 'rise';
            var delay = parseInt(el.getAttribute('data-delay') || '0', 10);

            if (kind === 'pop') {
                anime({
                    targets: el,
                    opacity: [0, 1],
                    scale: [0.85, 1],
                    duration: 600,
                    delay: delay,
                    easing: 'easeOutBack',
                });
            } else {
                anime({
                    targets: el,
                    opacity: [0, 1],
                    translateY: [28, 0],
                    duration: 650,
                    delay: delay,
                    easing: 'easeOutCubic',
                });
            }
        });
    };

    /**
     * Animated count-up for coin balances / stats.
     * el: element whose data-countup holds the target number.
     */
    EarnPlus.countUp = function (el, target, duration) {
        if (!animeReady()) {
            el.textContent = Number(target).toLocaleString('en-US');
            return;
        }

        var state = { value: 0 };
        anime({
            targets: state,
            value: Number(target),
            duration: duration || 1400,
            delay: parseInt(el.getAttribute('data-delay') || '0', 10),
            easing: 'easeOutExpo',
            update: function () {
                el.textContent = Math.round(state.value).toLocaleString('en-US');
            },
        });
    };

    EarnPlus.initCountUps = function () {
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            EarnPlus.countUp(el, el.getAttribute('data-countup'));
        });
    };

    /**
     * Gentle infinite float for hero decorations (transform only).
     */
    EarnPlus.initFloat = function () {
        if (!animeReady()) {
            return;
        }

        document.querySelectorAll('[data-float]').forEach(function (el, i) {
            anime({
                targets: el,
                translateY: [-10, 10],
                duration: 2600 + i * 300,
                direction: 'alternate',
                loop: true,
                easing: 'easeInOutSine',
            });
        });
    };

    /**
     * Button press feedback: subtle scale on pointer down.
     */
    EarnPlus.initPressFeedback = function () {
        document.addEventListener('pointerdown', function (e) {
            var btn = e.target.closest('.btn');
            if (btn) {
                btn.classList.add('btn-pressing');
            }
        });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
            document.addEventListener(evt, function (e) {
                var btn = e.target.closest ? e.target.closest('.btn') : null;
                if (btn) {
                    btn.classList.remove('btn-pressing');
                } else {
                    document.querySelectorAll('.btn-pressing').forEach(function (b) {
                        b.classList.remove('btn-pressing');
                    });
                }
            });
        });
    };

    /**
     * Swap a skeleton placeholder for real content with a fade.
     * container: element holding .skeleton children.
     * render: callback that fills container.innerHTML.
     */
    EarnPlus.resolveSkeleton = function (container, render) {
        render();
        if (animeReady()) {
            anime({
                targets: container,
                opacity: [0.35, 1],
                duration: 350,
                easing: 'easeOutCubic',
            });
        }
    };

    window.EarnPlus = EarnPlus;

    /**
     * Live countdowns for promotion banners: elements carrying
     * data-countdown-to="ISO-8601" tick every second until the end.
     * The end timestamp is server-rendered, so no client clock trust.
     */
    EarnPlus.initCountdowns = function () {
        var els = document.querySelectorAll('[data-countdown-to]');
        if (!els.length) {
            return;
        }

        function pad(n) {
            return (n < 10 ? '0' : '') + n;
        }

        function render() {
            var now = Date.now();
            els.forEach(function (el) {
                var timeEl = el.querySelector('.ep-promo-countdown-time') || el;
                var target = Date.parse(el.getAttribute('data-countdown-to'));
                if (isNaN(target)) {
                    return;
                }
                var diff = Math.max(0, target - now);
                var s = Math.floor(diff / 1000);
                var d = Math.floor(s / 86400);
                var h = Math.floor((s % 86400) / 3600);
                var m = Math.floor((s % 3600) / 60);
                var sec = s % 60;
                var text = d > 0
                    ? d + 'd ' + pad(h) + 'h ' + pad(m) + 'm'
                    : (h > 0 ? pad(h) + 'h ' + pad(m) + 'm ' : '') + pad(m) + 'm ' + pad(sec) + 's';
                if (timeEl.textContent !== text) {
                    timeEl.textContent = text;
                }
            });
        }

        render();
        setInterval(render, 1000);
    };

    document.addEventListener('DOMContentLoaded', function () {
        EarnPlus.initPage();
        EarnPlus.initCountUps();
        EarnPlus.initFloat();
        EarnPlus.initPressFeedback();
        EarnPlus.initCountdowns();
        EarnPlus.initConfirmDelegates();
    });

    // Phase 10 (CSP): replaces inline onclick="return confirm(…)" /
    // onsubmit="return confirm(…)" handlers. Any element with
    // data-confirm="Message" asks for confirmation before its click
    // proceeds or its form submits.
    EarnPlus.initConfirmDelegates = function () {
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-confirm]');
            if (!el) return;
            var form = el.closest('form');
            // For submit buttons, let the form's submit handler decide once.
            if (form && (el.type === 'submit' || el.tagName === 'BUTTON')) return;
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.hasAttribute || !form.hasAttribute('data-confirm')) return;
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    };
})();
