/// Notification inbox.
///
/// Reads from [NotificationService]. The backend exposes no notification
/// API for mobile yet (see the service file header), so the inbox starts
/// with a single local welcome message and fills as the app posts events.
/// Stitch design language.
library;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../services/notification_service.dart';
import '../stitch/stitch_theme.dart';
import '../stitch/stitch_widgets.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  String _when(DateTime at) {
    final now = DateTime.now();
    final diff = now.difference(at);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inHours < 1) return '${diff.inMinutes}m ago';
    if (diff.inDays < 1) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return DateFormat('d MMM yyyy').format(at);
  }

  @override
  Widget build(BuildContext context) {
    final inbox = context.watch<NotificationService>();
    final items = inbox.items;

    return Scaffold(
      backgroundColor: StitchColors.background,
      appBar: stitchAppBar(
        'Notifications',
        actions: [
          if (inbox.unreadCount > 0)
            TextButton(
              key: const Key('mark_all_read'),
              onPressed: inbox.markAllRead,
              child: const Text('Mark all read',
                  style: TextStyle(
                      color: StitchColors.primaryDark,
                      fontWeight: FontWeight.w700)),
            ),
        ],
      ),
      body: items.isEmpty
          ? const StitchEmpty(
              icon: Icons.notifications_outlined,
              message:
                  'You\'re all caught up!\nEarning updates and offers will appear here.',
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                final n = items[i];
                return InkWell(
                  key: Key('notification_${n.id}'),
                  borderRadius: BorderRadius.circular(18),
                  onTap: () => inbox.markRead(n.id),
                  child: Container(
                    decoration: stitchCardDecoration().copyWith(
                      color: n.read
                          ? StitchColors.card
                          : StitchColors.primarySoft
                              .withValues(alpha: 0.45),
                    ),
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: n.system
                                ? StitchColors.amberSoft
                                : StitchColors.primarySoft,
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            n.system
                                ? Icons.waving_hand_outlined
                                : Icons.notifications_outlined,
                            color: n.system
                                ? StitchColors.secondary
                                : StitchColors.primaryDark,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment:
                                CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(n.title,
                                        style: StitchText.title.copyWith(
                                            fontSize: 15)),
                                  ),
                                  if (!n.read)
                                    Container(
                                      width: 9,
                                      height: 9,
                                      decoration: const BoxDecoration(
                                        color: StitchColors.primary,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(n.body, style: StitchText.body),
                              const SizedBox(height: 6),
                              Text(_when(n.at),
                                  style: StitchText.caption),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}
