import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

class GameWebView extends StatefulWidget {
  final String url;
  final String? token;
  const GameWebView({super.key, required this.url, this.token});

  @override
  State<GameWebView> createState() => _GameWebViewState();
}

class _GameWebViewState extends State<GameWebView> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) {
          if (mounted) setState(() { _loading = true; _hasError = false; });
        },
        onPageFinished: (_) {
          if (widget.token != null) {
            final safeToken = jsonEncode(widget.token);
            _controller.runJavaScript(
              "localStorage.setItem('api_token', $safeToken);",
            );
          }
          if (mounted) setState(() => _loading = false);
        },
        onWebResourceError: (_) {
          if (mounted) setState(() { _loading = false; _hasError = true; });
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
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
            const Text('Brak połączenia z serwerem'),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: () => _controller.reload(),
              icon: const Icon(Icons.refresh),
              label: const Text('Odśwież'),
            ),
          ],
        ),
      );
    }

    return Stack(
      children: [
        WebViewWidget(controller: _controller),
        if (_loading)
          const LinearProgressIndicator(),
      ],
    );
  }
}
