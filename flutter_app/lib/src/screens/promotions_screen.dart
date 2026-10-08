/// Promotions: full list of active bonus offers (GET /api/promotions).
///
/// The home tab shows these as compact banners; this screen is the
/// dedicated view with multiplier, scope and countdown for each offer.
/// Stitch design language.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../stitch/stitch_theme.dart';
import '../stitch/stitch_widgets.dart';

class PromotionsScreen extends StatefulWidget {
  const PromotionsScreen({super.key});

  @override
  State<PromotionsScreen> createState() => _PromotionsScreenState();
}

class _PromotionsScreenState extends State<PromotionsScreen> {
  List<Promotion>? _promos;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    setState(() {
      _promos = null;
      _error = null;
    });
    try {
      final promos = await api.getPromotions();
      if (mounted) setState(() => _promos = promos);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StitchColors.background,
      appBar: stitchAppBar('Promotions'),
      body: _error != null && _promos == null
          ? StitchError(message: _error!, onRetry: _load)
          : _promos == null
              ? const StitchLoading(message: 'Loading offers…')
              : _promos!.isEmpty
                  ? const StitchEmpty(
                      icon: Icons.local_offer_outlined,
                      message:
                          'No active promotions right now.\nCheck back soon for bonus offers!')
                  : RefreshIndicator(
                      onRefresh: _load,
                      color: StitchColors.primary,
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          StitchTicker(items: _promos!
                              .map((p) => p.bannerTitle.isNotEmpty
                                  ? p.bannerTitle
                                  : p.name)
                              .toList()),
                          const SizedBox(height: 12),
                          ..._promos!.map((p) => Padding(
                                padding:
                                    const EdgeInsets.only(bottom: 12),
                                child: _PromoCard(promo: p),
                              )),
                          const SizedBox(height: 8),
                          const StitchTrustBadges(badges: [
                            (Icons.verified_outlined, 'Verified offers'),
                            (Icons.bolt_outlined, 'Auto-applied'),
                            (Icons.schedule_outlined, 'Limited time'),
                          ]),
                        ],
                      ),
                    ),
    );
  }
}

class _PromoCard extends StatelessWidget {
  final Promotion promo;
  const _PromoCard({required this.promo});

  String get _countdown {
    final raw = promo.endsAt;
    if (raw == null || raw.isEmpty) return '';
    final end = DateTime.tryParse(raw);
    if (end == null) return '';
    final left = end.difference(DateTime.now());
    if (left.isNegative) return 'Ending soon';
    if (left.inDays > 0) return '${left.inDays}d ${left.inHours % 24}h left';
    if (left.inHours > 0) return '${left.inHours}h ${left.inMinutes % 60}m left';
    return '${left.inMinutes}m left';
  }

  @override
  Widget build(BuildContext context) {
    final title =
        promo.bannerTitle.isNotEmpty ? promo.bannerTitle : promo.name;
    return Container(
      decoration: stitchCardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                colors: [StitchColors.primary, StitchColors.primaryDark],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if ((promo.badge ?? '').isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: StitchColors.secondary,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            promo.badge!,
                            style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: StitchColors.neutral),
                          ),
                        ),
                      Text(title,
                          style: StitchText.title
                              .copyWith(color: Colors.white)),
                      if (promo.bannerSubtitle.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(promo.bannerSubtitle,
                            style: const TextStyle(
                                fontSize: 13, color: Colors.white70)),
                      ],
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    '${promo.multiplier.toStringAsFixed(promo.multiplier % 1 == 0 ? 0 : 1)}x',
                    key: Key('promo_multiplier_${promo.name}'),
                    style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: StitchColors.primaryDark),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                const Icon(Icons.category_outlined,
                    size: 16, color: StitchColors.muted),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    promo.scope.isNotEmpty
                        ? 'Applies to: ${promo.scope}'
                        : 'Applies automatically at credit time',
                    style: StitchText.caption,
                  ),
                ),
                if (_countdown.isNotEmpty)
                  Row(
                    children: [
                      const Icon(Icons.timer_outlined,
                          size: 16, color: StitchColors.secondary),
                      const SizedBox(width: 4),
                      Text(_countdown,
                          style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: StitchColors.secondary)),
                    ],
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
