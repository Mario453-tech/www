import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../config/app_config.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../theme/app_colors.dart';

/// Reusable WebView for any game department page.
///
/// Loads the page at [AppConfig.gameUrl]/[path] via the mobile web bridge,
/// and restricts in-WebView navigation to that path only.
///
/// Usage:
///   DepartmentWebView(path: '/technical', title: 'Dział Techniczny')
///   DepartmentWebView(path: '/legal',     title: 'Dział Prawny')
class DepartmentWebView extends StatefulWidget {
  final String path;
  final String title;

  const DepartmentWebView({
    super.key,
    required this.path,
    required this.title,
  });

  @override
  State<DepartmentWebView> createState() => _DepartmentWebViewState();
}

class _DepartmentWebViewState extends State<DepartmentWebView> {
  WebViewController? _ctrl;
  bool _bridgeLoading = true;
  bool _pageReady = false;
  String? _error;

  String get _targetUrl => '${AppConfig.gameUrl}${widget.path}';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _init());
  }

  Future<void> _init() async {
    setState(() {
      _bridgeLoading = true;
      _pageReady = false;
      _error = null;
    });

    final token = context.read<AuthProvider>().token;
    if (token == null) {
      setState(() { _bridgeLoading = false; _error = 'Brak tokenu'; });
      return;
    }

    try {
      final bridge = await ApiService.createWebBridge(token);

      late WebViewController ctrl;
      ctrl = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setBackgroundColor(const Color(0xFF0d0e14))
        ..setNavigationDelegate(NavigationDelegate(
          onNavigationRequest: (req) {
            final url = req.url;
            if (url.contains('mobile-bridge-login') ||
                Uri.tryParse(url)?.path.startsWith(widget.path) == true) {
              return NavigationDecision.navigate;
            }
            return NavigationDecision.prevent;
          },
          onPageFinished: (url) {
            if (!_pageReady && mounted) {
              if (Uri.tryParse(url)?.path.startsWith(widget.path) != true) {
                ctrl.loadRequest(Uri.parse(_targetUrl));
              } else {
                setState(() => _pageReady = true);
              }
            }
          },
          onWebResourceError: (err) {
            if (mounted) {
              setState(() { _bridgeLoading = false; _error = err.description; });
            }
          },
        ))
        ..loadRequest(Uri.parse(bridge.bridgeUrl));

      if (mounted) {
        setState(() { _ctrl = ctrl; _bridgeLoading = false; });
      }
    } catch (e) {
      if (mounted) {
        setState(() { _bridgeLoading = false; _error = e.toString(); });
      }
    }
  }

  Future<void> _goBack() async {
    if (_ctrl != null && await _ctrl!.canGoBack()) {
      await _ctrl!.goBack();
    } else if (mounted) {
      Navigator.of(context).maybePop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (!didPop) await _goBack();
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0d0e14),
        appBar: AppBar(
          backgroundColor: const Color(0xFF0d0e14),
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: AppColors.gold),
            onPressed: _goBack,
          ),
          title: Text(
            widget.title,
            style: const TextStyle(
              color: AppColors.gold,
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh, color: AppColors.text2),
              onPressed: _init,
            ),
          ],
        ),
        body: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_bridgeLoading) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CircularProgressIndicator(color: AppColors.gold),
            SizedBox(height: 16),
            Text('Łączenie...', style: TextStyle(color: AppColors.text2)),
          ],
        ),
      );
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, color: AppColors.red, size: 48),
              const SizedBox(height: 12),
              Text(_error!,
                  style: const TextStyle(color: AppColors.text2),
                  textAlign: TextAlign.center),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.gold,
                  foregroundColor: AppColors.bg3,
                ),
                onPressed: _init,
                icon: const Icon(Icons.refresh),
                label: const Text('Spróbuj ponownie'),
              ),
            ],
          ),
        ),
      );
    }

    final ctrl = _ctrl;
    if (ctrl == null) return const SizedBox.shrink();

    return Stack(
      children: [
        WebViewWidget(controller: ctrl),
        if (!_pageReady)
          const Center(child: CircularProgressIndicator(color: AppColors.gold)),
      ],
    );
  }
}
