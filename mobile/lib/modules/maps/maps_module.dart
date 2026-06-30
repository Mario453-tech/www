import 'package:flutter/material.dart';
import '../app_module.dart';
import 'maps_screen.dart';
import 'i18n/maps_en.dart';
import 'i18n/maps_pl.dart';

class MapsModule extends AppModule {
  @override
  String get id => 'maps';

  @override
  String get titleKey => 'nav.maps';

  @override
  IconData get navIcon => Icons.map_outlined;

  @override
  IconData get navIconSelected => Icons.map;

  @override
  int get order => 30;

  @override
  Map<String, Map<String, String>> get translations => const {
        'pl': mapsPl,
        'en': mapsEn,
      };

  @override
  Widget buildScreen(BuildContext context) => const MapsScreen();
}
