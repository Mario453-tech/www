import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:oil_empire/i18n/locale_provider.dart';
import 'package:oil_empire/i18n/strings/core_strings.dart';
import 'package:oil_empire/models/player.dart';
import 'package:oil_empire/modules/dashboard/dashboard_module.dart';
import 'package:oil_empire/modules/dashboard/dashboard_screen.dart';
import 'package:oil_empire/modules/module_registry.dart';
import 'package:oil_empire/providers/auth_provider.dart';

/// Atrapa AuthProvider — wstrzykuje gotowego gracza bez sieci.
class _FakeAuth extends AuthProvider {
  final Player _player;
  _FakeAuth(this._player);

  @override
  Player? get player => _player;
  @override
  bool get isLoading => false;
  @override
  String? get error => null;
  @override
  Future<void> refreshPlayer() async {}
}

Player _samplePlayer() => const Player(
      id: 1,
      username: 'tester',
      companyName: 'Test Co',
      cash: 962529.92,
      bankBalance: 2842343.70,
      oilPrice: 150.0,
      companyAgeDays: 120,
      financialState: 'normal',
      creditScore: 720,
      offlineMode: false,
      storage: Storage(capacity: 1300, used: 1300),
      activeWells: 3,
      activeLoans: 0,
    );

Widget _wrap(Widget child) {
  final translations = ModuleRegistry([DashboardModule()])
      .buildTranslations(LocaleProvider.supported, base: coreStrings);
  return MultiProvider(
    providers: [
      ChangeNotifierProvider<AuthProvider>(create: (_) => _FakeAuth(_samplePlayer())),
      ChangeNotifierProvider(create: (_) => LocaleProvider(translations)),
    ],
    child: MaterialApp(home: Scaffold(body: child)),
  );
}

void main() {
  testWidgets('Dashboard pokazuje powitanie z nazwa firmy', (tester) async {
    await tester.pumpWidget(_wrap(const DashboardScreen()));
    expect(find.text('Witaj, Test Co'), findsOneWidget);
  });

  testWidgets('Dashboard renderuje etykiety KPI (PL)', (tester) async {
    await tester.pumpWidget(_wrap(const DashboardScreen()));
    expect(find.text('GOTÓWKA'), findsOneWidget);
    expect(find.text('SALDO KONTA'), findsOneWidget);
    expect(find.text('CENA ROPY'), findsOneWidget);
    expect(find.text('AKTYWNE STUDNIE'), findsOneWidget);
  });

  testWidgets('Dashboard pokazuje liczbe aktywnych studni', (tester) async {
    await tester.pumpWidget(_wrap(const DashboardScreen()));
    expect(find.text('3'), findsWidgets);
  });

  testWidgets('Przelaczenie jezyka zmienia etykiety na EN', (tester) async {
    await tester.pumpWidget(_wrap(const DashboardScreen()));
    final ctx = tester.element(find.byType(DashboardScreen));
    await Provider.of<LocaleProvider>(ctx, listen: false).setLocale('en');
    await tester.pump();
    expect(find.text('CASH'), findsOneWidget);
    expect(find.text('ACCOUNT BALANCE'), findsOneWidget);
  });
}
