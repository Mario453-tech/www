/// Active market trend/event, for example "Military threat +70%".
///
/// Countdown is based on server-calculated `remainingSeconds` from fetch time.
/// The device only subtracts local elapsed time, so it does not use its own
/// clock as the source of truth.
class MarketTrend {
  final String name;
  final String category;
  final int pricePct;
  final int durationHours;
  final String message;
  final DateTime? activatedAt;
  final int _remainingAtFetch;
  final DateTime _fetchedAt;

  MarketTrend({
    required this.name,
    required this.category,
    required this.pricePct,
    required this.durationHours,
    required this.message,
    this.activatedAt,
    required int remainingSeconds,
    required DateTime fetchedAt,
  })  : _remainingAtFetch = remainingSeconds,
        _fetchedAt = fetchedAt;

  /// Seconds left, adjusted by elapsed time since fetch.
  int remainingSeconds([DateTime? now]) {
    final elapsed = (now ?? DateTime.now()).difference(_fetchedAt).inSeconds;
    final left = _remainingAtFetch - elapsed;
    return left < 0 ? 0 : left;
  }

  bool isActiveAt([DateTime? now]) => remainingSeconds(now) > 0;

  bool get isActive => isActiveAt();

  factory MarketTrend.fromJson(Map<String, dynamic> j, DateTime fetchedAt) =>
      MarketTrend(
        name: j['name'] as String? ?? '',
        category: j['category'] as String? ?? '',
        pricePct: (j['price_pct'] as num?)?.toInt() ?? 0,
        durationHours: (j['duration_hours'] as num?)?.toInt() ?? 0,
        message: j['message'] as String? ?? (j['name'] as String? ?? ''),
        activatedAt: _parseDate(j['activated_at'] as String?),
        remainingSeconds: (j['remaining_seconds'] as num?)?.toInt() ?? 0,
        fetchedAt: fetchedAt,
      );

  static DateTime? _parseDate(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    return DateTime.tryParse(value) ??
        DateTime.tryParse(value.replaceFirst(' ', 'T'));
  }
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
