/// Tasks: offerwall provider cards. Tapping a provider calls
/// POST /api/tasks/click/{slug} and opens the returned URL in a WebView.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../theme.dart';
import '../widgets/widgets.dart';
import 'task_webview_screen.dart';

class TasksScreen extends StatefulWidget {
  /// When true the screen is a bottom-nav tab (no own AppBar).
  final bool embedded;
  const TasksScreen({super.key, this.embedded = false});

  @override
  State<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends State<TasksScreen> {
  List<TaskProvider>? _providers;
  String? _error;
  String? _openingSlug;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    try {
      final providers = await api.getTaskProviders();
      if (mounted) setState(() => _providers = providers);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _openProvider(TaskProvider p) async {
    final api = Provider.of<ApiClient>(context, listen: false);
    setState(() => _openingSlug = p.slug);
    try {
      final url = await api.taskClickUrl(p.slug);
      if (!mounted) return;
      Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => TaskWebViewScreen(title: p.name, url: url),
      ));
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _openingSlug = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final body = _error != null
        ? ErrorView(message: _error!, onRetry: () {
            setState(() => _error = null);
            _load();
          })
        : _providers == null
            ? const LoadingView(message: 'Loading tasks…')
            : _providers!.isEmpty
                ? const EmptyView(
                    icon: Icons.task_outlined,
                    message: 'No task providers available right now.')
                : RefreshIndicator(
                    onRefresh: _load,
                    color: EarnPlusColors.primary,
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _providers!.length,
                      separatorBuilder: (_, __) =>
                          const SizedBox(height: 12),
                      itemBuilder: (_, i) {
                        final p = _providers![i];
                        final opening = _openingSlug == p.slug;
                        return Card(
                          child: InkWell(
                            key: Key('provider_${p.slug}'),
                            borderRadius: BorderRadius.circular(18),
                            onTap: opening ? null : () => _openProvider(p),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Row(
                                children: [
                                  Container(
                                    width: 52,
                                    height: 52,
                                    decoration: BoxDecoration(
                                      color: EarnPlusColors.primaryLight,
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    child: const Icon(
                                        Icons.storefront_outlined,
                                        color: EarnPlusColors.primary,
                                        size: 28),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Flexible(
                                              child: Text(p.name,
                                                  style: const TextStyle(
                                                      fontSize: 16,
                                                      fontWeight:
                                                          FontWeight.w700)),
                                            ),
                                            if (p.badge != null &&
                                                p.badge!.isNotEmpty) ...[
                                              const SizedBox(width: 8),
                                              Container(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                        horizontal: 8,
                                                        vertical: 3),
                                                decoration: BoxDecoration(
                                                  color: EarnPlusColors.accent
                                                      .withValues(alpha: 0.25),
                                                  borderRadius:
                                                      BorderRadius.circular(
                                                          10),
                                                ),
                                                child: Text(p.badge!,
                                                    style: const TextStyle(
                                                        fontSize: 11,
                                                        fontWeight:
                                                            FontWeight.w700)),
                                              ),
                                            ],
                                          ],
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          p.demoTasks.isEmpty
                                              ? 'Complete offers to earn coins'
                                              : 'e.g. ${p.demoTasks.first.title} · +${p.demoTasks.first.payoutCoins} coins',
                                          style: const TextStyle(
                                              fontSize: 13,
                                              color: EarnPlusColors.muted),
                                        ),
                                        if (p.sandbox)
                                          const Text('Sandbox mode',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: EarnPlusColors.muted,
                                                  fontStyle:
                                                      FontStyle.italic)),
                                      ],
                                    ),
                                  ),
                                  opening
                                      ? const SizedBox(
                                          width: 24,
                                          height: 24,
                                          child: CircularProgressIndicator(
                                              strokeWidth: 2))
                                      : const Icon(Icons.chevron_right,
                                          color: EarnPlusColors.muted),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  );

    if (widget.embedded) return body;
    return Scaffold(appBar: AppBar(title: const Text('Tasks')), body: body);
  }
}
