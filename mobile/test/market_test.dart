import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/models/market.dart';

void main() {
  group('MarketTrend.remainingSeconds', () {
    final fetchedAt = DateTime(2026, 1, 1, 12, 0, 0);
    final trend = MarketTrend(
      name: 'War',
      category: 'military',
      pricePct: 70,
      durationHours: 8,
      message: 'Military threat +70%',
      activatedAt: DateTime(2026, 1, 1, 10, 0, 0),
      remainingSeconds: 100,
      fetchedAt: fetchedAt,
    );

    test('subtracts elapsed time since fetch', () {
      final now = fetchedAt.add(const Duration(seconds: 30));
      expect(trend.remainingSeconds(now), 70);
    });

    test('does not go below zero', () {
      final now = fetchedAt.add(const Duration(seconds: 250));
      expect(trend.remainingSeconds(now), 0);
    });

    test('isActive is false after expiry', () {
      final now = fetchedAt.add(const Duration(seconds: 200));
      expect(trend.remainingSeconds(now), 0);
      expect(trend.isActiveAt(now), isFalse);
    });
  });

  group('MarketState.fromJson', () {
    test('parses price and active trend', () {
      final m = MarketState.fromJson({
        'price': {'current': 150.0},
        'trend': {
          'name': 'Boom',
          'category': 'economic',
          'price_pct': 50,
          'duration_hours': 4,
          'message': 'Boom +50%',
          'activated_at': '2026-06-27 12:30:00',
          'remaining_seconds': 3600,
        },
      });
      expect(m.currentPrice, 150.0);
      expect(m.trend, isNotNull);
      expect(m.trend!.name, 'Boom');
      expect(m.trend!.pricePct, 50);
      expect(m.trend!.durationHours, 4);
      expect(m.trend!.activatedAt, DateTime(2026, 6, 27, 12, 30));
      expect(m.trend!.remainingSeconds(), greaterThan(0));
    });

    test('missing trend becomes null', () {
      final m = MarketState.fromJson({
        'price': {'current': 100.0},
        'trend': null,
      });
      expect(m.currentPrice, 100.0);
      expect(m.trend, isNull);
    });
  });
}
