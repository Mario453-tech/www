import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../../../models/market.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_theme.dart';

/// Baner aktywnego zdarzenia rynkowego z odliczaniem — natywne odwzorowanie
/// bannera z webowego dashboardu. Dane (w tym czas do konca) pochodza z serwera;
/// rodzic odswieza co sekunde, a tu liczymy tylko format MM:SS / HH:MM.
class MarketEventCard extends StatelessWidget {
  final MarketTrend trend;
  const MarketEventCard({super.key, required this.trend});

  @override
  Widget build(BuildContext context) {
    final pct = trend.pricePct;
    final up = pct >= 0;
    final badgeColor = up ? AppColors.green : AppColors.red;
    final badgeText =
        '${up ? '+' : ''}$pct% ${context.t('dashboard.event.price_word')}';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.bg3, AppColors.bg2],
        ),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.goldBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              _Pill(
                text: context.t('dashboard.event.active').toUpperCase(),
                bg: const Color(0x14FFFFFF),
                fg: AppColors.text2,
              ),
              _Pill(
                text: badgeText.toUpperCase(),
                bg: badgeColor.withValues(alpha: 0.12),
                fg: badgeColor,
                border: badgeColor.withValues(alpha: 0.4),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            trend.message,
            style: const TextStyle(
              fontSize: 16,
              height: 1.3,
              fontWeight: FontWeight.w600,
              color: AppColors.text,
            ),
          ),
          const SizedBox(height: 14),
          _Countdown(seconds: trend.remainingSeconds()),
        ],
      ),
    );
  }
}

class _Countdown extends StatelessWidget {
  final int seconds;
  const _Countdown({required this.seconds});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0x0AFFFFFF),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppColors.borderSubtle),
      ),
      child: Row(
        children: [
          Text(
            context.t('dashboard.event.remaining').toUpperCase(),
            style: AppTheme.kpiLabel,
          ),
          const SizedBox(width: 12),
          Text(
            _format(seconds),
            style: const TextStyle(
              fontSize: 30,
              fontWeight: FontWeight.w800,
              letterSpacing: 1,
              color: AppColors.orange,
            ),
          ),
        ],
      ),
    );
  }

  /// >= 1h -> HH:MM (godziny:minuty), inaczej MM:SS.
  static String _format(int total) {
    final h = total ~/ 3600;
    final m = (total % 3600) ~/ 60;
    final s = total % 60;
    String two(int v) => v.toString().padLeft(2, '0');
    return h > 0 ? '${two(h)}:${two(m)}' : '${two(m)}:${two(s)}';
  }
}

class _Pill extends StatelessWidget {
  final String text;
  final Color bg;
  final Color fg;
  final Color? border;
  const _Pill({required this.text, required this.bg, required this.fg, this.border});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: border != null ? Border.all(color: border!) : null,
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.8,
          color: fg,
        ),
      ),
    );
  }
}
