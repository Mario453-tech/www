import 'package:flutter/material.dart';
import '../app_module.dart';
import 'technical_screen.dart';
import 'i18n/technical_en.dart';
import 'i18n/technical_pl.dart';

class TechnicalModule extends AppModule {
  @override
  String get id => 'technical';

  @override
  String get titleKey => 'nav.technical';

  @override
  IconData get navIcon => Icons.build_outlined;

  @override
  IconData get navIconSelected => Icons.build;

  @override
  int get order => 40;

  @override
  Map<String, Map<String, String>> get translations => const {
        'pl': technicalPl,
        'en': technicalEn,
      };

  @override
  Widget buildScreen(BuildContext context) => const TechnicalScreen();
}
