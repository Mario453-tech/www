import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../dashboard_styles.dart';
import '../utils/market_event_formatters.dart';

class MarketEventCountdown extends StatelessWidget {
  final int seconds;

  const MarketEventCountdown({super.key, required this.seconds});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: DashboardStyles.eventTimerWidth,
      padding: DashboardStyles.timerPadding,
      decoration: DashboardStyles.subtlePanelDecoration(),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const _PulseDot(),
          const SizedBox(height: 5),
          Text(
            context.t('dashboard.event.remaining').toUpperCase(),
            textAlign: TextAlign.center,
            style: DashboardStyles.timerLabel,
          ),
          const SizedBox(height: 5),
          Text(
            MarketEventFormatters.countdown(seconds),
            textAlign: TextAlign.center,
            style: DashboardStyles.timerValue,
          ),
          const SizedBox(height: 5),
          Text(
            context.t('dashboard.event.timer_sub', {
              'time': _timeLabel(context, seconds),
            }),
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: DashboardStyles.timerSub,
          ),
        ],
      ),
    );
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

class _PulseDot extends StatelessWidget {
  const _PulseDot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: DashboardStyles.eventTimerDotSize,
      height: DashboardStyles.eventTimerDotSize,
      decoration: const BoxDecoration(
        color: DashboardStyles.eventTimerDotColor,
        shape: BoxShape.circle,
      ),
    );
  }
}
