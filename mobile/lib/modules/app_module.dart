import 'package:flutter/material.dart';

/// Kontrakt modulu aplikacji. Kazdy modul (Dashboard, Gra, Rynek, Studnie...)
/// w jednym miejscu deklaruje: identyfikator, etykiete nawigacji, ikony,
/// kolejnosc, WLASNE tlumaczenia (per modul, jak w grze) oraz swoj ekran.
///
/// Dodanie nowego modulu = stworzenie jednej klasy i zarejestrowanie jej
/// w [ModuleRegistry] (lib/app/modules.dart). Zero zmian w reszcie aplikacji.
///
/// App module contract. Each module declares — in one place — its id, nav label,
/// icons, order, its OWN translations (per-module, like the game) and its screen.
/// Adding a module = one class + one registry entry.
abstract class AppModule {
  /// Stabilny identyfikator (np. 'dashboard', 'game', 'market').
  String get id;

  /// Klucz i18n etykiety w nawigacji (np. 'nav.dashboard').
  String get titleKey;

  /// Ikona w pasku nawigacji (stan nieaktywny).
  IconData get navIcon;

  /// Ikona w pasku nawigacji (stan aktywny). Domyslnie ta sama co [navIcon].
  IconData get navIconSelected => navIcon;

  /// Kolejnosc w nawigacji (rosnaco).
  int get order;

  /// Czy modul pojawia sie w dolnym pasku nawigacji.
  bool get showInNav => true;

  /// Tlumaczenia modulu: { 'pl': {...}, 'en': {...} }.
  /// Klucze w formacie `modul.klucz` (np. 'dashboard.cash').
  Map<String, Map<String, String>> get translations => const {};

  /// Buduje ekran modulu.
  Widget buildScreen(BuildContext context);
}
