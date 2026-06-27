import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../screens/webview_screen.dart';
import '../app_module.dart';

/// Modul Gra — pelna gra w WebView, z tokenem wstrzyknietym do localStorage.
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
    return GameWebView(
      key: ValueKey(token),
      url: AppConfig.gameUrl,
      token: token,
    );
  }
}
