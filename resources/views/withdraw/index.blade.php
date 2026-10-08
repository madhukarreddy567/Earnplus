<?php $title = 'Withdraw'; ?>
@extends('layouts.app')

@section('content')
<div class="px-3 pt-3 pb-5" style="max-width:520px;margin:0 auto;">
    <h1 class="h4 fw-bold mb-3" data-animate>💸 Withdraw</h1>

    @if (session('success'))
        <div class="alert alert-success rounded-4" data-animate>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger rounded-4" data-animate>{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger rounded-4" data-animate>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $withdrawalsEnabled)
        <div class="ep-card p-4 text-center" data-animate>
            <div class="h5 fw-bold">Withdrawals are paused</div>
            <p class="text-muted small mb-0">Please check back later.</p>
        </div>
    @else
        {{-- Withdrawable balance hero --}}
        <div class="ep-hero-card mb-3" data-animate>
            <div class="ep-hero-label">Withdrawable balance</div>
            <div class="d-flex align-items-center gap-3 mt-1">
                <span class="ep-coin" style="width:52px;height:52px;font-size:1.5rem;" data-float>₹</span>
                <div>
                    <div class="ep-hero-balance"><span data-countup="{{ $balance }}">0</span></div>
                    <div class="ep-hero-sub">≈ <strong>₹{{ number_format($withdrawablePaise / 100, 2) }}</strong></div>
                </div>
            </div>
            <div class="ep-hero-chips">
                <span class="ep-chip">{{ $requestsToday }} of {{ $maxPerDay }} requests used today</span>
            </div>
        </div>

        <form method="POST" action="{{ route('withdraw.store') }}" id="withdrawForm" data-animate data-delay="120">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <input type="hidden" name="amount" id="amountPaise" value="">

            {{-- Method picker --}}
            <div class="fw-bold mb-2">Payout method</div>
            <div class="d-grid gap-2 mb-3" role="radiogroup" aria-label="Payout method">
                @forelse ($methods as $method)
                    <label class="ep-method" data-method-type="{{ $method->type }}" data-currency="{{ $method->currency }}"
                           data-min="{{ $method->min_amount }}" data-max="{{ $method->max_amount }}"
                           data-fields="{{ implode(',', $method->detailFields()) }}">
                        <input type="radio" name="method_id" value="{{ $method->id }}"
                               class="d-none method-radio" {{ $loop->first ? 'checked' : '' }}>
                        <span class="ep-method-radio"></span>
                        <span class="flex-grow-1">
                            <span class="fw-bold d-block">{{ $method->name }}</span>
                            <span class="text-muted small">{{ $method->formatAmount($method->min_amount) }} – {{ $method->formatAmount($method->max_amount) }}</span>
                        </span>
                        @if ($method->isUsd())
                            <span class="badge rounded-pill bg-info text-dark">USD</span>
                        @endif
                    </label>
                @empty
                    <div class="ep-card p-3 text-muted small">No payout methods are enabled right now.</div>
                @endforelse
            </div>

            {{-- Amount --}}
            <div class="fw-bold mb-2">Amount</div>
            <div class="d-flex gap-2 mb-2" id="presetRow">
                @foreach ($presets as $preset)
                    <button type="button" class="btn btn-outline-dark rounded-pill flex-grow-1 preset-btn"
                            data-paise="{{ $preset }}">₹{{ $preset / 100 }}</button>
                @endforeach
            </div>
            <div class="input-group mb-1">
                <span class="input-group-text" id="currencySymbol">₹</span>
                <input type="number" class="form-control" id="amountInput" min="0" step="0.01"
                       placeholder="Custom amount" aria-label="Custom amount">
            </div>
            <div class="text-muted small mb-3" id="limitHint"></div>

            {{-- Live preview --}}
            <div class="ep-card p-3 mb-3 d-none" id="previewCard">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Coins needed</span>
                    <strong id="previewCoins">–</strong>
                </div>
                <div class="d-flex justify-content-between small mb-1" id="taxRow">
                    <span class="text-muted">Tax</span>
                    <strong id="previewTax">–</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">You receive</span>
                    <strong class="text-success" id="previewNet">–</strong>
                </div>
            </div>

            {{-- Payout details (per-method fieldsets) --}}
            <div class="fw-bold mb-2">Payout details</div>
            <div id="detailFields">
                <div class="detail-group mb-2" data-fields-for="upi_id">
                    <label class="form-label small">UPI ID</label>
                    <input type="text" name="details[upi_id]" class="form-control" placeholder="name@bank">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="mobile">
                    <label class="form-label small">Mobile number</label>
                    <input type="text" name="details[mobile]" class="form-control" placeholder="10-digit mobile" inputmode="numeric">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="account_holder">
                    <label class="form-label small">Account holder name</label>
                    <input type="text" name="details[account_holder]" class="form-control">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="account_no">
                    <label class="form-label small">Account number</label>
                    <input type="text" name="details[account_no]" class="form-control" inputmode="numeric">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="ifsc">
                    <label class="form-label small">IFSC code</label>
                    <input type="text" name="details[ifsc]" class="form-control" placeholder="HDFC0001234" style="text-transform:uppercase">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="paypal_name">
                    <label class="form-label small">Full name (PayPal)</label>
                    <input type="text" name="details[paypal_name]" class="form-control">
                </div>
                <div class="detail-group mb-2 d-none" data-fields-for="paypal_email">
                    <label class="form-label small">PayPal e-mail</label>
                    <input type="email" name="details[paypal_email]" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-success btn-lg w-100 rounded-pill mt-2" id="submitBtn" disabled>
                Request withdrawal
            </button>
            <p class="text-muted small text-center mt-2">Coins are debited immediately; payout is reviewed by our team.</p>
        </form>
    @endif

    {{-- History --}}
    <div class="fw-bold mt-4 mb-2" data-animate>My withdrawals</div>
    <div class="d-grid gap-2">
        @forelse ($history as $w)
            <a href="{{ route('withdraw.show', $w) }}" class="text-decoration-none text-dark" data-animate>
                <div class="ep-tx">
                    <div class="ep-tx-icon">💸</div>
                    <div class="ep-tx-meta">
                        <div class="fw-semibold small">{{ $w->method->name }} · {{ $w->netFormatted() }}</div>
                        <div class="text-muted small">{{ $w->requested_at->diffForHumans() }}</div>
                    </div>
                    <span class="badge rounded-pill {{ $w->status === 'completed' ? 'bg-success' : ($w->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">
                        {{ \App\Models\Withdrawal::statusLabel($w->status) }}
                    </span>
                </div>
            </a>
        @empty
            <div class="ep-card p-3 text-muted small text-center" data-animate>No withdrawals yet.</div>
        @endforelse
    </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function () {
    var form = document.getElementById('withdrawForm');
    if (!form) return;

    var methodRadios = Array.prototype.slice.call(document.querySelectorAll('.method-radio'));
    var amountInput = document.getElementById('amountInput');
    var amountPaise = document.getElementById('amountPaise');
    var previewCard = document.getElementById('previewCard');
    var previewCoins = document.getElementById('previewCoins');
    var previewTax = document.getElementById('previewTax');
    var previewNet = document.getElementById('previewNet');
    var submitBtn = document.getElementById('submitBtn');
    var limitHint = document.getElementById('limitHint');
    var currencySymbol = document.getElementById('currencySymbol');
    var presetRow = document.getElementById('presetRow');
    var quoteUrl = '{{ route('withdraw.quote') }}';
    var csrf = '{{ csrf_token() }}';
    var timer = null;

    function selectedMethod() {
        var label = document.querySelector('.method-radio:checked');
        return label ? label.closest('.ep-method') : null;
    }

    function refreshMethodUI() {
        var m = selectedMethod();
        document.querySelectorAll('.ep-method').forEach(function (el) {
            el.classList.toggle('ep-method-active', el === m);
        });
        if (!m) return;
        var isUsd = m.dataset.currency === 'USD';
        currencySymbol.textContent = isUsd ? '$' : '₹';
        presetRow.style.display = isUsd ? 'none' : '';
        limitHint.textContent = 'Limits: ' + m.querySelector('.text-muted').textContent;
        var fields = (m.dataset.fields || '').split(',').filter(Boolean);
        document.querySelectorAll('.detail-group').forEach(function (g) {
            var show = fields.indexOf(g.dataset.fieldsFor) !== -1;
            g.classList.toggle('d-none', !show);
            g.querySelector('input').disabled = !show;
        });
        scheduleQuote();
    }

    function smallestUnit() {
        var m = selectedMethod();
        var val = parseFloat(amountInput.value);
        if (!m || isNaN(val) || val <= 0) return 0;
        return m.dataset.currency === 'USD' ? Math.round(val * 100) : Math.round(val * 100);
    }

    function scheduleQuote() {
        clearTimeout(timer);
        timer = setTimeout(fetchQuote, 350);
    }

    function fetchQuote() {
        var m = selectedMethod();
        var units = smallestUnit();
        if (!m || units <= 0) {
            previewCard.classList.add('d-none');
            submitBtn.disabled = true;
            return;
        }
        var methodId = m.querySelector('.method-radio').value;
        fetch(quoteUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ method_id: parseInt(methodId, 10), amount: units })
        }).then(function (r) { return r.json(); }).then(function (q) {
            amountPaise.value = units;
            previewCoins.textContent = q.coins.toLocaleString('en-IN') + ' coins';
            previewTax.textContent = q.tax;
            document.getElementById('taxRow').style.display = q.tax_paise > 0 ? '' : 'none';
            previewNet.textContent = q.net;
            previewCard.classList.remove('d-none');
            submitBtn.disabled = !q.within_limits;
            submitBtn.textContent = q.within_limits ? 'Request withdrawal' : 'Outside limits (' + q.min + ' – ' + q.max + ')';
        }).catch(function () {
            previewCard.classList.add('d-none');
            submitBtn.disabled = true;
        });
    }

    methodRadios.forEach(function (r) { r.addEventListener('change', refreshMethodUI); });
    amountInput.addEventListener('input', scheduleQuote);
    document.querySelectorAll('.preset-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            amountInput.value = (parseInt(b.dataset.paise, 10) / 100).toFixed(2).replace(/\.00$/, '');
            scheduleQuote();
        });
    });

    refreshMethodUI();
})();
</script>
@endsection
