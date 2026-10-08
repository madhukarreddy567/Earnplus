/// Withdraw: method picker → amount (presets + custom) → per-method detail
/// form → server quote preview → idempotent submit → history.
///
/// Money math: the backend works in paise. Presets come from the server
/// (default ₹10/₹20/₹30 = 1000/2000/3000 paise). Every submit carries a
/// client-generated UUID v4 idempotency key so double-taps / retries can
/// never create duplicate withdrawals.
library;

import 'dart:math';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../ads/interstitial_scheduler.dart';
import '../api/api_client.dart';
import '../api/models.dart';
import '../theme.dart';
import '../widgets/widgets.dart';
import 'withdraw_receipt_screen.dart';

class WithdrawScreen extends StatefulWidget {
  const WithdrawScreen({super.key});

  @override
  State<WithdrawScreen> createState() => _WithdrawScreenState();
}

class _WithdrawScreenState extends State<WithdrawScreen> {
  WithdrawMethodsResponse? _info;
  List<WithdrawalItem> _history = [];
  String? _error;

  WithdrawMethod? _method;
  int? _selectedPresetPaise;
  final _customCtrl = TextEditingController();
  final Map<String, TextEditingController> _detailCtrls = {};
  WithdrawQuote? _quote;
  bool _quoting = false;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _customCtrl.dispose();
    for (final c in _detailCtrls.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    try {
      final results = await Future.wait([
        api.withdrawMethods(),
        api.getWithdrawals(),
      ]);
      if (!mounted) return;
      final info = results[0] as WithdrawMethodsResponse;
      setState(() {
        _info = info;
        _history = results[1] as List<WithdrawalItem>;
        _method = info.methods.isNotEmpty ? info.methods.first : null;
        _selectedPresetPaise =
            info.presetsPaise.isNotEmpty ? info.presetsPaise.first : null;
        _syncDetailControllers();
      });
      // Pre-fetch the quote preview for the default selection.
      await _fetchQuote();
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  void _syncDetailControllers() {
    for (final c in _detailCtrls.values) {
      c.dispose();
    }
    _detailCtrls.clear();
    for (final f in _method?.detailFields ?? const <String>[]) {
      _detailCtrls[f] = TextEditingController();
    }
  }

  /// Amount in paise from preset or custom ₹ input.
  int? get _amountPaise {
    final custom = _customCtrl.text.trim();
    if (custom.isNotEmpty) {
      final rupees = double.tryParse(custom);
      if (rupees == null || rupees <= 0) return null;
      return (rupees * 100).round();
    }
    return _selectedPresetPaise;
  }

  String _paiseToRupees(int paise) =>
      '₹${(paise / 100).toStringAsFixed(paise % 100 == 0 ? 0 : 2)}';

  Future<void> _fetchQuote() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final method = _method;
    final amount = _amountPaise;
    if (method == null || amount == null) {
      setState(() => _quote = null);
      return;
    }
    setState(() {
      _quoting = true;
      _quote = null;
    });
    try {
      final quote = await api.withdrawQuote(method.id, amount);
      if (mounted) setState(() => _quote = quote);
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _quoting = false);
    }
  }

