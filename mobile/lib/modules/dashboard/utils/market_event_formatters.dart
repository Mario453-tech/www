/// Formatting helpers for the dashboard market event banner.
class MarketEventFormatters {
  MarketEventFormatters._();

  static String? date(DateTime? date, {String locale = 'pl'}) {
    if (date == null) return null;
    String two(int v) => v.toString().padLeft(2, '0');
    if (locale == 'en') {
      return '${two(date.month)}/${two(date.day)}/${date.year}';
    }
    return '${two(date.day)}.${two(date.month)}.${date.year}';
  }

  static String countdown(int total) {
    final h = total ~/ 3600;
    final m = (total % 3600) ~/ 60;
    final s = total % 60;
    String two(int v) => v.toString().padLeft(2, '0');
    return h > 0 ? '${two(h)}:${two(m)}' : '${two(m)}:${two(s)}';
  }
}
