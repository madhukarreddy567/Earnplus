/// WebView that renders an offerwall task URL obtained from
/// POST /api/tasks/click/{slug}.
library;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../ads/interstitial_scheduler.dart';
import '../theme.dart';

class TaskWebViewScreen extends StatefulWidget {
  final String title;
  final String url;
  const TaskWebViewScreen({super.key, required this.title, required this.url});

  @override
  State<TaskWebViewScreen> createState() => _TaskWebViewScreenState();
}

class _TaskWebViewScreenState extends State<TaskWebViewScreen> {
  late final WebViewController _controller;
  late final InterstitialScheduler _scheduler;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    // Unsafe zone for interstitials: never pop an ad over the task WebView.
    // Capture now — ancestor lookup is unsafe in dispose().
    _scheduler = Provider.of<InterstitialScheduler>(context, listen: false);
    _scheduler.suppress();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (_) {
            if (mounted) setState(() => _loading = false);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  void dispose() {
    _scheduler.unsuppress();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Center(
              child: CircularProgressIndicator(
                  color: EarnPlusColors.primary),
            ),
        ],
      ),
    );
  }
}
