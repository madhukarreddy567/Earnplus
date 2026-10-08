/// Referral: code, share button, referred count.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../theme.dart';
import '../widgets/widgets.dart';

class ReferralScreen extends StatefulWidget {
  final bool embedded;
  const ReferralScreen({super.key, this.embedded = false});

  @override
  State<ReferralScreen> createState() => _ReferralScreenState();
}

class _ReferralScreenState extends State<ReferralScreen> {
  ReferralInfo? _info;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    try {
      final info = await api.getReferral();
      if (mounted) setState(() => _info = info);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _share() async {
    final info = _info;
    if (info == null) return;
    final text = info.shareText.isNotEmpty
        ? info.shareText
        : 'Join EarnPlus with my code ${info.code} and earn coins together!';
    await Share.share(text, subject: 'Join EarnPlus');
  }

  @override
  Widget build(BuildContext context) {
    final body = _error != null && _info == null
        ? ErrorView(message: _error!, onRetry: () {
            setState(() => _error = null);
            _load();
          })
        : _info == null
            ? const LoadingView()
            : _buildBody();

    if (widget.embedded) return body;
    return Scaffold(appBar: AppBar(title: const Text('Refer & Earn')), body: body);
  }

  Widget _buildBody() {
    final info = _info!;
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  const Icon(Icons.group_add,
                      color: EarnPlusColors.primary, size: 56),
                  const SizedBox(height: 12),
                  const Text('Your referral code',
                      style: TextStyle(color: EarnPlusColors.muted)),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 24, vertical: 12),
                    decoration: BoxDecoration(
                      color: EarnPlusColors.primaryLight,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                          color: EarnPlusColors.primary
                              .withValues(alpha: 0.3)),
                    ),
                    child: Text(
                      info.code,
                      key: const Key('referral_code'),
                      style: const TextStyle(
                          fontSize: 30,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 4,
                          color: EarnPlusColors.primary),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _stat('${info.referredCount}', 'Friends joined'),
                      _stat('${info.bonusCoins}', 'Bonus coins'),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              key: const Key('share_button'),
              onPressed: _share,
              icon: const Icon(Icons.share),
              label: const Text('Share & Earn'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _stat(String value, String label) => Column(
        children: [
          Text(value,
              style:
                  const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
          Text(label, style: const TextStyle(color: EarnPlusColors.muted)),
        ],
      );
}
