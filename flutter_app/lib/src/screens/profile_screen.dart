/// Profile: user info + logout.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../auth/auth_service.dart';
import '../services/kyc_service.dart';
import '../services/notification_service.dart';
import '../theme.dart';
import 'help_screen.dart';
import 'kyc_screen.dart';
import 'login_screen.dart';
import 'notifications_screen.dart';
import 'promotions_screen.dart';

class ProfileScreen extends StatefulWidget {
  final bool embedded;
  const ProfileScreen({super.key, this.embedded = false});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _loggingOut = false;

  Future<void> _logout() async {
    final auth = Provider.of<AuthService>(context, listen: false);
    final confirm = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text('You will need to sign in again.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Log out')),
        ],
      ),
    );
    if (confirm != true || !mounted) return;
    setState(() => _loggingOut = true);
    await auth.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  Widget _menuTile(
    BuildContext context, {
    Key? key,
    required IconData icon,
    required String label,
    Widget? trailing,
    required VoidCallback onTap,
  }) =>
      ListTile(
        key: key,
        leading: Icon(icon, color: EarnPlusColors.primary),
        title: Text(label),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (trailing != null) trailing,
            const Icon(Icons.chevron_right, color: EarnPlusColors.muted),
          ],
        ),
        onTap: onTap,
      );

  Widget _unreadBadge(BuildContext context) {
    final unread = context.watch<NotificationService>().unreadCount;
    if (unread == 0) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.only(right: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: const BoxDecoration(
        color: EarnPlusColors.danger,
        borderRadius: BorderRadius.all(Radius.circular(999)),
      ),
      child: Text(
        '$unread',
        style: const TextStyle(
            fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white),
      ),
    );
  }

  Widget _kycChip(BuildContext context) {
    final status = context.watch<KycService>().status;
    final label = switch (status) {
      KycStatus.verified => 'Verified',
      KycStatus.underReview => 'Under review',
      KycStatus.draft => 'Continue',
      KycStatus.rejected => 'Resubmit',
      KycStatus.notStarted => 'Start',
    };
    final color = switch (status) {
      KycStatus.verified => EarnPlusColors.success,
      KycStatus.underReview => EarnPlusColors.accent,
      _ => EarnPlusColors.muted,
    };
    return Container(
      margin: const EdgeInsets.only(right: 6),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: const BorderRadius.all(Radius.circular(999)),
      ),
      child: Text(
        label,
        style: TextStyle(
            fontSize: 11, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthService>().user;

    final body = Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          const SizedBox(height: 12),
          CircleAvatar(
            radius: 48,
            backgroundColor: EarnPlusColors.primaryLight,
            backgroundImage: user?.avatar != null && user!.avatar!.isNotEmpty
                ? NetworkImage(user.avatar!)
                : null,
            child: user?.avatar == null || user!.avatar!.isEmpty
                ? Text(
                    (user?.name.isNotEmpty == true
                            ? user!.name[0]
                            : 'U')
                        .toUpperCase(),
                    style: const TextStyle(
                        fontSize: 40,
                        fontWeight: FontWeight.w800,
                        color: EarnPlusColors.primary),
                  )
                : null,
          ),
          const SizedBox(height: 12),
          Text(
            user?.name ?? 'User',
            key: const Key('profile_name'),
            style:
                const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
          if ((user?.email ?? '').isNotEmpty)
            Text(user!.email,
                style: const TextStyle(color: EarnPlusColors.muted)),
          const SizedBox(height: 20),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.monetization_on_outlined,
                      color: EarnPlusColors.primary),
                  title: const Text('Coins'),
                  trailing: Text('${user?.coins ?? 0}',
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.currency_rupee,
                      color: EarnPlusColors.primary),
                  title: const Text('Rupees'),
                  trailing: Text('₹${(user?.rupees ?? 0).toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.card_giftcard_outlined,
                      color: EarnPlusColors.primary),
                  title: const Text('Referral code'),
                  trailing: Text(user?.referralCode ?? '—',
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          Card(
            child: Column(
              children: [
                _menuTile(
                  context,
                  key: const Key('menu_notifications'),
                  icon: Icons.notifications_outlined,
                  label: 'Notifications',
                  trailing: _unreadBadge(context),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                        builder: (_) => const NotificationsScreen()),
                  ),
                ),
                const Divider(height: 1),
                _menuTile(
                  context,
                  key: const Key('menu_kyc'),
                  icon: Icons.verified_user_outlined,
                  label: 'KYC Verification',
                  trailing: _kycChip(context),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                        builder: (_) => const KycScreen()),
                  ),
                ),
                const Divider(height: 1),
                _menuTile(
                  context,
                  key: const Key('menu_promotions'),
                  icon: Icons.local_offer_outlined,
                  label: 'Promotions',
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                        builder: (_) => const PromotionsScreen()),
                  ),
                ),
                const Divider(height: 1),
                _menuTile(
                  context,
                  key: const Key('menu_help'),
                  icon: Icons.help_outline,
                  label: 'Help & Policies',
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(
                        builder: (_) => const HelpScreen()),
                  ),
                ),
              ],
            ),
          ),
          const Spacer(),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              key: const Key('logout_button'),
              onPressed: _loggingOut ? null : _logout,
              icon: const Icon(Icons.logout, color: EarnPlusColors.danger),
              label: Text(
                _loggingOut ? 'Logging out…' : 'Log Out',
                style: const TextStyle(color: EarnPlusColors.danger),
              ),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: EarnPlusColors.danger),
              ),
            ),
          ),
        ],
      ),
    );

    if (widget.embedded) return body;
    return Scaffold(appBar: AppBar(title: const Text('Profile')), body: body);
  }
}
