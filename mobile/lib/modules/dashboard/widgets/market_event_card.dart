import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../../../models/market.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_theme.dart';

/// Native version of the web market event banner.
///
/// The server provides remaining seconds; this widget only formats the
/// countdown and mirrors the dashboard alert structure.
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
    final activatedLabel = _formatDate(trend.activatedAt);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.bg3, AppColors.bg2],
        ),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.goldBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          _EventIcon(category: trend.category, positive: up),
          const SizedBox(width: 12),
          Expanded(
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
                      bg: const Color(0x1AFFFFFF),
                      fg: AppColors.text2,
                    ),
                    _Pill(
                      text: badgeText.toUpperCase(),
                      bg: badgeColor.withValues(alpha: 0.12),
                      fg: badgeColor,
                      border: badgeColor.withValues(alpha: 0.4),
                    ),
                    if (activatedLabel != null)
                      _Pill(
                        text: activatedLabel,
                        bg: Colors.transparent,
                        fg: AppColors.text3,
                        border: AppColors.borderSubtle,
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  trend.message,
                  style: const TextStyle(
                    fontSize: 14,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                    color: AppColors.text,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          _Countdown(seconds: trend.remainingSeconds()),
        ],
      ),
    );
  }

  static String? _formatDate(DateTime? date) {
    if (date == null) return null;
    String two(int v) => v.toString().padLeft(2, '0');
    return '${two(date.day)}.${two(date.month)}.${date.year}';
  }
}

class _Countdown extends StatelessWidget {
  final int seconds;
  const _Countdown({required this.seconds});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 112,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0x47000000),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.borderSubtle),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const _PulseDot(),
          const SizedBox(height: 5),
          Text(
            context.t('dashboard.event.remaining').toUpperCase(),
            textAlign: TextAlign.center,
            style: AppTheme.kpiLabel.copyWith(fontSize: 8, letterSpacing: 1.2),
          ),
          const SizedBox(height: 5),
          Text(
            _format(seconds),
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 27,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.1,
              color: AppColors.orange,
              height: 1,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            context.t('dashboard.event.timer_sub', {
              'time': _timeLabel(context, seconds),
            }),
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 9,
              height: 1.25,
              color: AppColors.text3,
            ),
          ),
        ],
      ),
    );
  }

  /// >= 1h -> HH:MM, otherwise MM:SS.
  static String _format(int total) {
    final h = total ~/ 3600;
    final m = (total % 3600) ~/ 60;
    final s = total % 60;
    String two(int v) => v.toString().padLeft(2, '0');
    return h > 0 ? '${two(h)}:${two(m)}' : '${two(m)}:${two(s)}';
  }

  static String _timeLabel(BuildContext context, int total) {
    final h = total ~/ 3600;
    final m = (total % 3600) ~/ 60;
    if (h > 0) {
      return context.t('dashboard.event.time_hm', {'h': h, 'm': m});
    }
    return context.t('dashboard.event.time_m', {'m': m});
  }
}

class _EventIcon extends StatelessWidget {
  final String category;
  final bool positive;
  const _EventIcon({required this.category, required this.positive});

  @override
  Widget build(BuildContext context) {
    final color = positive ? AppColors.green : AppColors.red;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Icon(_iconFor(category), color: color, size: 20),
    );
  }

  static IconData _iconFor(String category) {
    switch (category) {
      case 'military':
      case 'war':
        return Icons.gpp_maybe;
      case 'political':
      case 'sanctions':
        return Icons.block;
      case 'economic':
      case 'boom':
        return Icons.trending_up;
      case 'discovery':
        return Icons.science;
      case 'winter':
        return Icons.ac_unit;
      case 'opec':
        return Icons.account_balance;
      default:
        return Icons.campaign;
    }
  }
}

class _PulseDot extends StatelessWidget {
  const _PulseDot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 7,
      height: 7,
      decoration: const BoxDecoration(
        color: AppColors.orange,
        shape: BoxShape.circle,
      ),
    );
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
