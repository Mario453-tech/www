import 'package:flutter/material.dart';
import '../app_module.dart';
import 'dashboard_screen.dart';
import 'i18n/dashboard_en.dart';
import 'i18n/dashboard_pl.dart';

/// Dashboard module with the player's KPI overview.
class DashboardModule extends AppModule {
  @override
  String get id => 'dashboard';

  @override
  String get titleKey => 'nav.dashboard';

  @override
  IconData get navIcon => Icons.dashboard_outlined;

  @override
  IconData get navIconSelected => Icons.dashboard;

  @override
  int get order => 10;

  @override
  Map<String, Map<String, String>> get translations => const {
        'pl': dashboardPl,
        'en': dashboardEn,
      };

  @override
  Widget buildScreen(BuildContext context) => const DashboardScreen();
}
