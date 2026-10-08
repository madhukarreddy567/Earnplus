/// Wallet: balance summary + paginated transaction history.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../theme.dart';
import '../widgets/widgets.dart';

class WalletScreen extends StatefulWidget {
  final bool embedded;
  const WalletScreen({super.key, this.embedded = false});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  Wallet? _wallet;
  final List<TransactionItem> _txns = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final wallet = await api.getWallet();
      final paged = await api.getTransactions(page: 1);
      if (!mounted) return;
      setState(() {
        _wallet = wallet;
        _txns
          ..clear()
          ..addAll(paged.data);
        _page = paged.currentPage;
        _lastPage = paged.lastPage;
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _lastPage) return;
    final api = Provider.of<ApiClient>(context, listen: false);
    setState(() => _loadingMore = true);
    try {
      final paged = await api.getTransactions(page: _page + 1);
      if (!mounted) return;
      setState(() {
        _txns.addAll(paged.data);
        _page = paged.currentPage;
        _lastPage = paged.lastPage;
      });
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = _loading
        ? const LoadingView(message: 'Loading wallet…')
        : _error != null
            ? ErrorView(message: _error!, onRetry: _load)
            : RefreshIndicator(
                onRefresh: _load,
                color: EarnPlusColors.primary,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    BalanceHero(
                      coins: _wallet?.coins ?? 0,
                      rupees: _wallet?.rupees ?? 0,
                      subtitle:
                          'Lifetime earned: ${_wallet?.lifetimeEarned ?? 0} coins',
                    ),
                    const SizedBox(height: 20),
                    const Text('Transactions',
                        style: TextStyle(
                            fontSize: 16, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    if (_txns.isEmpty)
                      const EmptyView(
                          icon: Icons.receipt_long_outlined,
                          message: 'No transactions yet.')
                    else
                      Card(
                        child: Column(
                          children: [
                            ..._txns.map((t) => ListTile(
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
                                  title: Text(t.source.isNotEmpty
                                      ? t.source
                                      : t.type),
                                  subtitle: Text(
                                      '${t.createdAt} · bal ${t.balanceAfter}'),
                                  trailing: Text(
                                    '${t.isCredit ? '+' : ''}${t.amount}',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: t.isCredit
                                          ? EarnPlusColors.primary
                                          : EarnPlusColors.danger,
                                    ),
                                  ),
                                )),
                            if (_page < _lastPage)
                              Padding(
                                padding: const EdgeInsets.all(12),
                                child: _loadingMore
                                    ? const CircularProgressIndicator()
                                    : OutlinedButton(
                                        key: const Key('load_more_button'),
                                        onPressed: _loadMore,
                                        child: const Text('Load more'),
                                      ),
                              ),
                          ],
                        ),
                      ),
                  ],
                ),
              );

    if (widget.embedded) return body;
    return Scaffold(appBar: AppBar(title: const Text('Wallet')), body: body);
  }
}
