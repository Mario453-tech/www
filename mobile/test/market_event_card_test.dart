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
      durationHours: 2,
      message: 'Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!',
      activatedAt: DateTime(2026, 6, 27, 12, 30),
      remainingSeconds: 3600,
      fetchedAt: DateTime.now(),
    );

    await tester.pumpWidget(_wrap(MarketEventCard(trend: trend)));

    expect(
      find.text('Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!'),
      findsOneWidget,
    );
    // Price impact badge.
    expect(find.textContaining('CENY ROPY'), findsOneWidget);
    // Active event label.
    expect(find.textContaining('ZDARZENIE'), findsOneWidget);
    expect(find.text('27.06.2026'), findsOneWidget);
  });
}
