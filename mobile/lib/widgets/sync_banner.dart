import 'package:flutter/material.dart';

import '../data/repositories/sync_repository.dart';

/// A single honest line about whether the phone and the office agree.
/// It is always visible rather than hidden in a menu, because a cashier who
/// does not know work is unsent is the one who turns the phone off.
class SyncBanner extends StatelessWidget {
  const SyncBanner({super.key, required this.sync, this.onTap});

  final SyncRepository sync;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: sync,
      builder: (BuildContext context, _) {
        final SyncStatusInfo s = sync.status;
        if (s.state == SyncState.idle && s.pending == 0 && s.rejected == 0) {
          return const SizedBox.shrink();
        }
        final ColorScheme c = Theme.of(context).colorScheme;

        late final Color bg;
        late final Color fg;
        late final IconData icon;
        late final String text;

        if (s.rejected > 0) {
          bg = c.errorContainer;
          fg = c.onErrorContainer;
          icon = Icons.report_problem_outlined;
          text = '${s.rejected} item(s) the office would not accept. Tap to review.';
        } else if (s.state == SyncState.syncing) {
          bg = c.secondaryContainer;
          fg = c.onSecondaryContainer;
          icon = Icons.sync;
          text = 'Sending to the office…';
        } else if (s.state == SyncState.offline) {
          bg = c.tertiaryContainer;
          fg = c.onTertiaryContainer;
          icon = Icons.cloud_off_outlined;
          text = s.pending > 0
              ? 'No connection. ${s.pending} item(s) waiting — keep selling.'
              : 'No connection. You can keep selling.';
        } else if (s.state == SyncState.failed) {
          bg = c.errorContainer;
          fg = c.onErrorContainer;
          icon = Icons.error_outline;
          text = s.message ?? 'Could not reach the office.';
        } else {
          bg = c.secondaryContainer;
          fg = c.onSecondaryContainer;
          icon = Icons.schedule;
          text = '${s.pending} item(s) waiting to be sent.';
        }

        return Material(
          color: bg,
          child: InkWell(
            onTap: onTap ?? () => sync.syncNow(force: true),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                children: <Widget>[
                  Icon(icon, size: 18, color: fg),
                  const SizedBox(width: 10),
                  Expanded(child: Text(text, style: TextStyle(color: fg, fontSize: 13))),
                  if (s.state != SyncState.syncing)
                    Text('Retry', style: TextStyle(color: fg, fontWeight: FontWeight.w600, fontSize: 13)),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
