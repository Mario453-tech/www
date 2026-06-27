import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/models/market.dart';

void main() {
  group('MarketTrend.remainingSeconds', () {
    final fetchedAt = DateTime(2026, 1, 1, 12, 0, 0);
    final trend = MarketTrend(
      name: 'Wojna',
      category: 'military',
      pricePct: 70,
      message: 'Zagrożenie militarne +70%',
      remainingSeconds: 100,
      fetchedAt: fetchedAt,
    );

    test('odejmuje czas, ktory uplynal od pobrania', () {
      final now = fetchedAt.add(const Duration(seconds: 30));
      expect(trend.remainingSeconds(now), 70);
    });

    test('nie schodzi ponizej zera', () {
      final now = fetchedAt.add(const Duration(seconds: 250));
      expect(trend.remainingSeconds(now), 0);
    });

    test('isActive jest false po wygasnieciu', () {
      final now = fetchedAt.add(const Duration(seconds: 200));
      expect(trend.remainingSeconds(now), 0);
    });
  });

  group('MarketState.fromJson', () {
    test('parsuje cene i aktywny trend', () {
      final m = MarketState.fromJson({
        'price': {'current': 150.0},
        'trend': {
          'name': 'Boom',
          'category': 'economic',
          'price_pct': 50,
          'message': 'Boom +50%',
          'remaining_seconds': 3600,
        },
      });
      expect(m.currentPrice, 150.0);
      expect(m.trend, isNotNull);
      expect(m.trend!.name, 'Boom');
      expect(m.trend!.pricePct, 50);
    });

    test('brak trendu -> trend == null', () {
      final m = MarketState.fromJson({
        'price': {'current': 100.0},
        'trend': null,
      });
      expect(m.currentPrice, 100.0);
      expect(m.trend, isNull);
    });
  });
}
