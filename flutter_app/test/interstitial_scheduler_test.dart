import 'package:earnplus/config.dart';
import 'package:earnplus/src/ads/interstitial_scheduler.dart';
import 'package:fake_async/fake_async.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('InterstitialScheduler', () {
    test('interval constant is the single source of truth (10 min)',
        () {
      expect(interstitialIntervalMinutes, 10);
      expect(
        const Duration(minutes: interstitialIntervalMinutes),
        const Duration(minutes: 10),
      );
    });

    test('does not fire on cold start before the first interval', () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        now = now.add(const Duration(minutes: 9));
        async.elapse(const Duration(minutes: 9));
        async.flushMicrotasks();
        expect(shown, 0);
        s.stop();
      });
    });

    test('fires after one interval of foreground time', () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(shown, 1);
        // Timer restarts: a second interval fires again.
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(shown, 2);
        s.stop();
      });
    });

    test('does not count backgrounded time', () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        // 5 min foreground…
        now = now.add(const Duration(minutes: 5));
        async.elapse(const Duration(minutes: 5));
        // …then backgrounded for an hour (clock frozen for the scheduler)…
        s.didChangeAppLifecycleState(AppLifecycleState.paused);
        async.elapse(const Duration(hours: 1));
        async.flushMicrotasks();
        expect(shown, 0, reason: 'must not fire while backgrounded');
        // …back to foreground: only 5 more foreground minutes needed.
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        now = now.add(const Duration(minutes: 5));
        async.elapse(const Duration(minutes: 5));
        async.flushMicrotasks();
        expect(shown, 1);
        s.stop();
      });
    });

    test('does not fire immediately on resume unless interval elapsed',
        () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        now = now.add(const Duration(minutes: 3));
        async.elapse(const Duration(minutes: 3));
        s.didChangeAppLifecycleState(AppLifecycleState.paused);
        async.elapse(const Duration(minutes: 30));
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        async.elapse(const Duration(seconds: 5));
        async.flushMicrotasks();
        expect(shown, 0, reason: 'only 3 foreground minutes elapsed');
        s.stop();
      });
    });

    test('defers while suppressed and fires on release', () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        s.suppress(); // e.g. rewarded ad showing / WebView open
        now = now.add(const Duration(minutes: 12));
        async.elapse(const Duration(minutes: 12));
        async.flushMicrotasks();
        expect(shown, 0, reason: 'suppressed: must not interrupt');
        expect(s.debugDeferred, isTrue);
        s.unsuppress(); // user back on a safe screen
        async.flushMicrotasks();
        expect(shown, 1, reason: 'deferred show fires on release');
        s.stop();
      });
    });

    test('nested suppress/unsuppress pairs stay suppressed until balanced',
        () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        s.suppress();
        s.suppress();
        now = now.add(const Duration(minutes: 11));
        async.elapse(const Duration(minutes: 11));
        s.unsuppress();
        async.flushMicrotasks();
        expect(shown, 0, reason: 'still one zone held');
        s.unsuppress();
        async.flushMicrotasks();
        expect(shown, 1);
        s.stop();
      });
    });

    test('load failure skips silently and retries next interval', () {
      FakeAsync().run((async) {
        var attempts = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            attempts++;
            return false; // no fill
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(attempts, 1);
        // No exception, no user-facing anything — just tries again later.
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(attempts, 2);
        s.stop();
      });
    });

    test('showAd throwing does not break the schedule', () {
      FakeAsync().run((async) {
        var attempts = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            attempts++;
            throw StateError('boom');
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(attempts, 1);
        now = now.add(const Duration(minutes: 10));
        async.elapse(const Duration(minutes: 10));
        async.flushMicrotasks();
        expect(attempts, 2);
        s.stop();
      });
    });

    test('stop halts the timer', () {
      FakeAsync().run((async) {
        var shown = 0;
        var now = DateTime(2026, 1, 1, 12, 0, 0);
        final s = InterstitialScheduler(
          interval: const Duration(minutes: 10),
          showAd: () async {
            shown++;
            return true;
          },
          clock: () => now,
        );
        s.didChangeAppLifecycleState(AppLifecycleState.resumed);
        s.start();
        s.stop();
        now = now.add(const Duration(minutes: 30));
        async.elapse(const Duration(minutes: 30));
        async.flushMicrotasks();
        expect(shown, 0);
      });
    });
  });
}
