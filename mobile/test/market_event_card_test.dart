import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:oil_empire/i18n/locale_provider.dart';
import 'package:oil_empire/i18n/strings/core_strings.dart';
import 'package:oil_empire/models/market.dart';
import 'package:oil_empire/modules/dashboard/dashboard_module.dart';
import 'package:oil_empire/modules/dashboard/widgets/market_event_card.dart';
import 'package:oil_empire/modules/module_registry.dart';

Widget _wrap(Widget child, {String locale = 'pl'}) {
  final translations = ModuleRegistry([DashboardModule()])
      .buildTranslations(LocaleProvider.supported, base: coreStrings);
  return ChangeNotifierProvider(
    create: (_) => LocaleProvider(translations, initial: locale),
    child: MaterialApp(home: Scaffold(body: child)),
  );
}

MarketTrend sampleTrend() => MarketTrend(
      name: 'Zagrożenie militarne',
      category: 'military',
      pricePct: 70,
      durationHours: 2,
      message: 'Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!',
      activatedAt: DateTime(2026, 6, 27, 12, 30),
      remainingSeconds: 3600,
      fetchedAt: DateTime.now(),
    );

void main() {
  testWidgets('market event banner shows message, badge and label',
      (tester) async {
    await tester.pumpWidget(_wrap(MarketEventCard(trend: sampleTrend())));

    expect(
      find.text(
          'Zagrożenie militarne zwiększa zapotrzebowanie, ceny ropy +70%!'),
      findsOneWidget,
    );
    expect(find.textContaining('CENY ROPY'), findsOneWidget);
    expect(find.textContaining('ZDARZENIE'), findsOneWidget);
    expect(find.text('27.06.2026'), findsOneWidget);
  });

  testWidgets('market event banner does not overflow on narrow screens',
      (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 720));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(_wrap(MarketEventCard(trend: sampleTrend())));

    expect(tester.takeException(), isNull);
    expect(find.text('01:00'), findsOneWidget);
  });

  testWidgets('market event date follows EN locale formatting', (tester) async {
    await tester.pumpWidget(
      _wrap(MarketEventCard(trend: sampleTrend()), locale: 'en'),
    );

    expect(find.text('06/27/2026'), findsOneWidget);
  });
}
