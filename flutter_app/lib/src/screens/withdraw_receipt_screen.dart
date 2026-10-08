/// Withdrawal confirmation receipt.
///
/// Shown after POST /api/withdraw succeeds. Everything displayed comes
/// from the server response plus the exact values the user submitted —
/// the app never invents amounts or statuses. UPI-first: the destination
/// UPI ID / account is shown prominently. Stitch design language.
library;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../ads/interstitial_scheduler.dart';
import '../api/models.dart';
import '../stitch/stitch_theme.dart';
import '../stitch/stitch_widgets.dart';

class WithdrawReceiptScreen extends StatefulWidget {
  final WithdrawResult result;
  final String methodName;
  final String amountDisplay;
  final String destination;
  final String coinsDebited;

  const WithdrawReceiptScreen({
    super.key,
    required this.result,
    required this.methodName,
    required this.amountDisplay,
    required this.destination,
    required this.coinsDebited,
  });

  @override
  State<WithdrawReceiptScreen> createState() => _WithdrawReceiptScreenState();
}

class _WithdrawReceiptScreenState extends State<WithdrawReceiptScreen> {
  late final InterstitialScheduler _scheduler;

  @override
  void initState() {
    super.initState();
    // Unsafe zone for interstitials: never pop an ad over the receipt.
    // Capture now — ancestor lookup is unsafe in dispose().
    _scheduler = Provider.of<InterstitialScheduler>(context, listen: false);
    _scheduler.suppress();
  }

  @override
  void dispose() {
    _scheduler.unsuppress();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final now = DateFormat('d MMM yyyy, h:mm a').format(DateTime.now());
    final result = widget.result;
    final methodName = widget.methodName;
    final amountDisplay = widget.amountDisplay;
    final destination = widget.destination;
    final coinsDebited = widget.coinsDebited;
    return Scaffold(
      backgroundColor: StitchColors.background,
      appBar: stitchAppBar('Withdrawal Receipt'),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const SizedBox(height: 8),
          Center(
            child: Container(
              width: 88,
              height: 88,
              decoration: const BoxDecoration(
                color: StitchColors.primarySoft,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check_circle,
                  size: 52, color: StitchColors.primaryDark),
            ),
          ),
          const SizedBox(height: 16),
          Text(
            result.message.isNotEmpty
                ? result.message
                : 'Withdrawal request submitted!',
            key: const Key('receipt_message'),
            textAlign: TextAlign.center,
            style: StitchText.headline.copyWith(fontSize: 20),
          ),
          const SizedBox(height: 6),
          Text(
            'Your money is on its way. Track it under Withdraw → History.',
            textAlign: TextAlign.center,
            style: StitchText.caption,
          ),
          const SizedBox(height: 20),
          Container(
            decoration: stitchCardDecoration(),
            padding: const EdgeInsets.all(18),
            child: Column(
              children: [
                Text(amountDisplay,
                    key: const Key('receipt_amount'),
                    style: const TextStyle(
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                        color: StitchColors.neutral)),
                const SizedBox(height: 4),
                Text('via $methodName', style: StitchText.caption),
                const Divider(height: 28),
                _row('Destination', destination),
                _row('Coins debited', coinsDebited),
                _row('Request ID', '#${result.id}'),
                _row('Status', result.status.isNotEmpty ? result.status : 'pending'),
                _row('Date', now),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const StitchTrustBadges(badges: [
            (Icons.lock_outline, 'Secure payout'),
            (Icons.schedule_outlined, 'Reviewed before release'),
          ]),
          const SizedBox(height: 24),
          stitchPrimaryButton(
            key: const Key('receipt_done'),
            label: 'Done',
            onPressed: () => Navigator.of(context)
                .popUntil((r) => r.isFirst),
          ),
        ],
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: StitchText.caption),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: StitchColors.neutral),
              ),
            ),
          ],
        ),
      );
}
