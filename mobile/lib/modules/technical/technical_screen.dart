import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/app_colors.dart';
import '../../config/app_config.dart';

class TechnicalScreen extends StatefulWidget {
  const TechnicalScreen({super.key});

  @override
  State<TechnicalScreen> createState() => _TechnicalScreenState();
}

class _TechnicalScreenState extends State<TechnicalScreen> {
  WebViewController? _controller;
  bool _bridgeLoading = true;
  String? _error;
  bool _webviewReady = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initWebView());
  }

  Future<void> _initWebView() async {
    setState(() {
      _bridgeLoading = true;
      _error = null;
      _webviewReady = false;
    });

    final token = context.read<AuthProvider>().token;
    if (token == null) {
      setState(() {
        _bridgeLoading = false;
        _error = 'Brak tokenu autoryzacji';
      });
      return;
    }

    try {
      final bridge = await ApiService.createWebBridge(token);
      final technicalUrl = '${AppConfig.gameUrl}/technical';

      late WebViewController ctrl;
      ctrl = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setBackgroundColor(const Color(0xFF0d0e14))
        ..setNavigationDelegate(NavigationDelegate(
          onNavigationRequest: (req) {
            final url = req.url;
            // Allow: bridge login + anything under /technical
            if (url.contains('mobile-bridge-login') ||
                Uri.tryParse(url)?.path.startsWith('/technical') == true) {
              return NavigationDecision.navigate;
            }
            // Block navigation to any other game section
            return NavigationDecision.prevent;
          },
          onPageFinished: (url) {
            if (!_webviewReady && mounted) {
              if (!url.contains('/technical')) {
                ctrl.loadRequest(Uri.parse(technicalUrl));
              } else {
                setState(() => _webviewReady = true);
              }
            }
          },
          onWebResourceError: (error) {
            if (mounted) {
              setState(() {
                _bridgeLoading = false;
                _error = 'Błąd ładowania: ${error.description}';
              });
            }
          },
        ))
        ..loadRequest(Uri.parse(bridge.bridgeUrl));

      if (mounted) {
        setState(() {
          _controller = ctrl;
          _bridgeLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _bridgeLoading = false;
          _error = e.toString();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final ctrl = _controller;
        if (ctrl != null && await ctrl.canGoBack()) {
          await ctrl.goBack();
        } else {
          if (context.mounted) Navigator.of(context).maybePop();
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0d0e14),
        appBar: AppBar(
          backgroundColor: const Color(0xFF0d0e14),
          elevation: 0,
          title: const Text(
            'Dział Techniczny',
            style: TextStyle(
              color: AppColors.gold,
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: AppColors.gold),
            onPressed: () async {
              final ctrl = _controller;
              if (ctrl != null && await ctrl.canGoBack()) {
                await ctrl.goBack();
              } else {
                if (context.mounted) Navigator.of(context).maybePop();
              }
            },
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh, color: AppColors.text2),
              onPressed: _initWebView,
              tooltip: 'Odśwież',
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
            Text(
              'Łączenie z systemem...',
              style: TextStyle(color: AppColors.text2),
            ),
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
              const SizedBox(height: 16),
              Text(
                'Błąd połączenia',
                style: const TextStyle(
                  color: AppColors.text,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                _error!,
                style: const TextStyle(color: AppColors.text2),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.gold,
                  foregroundColor: AppColors.bg3,
                ),
                onPressed: _initWebView,
                icon: const Icon(Icons.refresh),
                label: const Text('Spróbuj ponownie'),
              ),
            ],
          ),
        ),
      );
    }

    final ctrl = _controller;
    if (ctrl == null) return const SizedBox.shrink();

    return Stack(
      children: [
        WebViewWidget(controller: ctrl),
        if (!_webviewReady)
          const Center(
            child: CircularProgressIndicator(color: AppColors.gold),
          ),
      ],
    );
  }
}
