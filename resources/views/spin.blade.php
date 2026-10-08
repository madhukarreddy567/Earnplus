<?php $title = 'Spin wheel'; ?>
@extends('layouts.app')

@php
    // Wheel geometry: angle 0 = top, increasing clockwise.
    $cx = 200; $cy = 200; $r = 190;
    $n = count($segments);
    $segAngle = 360 / $n;
    $palette = ['#f5b301', '#101828'];
    $textPalette = ['#3a2a00', '#ffffff'];

    $point = function (float $angleDeg, float $radius) use ($cx, $cy) {
        $rad = deg2rad($angleDeg);
        return [$cx + $radius * sin($rad), $cy - $radius * cos($rad)];
    };
@endphp

@section('content')
<div class="px-3 pt-3">
    <div class="ep-wheel-stage text-center" data-animate>
        <h1 class="h4 fw-bold mb-1">🎡 Spin wheel</h1>
        <p class="text-muted small mb-3" data-animate data-delay="120">
            Spins left today: <strong id="spinsLeft">{{ $spinsLeft }}</strong> of {{ $dailyLimit }}
        </p>

        <div class="ep-wheel-wrap" data-animate="pop" data-delay="180">
            <div class="ep-pointer">▼</div>
                <svg id="wheel" viewBox="0 0 400 400" class="ep-wheel" role="img" aria-label="Prize wheel">
                    @for ($i = 0; $i < $n; $i++)
                        @php
                            $a0 = $i * $segAngle;
                            $a1 = ($i + 1) * $segAngle;
                            [$x0, $y0] = $point($a0, $r);
                            [$x1, $y1] = $point($a1, $r);
                            $fill = $palette[$i % 2];
                            $tcol = $textPalette[$i % 2];
                            $mid = $a0 + $segAngle / 2;
                            [$tx, $ty] = $point($mid, $r * 0.62);
                            $rot = $mid > 90 && $mid < 270 ? $mid + 180 : $mid;
                        @endphp
                        <path d="M {{ $cx }} {{ $cy }} L {{ round($x0, 1) }} {{ round($y0, 1) }} A {{ $r }} {{ $r }} 0 0 1 {{ round($x1, 1) }} {{ round($y1, 1) }} Z"
                            fill="{{ $fill }}" stroke="#ffffff" stroke-width="2" />
                        <text x="{{ round($tx, 1) }}" y="{{ round($ty, 1) }}"
                            fill="{{ $tcol }}" font-size="22" font-weight="800" text-anchor="middle"
                            dominant-baseline="middle"
                            transform="rotate({{ round($rot, 1) }} {{ round($tx, 1) }} {{ round($ty, 1) }})">{{ $segments[$i] }}</text>
                    @endfor
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="34" fill="#ffffff" stroke="#f5b301" stroke-width="4" />
                    <text x="{{ $cx }}" y="{{ $cy }}" text-anchor="middle" dominant-baseline="middle" font-size="30">🪙</text>
                </svg>
            </div>

            <button id="spinBtn" class="btn btn-primary btn-lg px-5 py-2 fw-bold mt-4" data-animate data-delay="240"
                {{ $spinsLeft <= 0 ? 'disabled' : '' }}>
                {{ $spinsLeft > 0 ? 'SPIN' : 'No spins left' }}
            </button>

            <div class="form-check form-switch d-inline-flex align-items-center gap-2 mt-3 ms-3">
                <input class="form-check-input" type="checkbox" id="soundToggle">
                <label class="form-check-label text-muted small" for="soundToggle">Tick sound</label>
            </div>

            <div id="spinResult" class="ep-result mt-4" hidden></div>

            <div class="mt-4">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm rounded-pill">← Back to dashboard</a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script nonce="{{ csp_nonce() }}">
