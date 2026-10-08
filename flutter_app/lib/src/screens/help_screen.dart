/// Help & policies.
///
/// Policy pages are served by the Laravel backend at
/// `{baseUrl}/policies/{slug}` (public web routes: terms, privacy, refund,
/// about — latest published version). They open in the existing WebView
/// screen so the user always reads the live, admin-editable copy.
/// Stitch design language.
library;

import 'package:flutter/material.dart';

import '../../config.dart';
import '../stitch/stitch_theme.dart';
import '../stitch/stitch_widgets.dart';
import 'task_webview_screen.dart';

class _PolicyLink {
  final String slug;
  final String title;
  final IconData icon;
  const _PolicyLink(this.slug, this.title, this.icon);
}

const _policies = [
  _PolicyLink('terms', 'Terms of Service', Icons.description_outlined),
  _PolicyLink('privacy', 'Privacy Policy', Icons.privacy_tip_outlined),
  _PolicyLink('refund', 'Refund Policy', Icons.replay_outlined),
  _PolicyLink('about', 'About EarnPlus', Icons.info_outline),
];

class HelpScreen extends StatelessWidget {
  const HelpScreen({super.key});

  void _openPolicy(BuildContext context, _PolicyLink p) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => TaskWebViewScreen(
        title: p.title,
        url: '$apiBaseUrl/policies/${p.slug}',
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StitchColors.background,
      appBar: stitchAppBar('Help & Policies'),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            decoration: stitchCardDecoration(),
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: const BoxDecoration(
                    color: StitchColors.primarySoft,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.support_agent,
                      color: StitchColors.primaryDark, size: 28),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Need help?', style: StitchText.title),
                      SizedBox(height: 4),
                      Text(
                        'Read the policies below. They are the live copies maintained by the EarnPlus team.',
                        style: StitchText.caption,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const StitchSectionHeader(title: 'Policies'),
          const SizedBox(height: 10),
          Container(
            decoration: stitchCardDecoration(),
            child: Column(
              children: [
                for (var i = 0; i < _policies.length; i++) ...[
                  ListTile(
                    key: Key('policy_${_policies[i].slug}'),
                    leading: Icon(_policies[i].icon,
                        color: StitchColors.primaryDark),
                    title: Text(_policies[i].title,
                        style: const TextStyle(
                            fontWeight: FontWeight.w600, fontSize: 15)),
                    trailing: const Icon(Icons.chevron_right,
                        color: StitchColors.muted),
                    onTap: () => _openPolicy(context, _policies[i]),
                  ),
                  if (i < _policies.length - 1)
                    const Divider(height: 1, indent: 56),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          const StitchSectionHeader(title: 'Earning tips'),
          const SizedBox(height: 10),
          Container(
            decoration: stitchCardDecoration(),
            padding: const EdgeInsets.all(18),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Tip('Check in every day to grow your streak bonus.'),
                _Tip('Finish offerwall tasks fully — partial tasks don\'t pay.'),
                _Tip('Withdrawals are reviewed before money is sent.'),
                _Tip(
                    'Never share your login with anyone. EarnPlus staff will never ask for it.'),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Text('EarnPlus v1.0.0',
              textAlign: TextAlign.center, style: StitchText.caption),
        ],
      ),
    );
  }
}

class _Tip extends StatelessWidget {
  final String text;
  const _Tip(this.text);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_circle,
              size: 18, color: StitchColors.primary),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: StitchText.body)),
        ],
      ),
    );
  }
}
