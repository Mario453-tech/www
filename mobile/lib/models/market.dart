/// Aktywny trend/event rynkowy (np. "Zagrożenie militarne +70%").
/// Odliczanie bazuje na `remainingSeconds` policzonym przez SERWER w chwili
/// pobrania; telefon jedynie odejmuje czas, ktory uplynal od pobrania
/// (nie polega na zegarze urzadzenia jako zrodle prawdy).
class MarketTrend {
  final String name;
  final String category;
  final int pricePct;
  final String message;
  final int _remainingAtFetch;
  final DateTime _fetchedAt;

  MarketTrend({
    required this.name,
    required this.category,
    required this.pricePct,
    required this.message,
    required int remainingSeconds,
    required DateTime fetchedAt,
  })  : _remainingAtFetch = remainingSeconds,
        _fetchedAt = fetchedAt;

  /// Sekundy do konca eventu, skorygowane o czas od pobrania.
  int remainingSeconds([DateTime? now]) {
    final elapsed = (now ?? DateTime.now()).difference(_fetchedAt).inSeconds;
    final left = _remainingAtFetch - elapsed;
    return left < 0 ? 0 : left;
  }

  bool get isActive => remainingSeconds() > 0;

  factory MarketTrend.fromJson(Map<String, dynamic> j, DateTime fetchedAt) =>
      MarketTrend(
        name: j['name'] as String? ?? '',
        category: j['category'] as String? ?? '',
        pricePct: (j['price_pct'] as num?)?.toInt() ?? 0,
        message: j['message'] as String? ?? (j['name'] as String? ?? ''),
        remainingSeconds: (j['remaining_seconds'] as num?)?.toInt() ?? 0,
        fetchedAt: fetchedAt,
      );
}

class MarketState {
  final double currentPrice;
  final MarketTrend? trend;

  const MarketState({required this.currentPrice, this.trend});

  factory MarketState.fromJson(Map<String, dynamic> j) {
    final now = DateTime.now();
    final price = j['price'] as Map<String, dynamic>? ?? const {};
    final trendJson = j['trend'] as Map<String, dynamic>?;
    return MarketState(
      currentPrice: (price['current'] as num?)?.toDouble() ?? 0.0,
      trend: trendJson != null ? MarketTrend.fromJson(trendJson, now) : null,
    );
  }
}
