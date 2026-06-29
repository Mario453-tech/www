import 'dart:async';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../i18n/locale_provider.dart';
import '../services/web_session_cleaner.dart';
import '../services/webview_navigation_policy.dart';

class GameWebView extends StatefulWidget {
  final String url;
  final Future<void> Function()? onRetryBridge;

  const GameWebView({super.key, required this.url, this.onRetryBridge});

  @override
  State<GameWebView> createState() => _GameWebViewState();
}

class _GameWebViewState extends State<GameWebView> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _hasError = false;
  String _errorKey = 'webview.error_connection';

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) {
              setState(() {
                _loading = true;
                _hasError = false;
                _errorKey = 'webview.error_connection';
              });
            }
          },
          onPageFinished: (_) {
            if (!mounted) return;
            setState(() => _loading = false);
          },
          onWebResourceError: (error) {
            if (error.isForMainFrame != true) {
              return;
            }
            if (mounted) {
              setState(() {
                _loading = false;
                _hasError = true;
                _errorKey = 'webview.error_connection';
              });
            }
          },
          onNavigationRequest: (request) {
            if (WebViewNavigationPolicy.isAllowedUrl(request.url)) {
              return NavigationDecision.navigate;
            }
            if (mounted) {
              setState(() {
                _loading = false;
                _hasError = true;
                _errorKey = 'webview.error_blocked_navigation';
              });
            }
            return NavigationDecision.prevent;
          },
        ),
      );

    unawaited(_loadBridgeUrl());
  }


  Future<void> _loadBridgeUrl() async {
    try {
      await WebSessionCleaner.clearControllerStorage(_controller);
      await _controller.loadRequest(Uri.parse(widget.url));
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _hasError = true;
        _errorKey = 'webview.error_connection';
      });
    }
  }

  Future<void> _retry() async {
    if (widget.onRetryBridge != null) {
      await widget.onRetryBridge!();
      return;
    }
    await _loadBridgeUrl();
  }

  @override
  Widget build(BuildContext context) {
    if (_hasError) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off, size: 48),
            const SizedBox(height: 12),
            Text(context.t(_errorKey), textAlign: TextAlign.center),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () => unawaited(_retry()),
              icon: const Icon(Icons.refresh),
              label: Text(context.t('common.retry')),
            ),
          ],
        ),
      );
    }

    return Stack(
      children: [
        WebViewWidget(controller: _controller),
        if (_loading) const LinearProgressIndicator(),
      ],
    );
  }
}