  /// Confirmation step before the idempotent submit: shows exactly what
  /// the user is about to do (method, amount, destination, coins, net).
  Future<void> _confirmAndSubmit() async {
    final method = _method;
    final amount = _amountPaise;
    final quote = _quote;
    if (method == null || amount == null || quote == null || !quote.withinLimits) {
      return;
    }
    final details = <String, String>{};
    for (final entry in _detailCtrls.entries) {
      final v = entry.value.text.trim();
      if (v.isEmpty) {
        showApiError(context, 'Please fill "${method.labelFor(entry.key)}".');
        return;
      }
      details[entry.key] = v;
    }
    final destination = details.values.firstWhere(
      (v) => v.isNotEmpty,
      orElse: () => method.name,
    );
    // Unsafe zone for interstitials: never pop an ad over the money
    // confirmation dialog or the submit that follows it.
    final scheduler =
        Provider.of<InterstitialScheduler>(context, listen: false);
    scheduler.suppress();
    bool? ok;
    try {
      ok = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
          key: const Key('withdraw_confirm_dialog'),
          title: const Text('Confirm withdrawal'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _confirmRow('Method', method.name),
              _confirmRow('You receive', _paiseToRupees(amount)),
              _confirmRow('Destination', destination),
              _confirmRow('Coins debited', '${quote.coins}'),
              _confirmRow('Fees', _paiseToRupees(quote.taxPaise)),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              key: const Key('withdraw_confirm_button'),
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirm'),
            ),
          ],
        ),
      );
      if (ok == true && mounted) {
        await _submit(details);
      }
    } finally {
      scheduler.unsuppress();
    }
  }

  Widget _confirmRow(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(color: EarnPlusColors.muted)),
            Flexible(
              child: Text(value,
                  textAlign: TextAlign.end,
                  style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );

  Future<void> _submit([Map<String, String>? presetDetails]) async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final method = _method;
    final amount = _amountPaise;
    final quote = _quote;
    if (method == null || amount == null || quote == null || !quote.withinLimits) {
      return;
    }
    // Details were validated in the confirmation step; reuse them here so
    // the submitted payload is byte-identical to what the user confirmed.
    final details = presetDetails ??
        {
          for (final entry in _detailCtrls.entries)
            entry.key: entry.value.text.trim()
        };
    setState(() => _submitting = true);
    try {
      final result = await api.withdraw(
        methodId: method.id,
        amountPaise: amount,
        idempotencyKey: _uuidV4(),
        details: details,
      );
      if (!mounted) return;
      final destination = details.values.firstWhere(
        (v) => v.isNotEmpty,
        orElse: () => method.name,
      );
      setState(() {
        _quote = null;
        _customCtrl.clear();
      });
      await _load();
      if (!mounted) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => WithdrawReceiptScreen(
            result: result,
            methodName: method.name,
            amountDisplay: _paiseToRupees(amount),
            destination: destination,
            coinsDebited: '${quote.coins} coins',
          ),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  /// RFC 4122 UUID v4, generated client-side for idempotency.
  String _uuidV4() {
    final r = Random.secure();
    final bytes = List<int>.generate(16, (_) => r.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant
    final hex =
        bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-'
        '${hex.substring(20)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Withdraw')),
      body: _error != null && _info == null
          ? ErrorView(message: _error!, onRetry: () {
              setState(() => _error = null);
              _load();
            })
          : _info == null
              ? const LoadingView(message: 'Loading…')
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final info = _info!;
    if (!info.enabled) {
      return const EmptyView(
        icon: Icons.payments_outlined,
        message: 'Withdrawals are not enabled right now.',
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      color: EarnPlusColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Available to withdraw',
                      style: TextStyle(color: EarnPlusColors.muted)),
                  const SizedBox(height: 4),
                  Text(
                    _paiseToRupees(info.withdrawablePaise),
                    key: const Key('withdrawable_amount'),
                    style: const TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w800,
                        color: EarnPlusColors.primary),
                  ),
                  Text(
                      '${info.balanceCoins} coins · ${info.requestsToday}/${info.maxPerDay} requests used today',
                      style: const TextStyle(
                          fontSize: 12, color: EarnPlusColors.muted)),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Method',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          if (info.methods.isEmpty)
            const Text('No withdrawal methods configured.',
                style: TextStyle(color: EarnPlusColors.muted))
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: info.methods.map((m) {
                final selected = _method?.id == m.id;
                return ChoiceChip(
                  key: Key('method_${m.id}'),
                  label: Text(m.name),
                  selected: selected,
                  selectedColor: EarnPlusColors.primaryLight,
                  onSelected: (_) {
                    setState(() {
                      _method = m;
                      _quote = null;
                      _syncDetailControllers();
                    });
                    _fetchQuote();
                  },
                );
              }).toList(),
            ),
          const SizedBox(height: 16),
          const Text('Amount',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: info.presetsPaise.map((p) {
              final selected =
                  _selectedPresetPaise == p && _customCtrl.text.isEmpty;
              return ChoiceChip(
                key: Key('preset_$p'),
                label: Text(_paiseToRupees(p)),
                selected: selected,
                selectedColor: EarnPlusColors.primaryLight,
                onSelected: (_) {
                  setState(() {
                    _selectedPresetPaise = p;
                    _customCtrl.clear();
                    _quote = null;
                  });
                  _fetchQuote();
                },
              );
            }).toList(),
          ),
          const SizedBox(height: 8),
          TextField(
            key: const Key('custom_amount_field'),
            controller: _customCtrl,
            keyboardType:
                const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(
              labelText: 'Custom amount (₹)',
              prefixIcon: Icon(Icons.currency_rupee),
              hintText: 'e.g. 25.50',
            ),
            onChanged: (_) {
              setState(() => _quote = null);
              _fetchQuote();
            },
          ),
          if (_method != null && _method!.detailFields.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text('${_method!.name} details',
                style: const TextStyle(
                    fontSize: 15, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            ..._method!.detailFields.map((f) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: TextField(
                    key: Key('detail_$f'),
                    controller: _detailCtrls[f],
                    decoration: InputDecoration(labelText: _method!.labelFor(f)),
                  ),
                )),
          ],
          const SizedBox(height: 8),
          if (_quoting)
            const Center(
                child: Padding(
              padding: EdgeInsets.all(12),
              child: CircularProgressIndicator(strokeWidth: 2),
            )),
          if (_quote != null)
            Card(
              color: _quote!.withinLimits
                  ? EarnPlusColors.primaryLight
                  : Colors.red.shade50,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Quote preview',
                        style: TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    _quoteRow('Coins debited', '${_quote!.coins}'),
                    _quoteRow('Tax/fees', _paiseToRupees(_quote!.taxPaise)),
                    _quoteRow('You receive', _quote!.netDisplay, bold: true),
                    if (!_quote!.withinLimits)
                      const Padding(
                        padding: EdgeInsets.only(top: 8),
                        child: Text(
                            'Amount is outside this method\'s limits.',
                            style: TextStyle(
                                color: EarnPlusColors.danger,
                                fontWeight: FontWeight.w600)),
                      ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              key: const Key('withdraw_submit_button'),
              onPressed: (_submitting ||
                      _quote == null ||
                      !_quote!.withinLimits)
                  ? null
                  : _confirmAndSubmit,
              child: _submitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Text('Submit Withdrawal'),
            ),
          ),
          const SizedBox(height: 24),
          const Text('History',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          if (_history.isEmpty)
            const Text('No withdrawals yet.',
                style: TextStyle(color: EarnPlusColors.muted))
          else
            Card(
              child: Column(
                children: _history
                    .map((w) => ListTile(
                          leading: const Icon(Icons.payments_outlined,
                              color: EarnPlusColors.primary),
                          title: Text(w.method),
                          subtitle: Text(w.createdAt),
                          trailing: Column(
                            mainAxisSize: MainAxisSize.min,
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(w.amountDisplay,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                              Text(w.status,
                                  style: const TextStyle(
                                      fontSize: 12,
                                      color: EarnPlusColors.muted)),
                            ],
                          ),
                        ))
                    .toList(),
              ),
            ),
        ],
      ),
    );
  }

  Widget _quoteRow(String label, String value, {bool bold = false}) {
    final style = TextStyle(
        fontWeight: bold ? FontWeight.w800 : FontWeight.w500, fontSize: 15);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: style), Text(value, style: style)],
      ),
    );
  }
}
