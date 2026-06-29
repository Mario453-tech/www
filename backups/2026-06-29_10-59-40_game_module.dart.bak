import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../i18n/locale_provider.dart';
import '../../providers/auth_provider.dart';
import '../../screens/webview_screen.dart';
import '../../services/api_service.dart';
import '../app_module.dart';

/// Full web game module opened through a one-time backend bridge.
class GameModule extends AppModule {
  @override
  String get id => 'game';

  @override
  String get titleKey => 'nav.game';

  @override
  IconData get navIcon => Icons.public_outlined;

  @override
  IconData get navIconSelected => Icons.public;

  @override
  int get order => 20;

  @override
  Widget buildScreen(BuildContext context) {
    final token = context.watch<AuthProvider>().token;
    if (token == null) return const SizedBox.shrink();
    return _GameBridgeView(key: ValueKey(token), token: token);
  }
}

class _GameBridgeView extends StatefulWidget {
  final String token;

  const _GameBridgeView({super.key, required this.token});

  @override
  State<_GameBridgeView> createState() => _GameBridgeViewState();
}

class _GameBridgeViewState extends State<_GameBridgeView> {
  String? _bridgeUrl;
  String? _error;
  bool _loading = true;
  int _requestId = 0;

  @override
  void initState() {
    super.initState();
    _loadBridge();
  }

  @override
  void didUpdateWidget(covariant _GameBridgeView oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.token != widget.token) {
      _loadBridge();
    }
  }

  Future<void> _loadBridge() async {
    final requestId = ++_requestId;
    setState(() {
      _loading = true;
      _error = null;
      _bridgeUrl = null;
    });

    try {
      final bridge = await ApiService.createWebBridge(widget.token);
      if (!mounted || requestId != _requestId) return;
      setState(() {
        _bridgeUrl = bridge.bridgeUrl;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted || requestId != _requestId) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted || requestId != _requestId) return;
      setState(() {
        _error = 'common.error_connection';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_bridgeUrl != null) {
      return GameWebView(
        key: ValueKey(_bridgeUrl),
        url: _bridgeUrl!,
        onRetryBridge: _loadBridge,
      );
    }

    if (_loading) {
      return Center(child: Text(context.t('game.bridge.loading')));
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.lock_outline, size: 48),
            const SizedBox(height: 12),
            Text(
              context.resolveText(_error ?? 'game.bridge.error'),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _loading ? null : _loadBridge,
              icon: const Icon(Icons.refresh),
              label: Text(context.t('common.retry')),
            ),
          ],
        ),
      ),
    );
  }
}
