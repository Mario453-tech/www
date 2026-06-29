import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:oil_empire/i18n/locale_provider.dart';
import 'package:oil_empire/i18n/strings/core_strings.dart';
import 'package:oil_empire/models/well.dart';
import 'package:oil_empire/widgets/well_card.dart';

Widget _wrap(Widget child, {String locale = 'pl'}) {
  final translations = {
    'pl': Map<String, String>.from(coreStrings['pl']!),
    'en': Map<String, String>.from(coreStrings['en']!),
  };
  return ChangeNotifierProvider(
    create: (_) => LocaleProvider(translations, initial: locale),
    child: MaterialApp(home: Scaffold(body: child)),
  );
}

Well _well({String status = 'active', String riskLevel = 'low'}) => Well(
      id: 1,
      name: 'Test Well',
      location: 'Green River',
      status: status,
      wellType: 'onshore',
      transportType: 'rurociag',
      productionPerHour: 10,
      upkeepPerHour: 2.5,
      technicalCondition: 80,
      wearLevel: 5,
      equipmentTier: 'standard',
      equipmentUpgradeLevel: 1,
      productionMode: 'normal',
      reservoirRemaining: 500,
      reservoirMax: 1000,
      riskLevel: riskLevel,
      riskScore: 10,
    );

void main() {
  testWidgets('WellCard translates extended backend statuses in PL',
      (tester) async {
    await tester
        .pumpWidget(_wrap(WellCard(well: _well(status: 'no_operator'))));

    expect(find.text('Brak operatora'), findsOneWidget);
    expect(find.textContaining('no_operator'), findsNothing);
  });

  testWidgets('WellCard translates extended backend statuses in EN',
      (tester) async {
    await tester.pumpWidget(
      _wrap(WellCard(well: _well(status: 'paused_cash')), locale: 'en'),
    );

    expect(find.text('Paused: cash'), findsOneWidget);
    expect(find.textContaining('paused_cash'), findsNothing);
  });

  testWidgets('WellCard uses translated fallback for unknown statuses',
      (tester) async {
    await tester.pumpWidget(
      _wrap(WellCard(well: _well(status: 'future_status')), locale: 'en'),
    );

    expect(find.text('Unknown'), findsOneWidget);
    expect(find.textContaining('future_status'), findsNothing);
  });

  testWidgets('WellCard keeps PLN units in EN because values are not converted',
      (tester) async {
    await tester.pumpWidget(_wrap(WellCard(well: _well()), locale: 'en'));

    expect(find.textContaining('PLN/h'), findsOneWidget);
  });

  testWidgets('WellCard hides raw unknown risk codes', (tester) async {
    await tester.pumpWidget(
      _wrap(WellCard(well: _well(riskLevel: 'critical')), locale: 'en'),
    );

    expect(find.text('UNKNOWN'), findsOneWidget);
    expect(find.textContaining('CRITICAL'), findsNothing);
  });
}
