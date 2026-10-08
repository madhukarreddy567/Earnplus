/// Home shell: bottom navigation + home tab.
///
/// Home tab shows the balance hero, quick-action grid (Tasks, Spin, Check-in,
/// Wallet, Withdraw), the active promotion banner with countdown, and recent
/// transactions. Tiles respect the backend feature flags.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../auth/auth_service.dart';
import '../config/app_config.dart';
import '../theme.dart';
import '../widgets/widgets.dart';
import 'checkin_screen.dart';
import 'profile_screen.dart';
import 'referral_screen.dart';
import 'spin_screen.dart';
import 'tasks_screen.dart';
import 'wallet_screen.dart';
import 'withdraw_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    final titles = ['Home', 'Tasks', 'Wallet', 'Referral', 'Profile'];
    final bodies = [
      _HomeTab(onNavigate: _openQuickAction),
      const TasksScreen(embedded: true),
      const WalletScreen(embedded: true),
      const ReferralScreen(embedded: true),
      const ProfileScreen(embedded: true),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(titles[_tab])),
      body: bodies[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.task_outlined),
              selectedIcon: Icon(Icons.task),
              label: 'Tasks'),
          NavigationDestination(
              icon: Icon(Icons.account_balance_wallet_outlined),
              selectedIcon: Icon(Icons.account_balance_wallet),
              label: 'Wallet'),
          NavigationDestination(
              icon: Icon(Icons.group_add_outlined),
              selectedIcon: Icon(Icons.group_add),
              label: 'Referral'),
          NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Profile'),
        ],
      ),
    );
  }

  void _openQuickAction(String action) {
    Widget? screen;
    switch (action) {
      case 'Tasks':
        setState(() => _tab = 1);
        return;
      case 'Spin':
        screen = const SpinScreen();
        break;
      case 'Check-in':
        screen = const CheckinScreen();
        break;
      case 'Wallet':
        setState(() => _tab = 2);
        return;
      case 'Withdraw':
        screen = const WithdrawScreen();
        break;
    }
    if (screen != null) {
      Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => screen!));
    }
  }
}

class _HomeTab extends StatefulWidget {
  final void Function(String action) onNavigate;
  const _HomeTab({required this.onNavigate});

  @override
  State<_HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<_HomeTab> {
  Wallet? _wallet;
  List<TransactionItem> _recent = [];
  List<Promotion> _promos = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final auth = Provider.of<AuthService>(context, listen: false);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        api.getWallet(),
        api.getTransactions(page: 1),
        api.getPromotions(),
      ]);
      final wallet = results[0] as Wallet;
      _wallet = wallet;
      _recent = (results[1] as Paginated<TransactionItem>).data.take(5).toList();
      _promos = results[2] as List<Promotion>;
      auth.updateUserBalances(coins: wallet.coins, rupees: wallet.rupees);
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthService>().user;
    final features = context.watch<AppConfigService>().config?.features ??
        const FeatureFlags();

    if (_loading) return const LoadingView();
    if (_error != null) {
      return ErrorView(message: _error!, onRetry: _load);
    }

    final coins = _wallet?.coins ?? user?.coins ?? 0;
    final rupees = _wallet?.rupees ?? user?.rupees ?? 0;

    final actions = <_Action>[
      _Action('Tasks', Icons.task_outlined, features.offerwalls),
      _Action('Spin', Icons.casino_outlined, features.spin),
      _Action('Check-in', Icons.event_available_outlined, features.checkin),
      _Action('Wallet', Icons.account_balance_wallet_outlined, true),
      _Action('Withdraw', Icons.payments_outlined, features.withdrawals),
    ];

    return RefreshIndicator(
      onRefresh: _load,
      color: EarnPlusColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          BalanceHero(coins: coins, rupees: rupees),
          const SizedBox(height: 20),
          const Text('Quick Actions',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: 4,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: actions
                .map((a) => QuickActionTile(
                      key: Key('quick_${a.label}'),
                      icon: a.icon,
                      label: a.label,
                      enabled: a.enabled,
                      onTap: () => widget.onNavigate(a.label),
                    ))
                .toList(),
          ),
          if (features.promotions && _promos.isNotEmpty) ...[
            const SizedBox(height: 20),
            const Text('Promotions',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            ..._promos.map((p) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: PromoBanner(
                    title: p.bannerTitle.isNotEmpty ? p.bannerTitle : p.name,
                    subtitle: p.bannerSubtitle,
                    badge: p.badge,
                    endsAt: p.endsAt,
                    multiplier: p.multiplier,
                  ),
                )),
          ],
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Recent Activity',
                  style:
                      TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              TextButton(
                onPressed: () => widget.onNavigate('Wallet'),
                child: const Text('View all'),
              ),
            ],
          ),
          if (_recent.isEmpty)
            const EmptyView(
                icon: Icons.receipt_long_outlined,
                message: 'No transactions yet. Complete tasks to earn!')
          else
            Card(
              child: Column(
                children: _recent
                    .map((t) => ListTile(
                          leading: CircleAvatar(
                            backgroundColor: t.isCredit
                                ? EarnPlusColors.primaryLight
                                : Colors.red.shade50,
                            child: Icon(
                              t.isCredit ? Icons.add : Icons.remove,
                              color: t.isCredit
                                  ? EarnPlusColors.primary
                                  : EarnPlusColors.danger,
                            ),
                          ),
                          title: Text(t.source.isNotEmpty ? t.source : t.type),
                          subtitle: Text(t.createdAt),
                          trailing: Text(
                            '${t.isCredit ? '+' : ''}${t.amount}',
                            style: TextStyle(
                              fontWeight: FontWeight.w700,
                              color: t.isCredit
                                  ? EarnPlusColors.primary
                                  : EarnPlusColors.danger,
                            ),
                          ),
                        ))
                    .toList(),
              ),
            ),
        ],
      ),
    );
  }
}

class _Action {
  final String label;
  final IconData icon;
  final bool enabled;
  const _Action(this.label, this.icon, this.enabled);
}
