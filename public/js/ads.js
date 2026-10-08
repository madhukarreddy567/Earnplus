/**
 * EarnPlus rewarded ads (public/js/ads.js).
 *
 * Drives the "Watch ad & earn" card: start → countdown (anime.js) →
 * claim via fetch → success burst. The countdown is cosmetic — the
 * server (RewardedAdService) re-verifies placement state, daily limits,
 * minimum intervals and abuse checks on every claim.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showStep(card, name) {
        card.querySelectorAll('[data-reward-step]').forEach(function (el) {
            el.classList.toggle('d-none', el.getAttribute('data-reward-step') !== name);
        });
    }

    function showError(card, message) {
        showStep(card, 'error');
        var box = card.querySelector('[data-reward-error]');
        if (box) {
            box.textContent = message;
        }
    }

    function initRewardCard(card) {
        var claimUrl = card.getAttribute('data-claim-url');
        var countdown = Math.max(1, parseInt(card.getAttribute('data-countdown') || '15', 10));
        var startBtn = card.querySelector('[data-reward-start]');
        var claimBtn = card.querySelector('[data-reward-claim]');
        var progress = card.querySelector('[data-reward-progress]');
        var secondsLabel = card.querySelector('[data-reward-seconds]');
        var timer = null;

        if (startBtn) {
            startBtn.addEventListener('click', function () {
                showStep(card, 'watch');

                var remaining = countdown;
                if (secondsLabel) {
                    secondsLabel.textContent = remaining + 's';
                }

                // Smooth progress fill (transform only — 60fps).
                if (window.anime && progress) {
                    anime({
                        targets: progress,
                        scaleX: [0, 1],
                        duration: countdown * 1000,
                        easing: 'linear',
                    });
                } else if (progress) {
                    progress.style.transform = 'scaleX(1)';
                    progress.style.transition = 'transform ' + countdown + 's linear';
                }

                timer = setInterval(function () {
                    remaining -= 1;
                    if (secondsLabel) {
                        secondsLabel.textContent = Math.max(remaining, 0) + 's';
                    }
                    if (remaining <= 0) {
                        clearInterval(timer);
                        timer = null;
                        showStep(card, 'claim');
                    }
                }, 1000);
            });
        }

        if (claimBtn) {
            claimBtn.addEventListener('click', function () {
                claimBtn.disabled = true;
                claimBtn.textContent = 'Claiming…';

                fetch(claimUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { status: response.status, body: body };
                        });
                    })
                    .then(function (result) {
                        if (result.status === 200) {
                            var earned = card.querySelector('[data-reward-earned]');
                            var balance = card.querySelector('[data-reward-balance]');
                            if (earned) {
                                earned.textContent = result.body.coins;
                            }
                            if (balance) {
                                balance.textContent = result.body.balance;
                            }
                            showStep(card, 'done');

                            // Coin burst pop.
                            var burst = card.querySelector('[data-reward-burst]');
                            if (window.anime && burst) {
                                anime({
                                    targets: burst,
                                    scale: [0.4, 1.25, 1],
                                    rotate: [0, 18, 0],
                                    duration: 700,
                                    easing: 'easeOutBack',
                                });
                            }
                        } else {
                            showError(card, (result.body && result.body.message) || 'Could not claim the reward. Please try again.');
                        }
                    })
                    .catch(function () {
                        showError(card, 'Network error — please check your connection and try again.');
                    })
                    .finally(function () {
                        claimBtn.disabled = false;
                        claimBtn.textContent = 'Claim coins';
                    });
            });
        }
    }

    /**
     * Sidebar rail: the aside starts hidden (d-none). Reveal it only when
     * a placement actually rendered (the .ep-ad wrapper), so an empty slot
     * never flashes or reserves space. d-xl-block still gates it to desktop.
     */
    function initSidebarSlot() {
        document.querySelectorAll('[data-sidebar-ad]').forEach(function (aside) {
            if (aside.querySelector('.ep-ad')) {
                aside.classList.remove('d-none');
            }
        });
    }

    /**
     * Interstitial overlay: shown once per session at most (the server
     * enforces the per-session frequency cap). The overlay stays hidden
     * unless a placement rendered; a short delay keeps it from flashing
     * instantly on every page view.
     */
    function initInterstitial() {
        var overlay = document.getElementById('epInterstitial');
        if (!overlay || !overlay.querySelector('.ep-ad')) {
            return;
        }

        var closeBtn = document.getElementById('epInterstitialClose');

        function hide() {
            overlay.hidden = true;
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', hide);
        }
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                hide();
            }
        });

        setTimeout(function () {
            overlay.hidden = false;
            if (window.anime) {
                anime({
                    targets: overlay,
                    opacity: [0, 1],
                    duration: 350,
                    easing: 'easeOutQuad',
                });
            }
        }, 1200);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-reward-card]').forEach(initRewardCard);
        initSidebarSlot();
        initInterstitial();
    });
})();
