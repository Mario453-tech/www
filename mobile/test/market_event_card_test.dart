import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:oil_empire/i18n/locale_provider.dart';
import 'package:oil_empire/i18n/strings/core_strings.dart';
import 'package:oil_empire/models/market.dart';
import 'package:oil_empire/modules/dashboard/dashboard_module.dart';
import 'package:oil_empire/modules/dashboard/widgets/market_event_card.dart';
import 'package:oil_empire/modules/module_registry.dart';

Widget _wrap(Widget child) {
  final translations = ModuleRegistry([DashboardModule()])
      .buildTranslations(LocaleProvider.supported, base: coreStrings);
  return ChangeNotifierProvider(
    create: (_) => LocaleProvider(translations),
    child: MaterialApp(home: Scaffold(body: child)),
  );
}

void main() {
  testWidgets('Baner eventu pokazuje komunikat, badge i etykiete', (tester) async {
    final trend = MarketTrend(
      name: 'Zagrożenie militarne',
      category: 'military',
      pricePct: 70,
      message: 'Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!',
      remainingSeconds: 3600,
      fetchedAt: DateTime.now(),
    );

    await tester.pumpWidget(_wrap(MarketEventCard(trend: trend)));

    expect(
      find.text('Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!'),
      findsOneWidget,
    );
    // Badge "+70% CENY ROPY" (wielkimi literami)
    expect(find.textContaining('CENY ROPY'), findsOneWidget);
    // Etykieta aktywnego zdarzenia
    expect(find.textContaining('ZDARZENIE'), findsOneWidget);
  });
}