(function () {
    'use strict';

    var wheel = document.getElementById('wheel');
    var btn = document.getElementById('spinBtn');
    var resultBox = document.getElementById('spinResult');
    var spinsLeftEl = document.getElementById('spinsLeft');
    var segments = @json($segments);
    var segAngle = 360 / segments.length;
    var rotation = 0;
    var spinning = false;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var soundOn = false;
    document.getElementById('soundToggle').addEventListener('change', function (e) {
        soundOn = e.target.checked;
    });
    var audioCtx = null;
    function tick() {
        if (!soundOn) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            var o = audioCtx.createOscillator();
            var g = audioCtx.createGain();
            o.type = 'square';
            o.frequency.value = 1400;
            g.gain.setValueAtTime(0.04, audioCtx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.05);
            o.connect(g).connect(audioCtx.destination);
            o.start();
            o.stop(audioCtx.currentTime + 0.06);
        } catch (e) { /* audio unavailable — spin continues silently */ }
    }

    function fingerprint() {
        var key = 'ep_fp';
        var fp = localStorage.getItem(key);
        if (fp) return fp;
        var raw = [navigator.userAgent, navigator.language, screen.width + 'x' + screen.height,
            new Date().getTimezoneOffset()].join('|');
        var h = 0;
        for (var i = 0; i < raw.length; i++) {
            h = ((h << 5) - h + raw.charCodeAt(i)) | 0;
        }
        fp = 'fp_' + (h >>> 0).toString(16);
        localStorage.setItem(key, fp);
        return fp;
    }

    function showResult(won, amount) {
        resultBox.hidden = false;
        if (won) {
            resultBox.className = 'ep-result ep-result-win mt-4';
            resultBox.innerHTML = '<div class="ep-result-amount">+' + amount + ' 🪙</div><div>You won ' + amount + ' coins!</div>';
        } else {
            resultBox.className = 'ep-result ep-result-lose mt-4';
            resultBox.innerHTML = '<div class="ep-result-amount">😅</div><div>No luck this time — try again tomorrow!</div>';
        }
        if (window.anime) {
            anime({ targets: resultBox, scale: [0.8, 1], opacity: [0, 1], duration: 500, easing: 'easeOutBack' });
        }
    }

    function landOn(segmentIndex) {
        // Segment i is centred at (i*seg + seg/2) degrees clockwise from top.
        // Rotating the wheel clockwise by R moves that point to (center + R);
        // we want it at the top pointer (0°), so R ≡ (360 − center) mod 360.
        var center = segmentIndex * segAngle + segAngle / 2;
        var desiredMod = ((360 - (center % 360)) % 360 + 360) % 360;
        var currentMod = ((rotation % 360) + 360) % 360;
        var delta = (desiredMod - currentMod + 360) % 360;
        return rotation + 360 * 6 + delta;
    }

    btn.addEventListener('click', function () {
        if (spinning) return;
        spinning = true;
        btn.disabled = true;
        btn.textContent = 'Spinning…';
        resultBox.hidden = true;

        fetch('{{ route('spin.play') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ device_fingerprint: fingerprint() }),
        })
            .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
            .then(function (res) {
                if (res.status !== 200) {
                    throw new Error((res.body && res.body.error) || 'Spin failed. Please try again.');
                }
                var data = res.body;
                // Server decides everything; a missing segment on a loss
                // just lands the wheel on a random segment.
                var idx = data.segment_index === null || data.segment_index === undefined
                    ? Math.floor(Math.random() * segments.length)
                    : data.segment_index;
                var finalRotation = landOn(idx);
                var lastSeg = Math.floor((((rotation % 360) + 360) % 360) / segAngle);

                var dur = reduceMotion ? 300 : 4600;
                anime({
                    targets: wheel,
                    rotate: finalRotation,
                    duration: dur,
                    easing: 'easeOutQuint',
                    update: function () {
                        var m = anime.get(wheel, 'rotate');
                        var s = Math.floor((((m % 360) + 360) % 360) / segAngle);
                        if (s !== lastSeg) { lastSeg = s; tick(); }
                    },
                    complete: function () {
                        rotation = finalRotation;
                        spinning = false;
                        var left = data.spins_left;
                        spinsLeftEl.textContent = left;
                        if (left > 0) {
                            btn.disabled = false;
                            btn.textContent = 'SPIN';
                        } else {
                            btn.textContent = 'No spins left';
                        }
                        showResult(data.won, data.amount);
                    },
                });
            })
            .catch(function (err) {
                spinning = false;
                btn.disabled = false;
                btn.textContent = 'SPIN';
                resultBox.hidden = false;
                resultBox.className = 'ep-result ep-result-lose mt-4';
                resultBox.textContent = err.message;
            });
    });
})();
</script>
@endpush
