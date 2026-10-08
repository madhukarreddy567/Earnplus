/// Daily check-in: streak card + claim button.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../api/models.dart';
import '../auth/auth_service.dart';
import '../theme.dart';
import '../widgets/widgets.dart';

class CheckinScreen extends StatefulWidget {
  const CheckinScreen({super.key});

  @override
  State<CheckinScreen> createState() => _CheckinScreenState();
}

class _CheckinScreenState extends State<CheckinScreen> {
  CheckinStatus? _status;
  String? _error;
  bool _claiming = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    try {
      final status = await api.checkinStatus();
      if (mounted) setState(() => _status = status);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _claim() async {
    final api = Provider.of<ApiClient>(context, listen: false);
    final auth = Provider.of<AuthService>(context, listen: false);
    setState(() => _claiming = true);
    try {
      final result = await api.doCheckin();
      if (!mounted) return;
      setState(() {
        _status = CheckinStatus(
            checkedInToday: true, streak: result.streak);
        _claiming = false;
      });
      auth.updateUserBalances(
          coins: result.balance, rupees: auth.user?.rupees ?? 0);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
              'Checked in! +${result.amount} coins${result.streakBonus > 0 ? ' (+${result.streakBonus} streak bonus)' : ''}'),
          backgroundColor: EarnPlusColors.success,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _claiming = false);
      if (e.isConflict) {
        // 409 = already checked in — treat as success state, not an error.
        setState(() => _status =
            CheckinStatus(checkedInToday: true, streak: _status?.streak ?? 0));
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Already checked in today.')),
        );
      } else {
        showApiError(context, e);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _claiming = false);
        showApiError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Daily Check-in')),
      body: _error != null && _status == null
          ? ErrorView(message: _error!, onRetry: () {
              setState(() => _error = null);
              _load();
            })
          : _status == null
              ? const LoadingView()
              : _buildBody(),
    );
  }

  Widget _buildBody() {
    final status = _status!;
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: EarnPlusColors.primaryLight,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Icon(Icons.local_fire_department,
                        color: EarnPlusColors.accent, size: 56),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${status.streak}-day streak',
                    key: const Key('checkin_streak'),
                    style: const TextStyle(
                        fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    status.checkedInToday
                        ? 'You have checked in today. Come back tomorrow to keep the streak alive!'
                        : 'Check in today to grow your streak and earn bonus coins.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: EarnPlusColors.muted),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          // 7-day streak dots
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(7, (i) {
              final lit = i < (status.streak % 7 == 0 && status.streak > 0
                  ? 7
                  : status.streak % 7);
              return Container(
                margin: const EdgeInsets.symmetric(horizontal: 4),
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: lit
                      ? EarnPlusColors.primary
                      : Colors.grey.shade200,
                  shape: BoxShape.circle,
                ),
                child: Icon(Icons.check,
                    size: 18,
                    color: lit ? Colors.white : Colors.grey.shade400),
              );
            }),
          ),
          const Spacer(),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              key: const Key('checkin_button'),
              onPressed: (status.checkedInToday || _claiming) ? null : _claim,
              child: _claiming
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : Text(status.checkedInToday
                      ? 'Checked In ✓'
                      : 'Claim Today\'s Reward'),
            ),
          ),
        ],
      ),
    );
  }
}
