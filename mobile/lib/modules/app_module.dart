import 'package:flutter/material.dart';

/// Contract implemented by every mobile app module.
abstract class AppModule {
  String get id;

  String get titleKey;

  IconData get navIcon;

  IconData get navIconSelected => navIcon;

  int get order;

  bool get showInNav => true;

  Map<String, Map<String, String>> get translations => const {};

  Widget buildScreen(BuildContext context);
}
