import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../../../models/market.dart';
import '../dashboard_styles.dart';
import '../utils/market_event_formatters.dart';
import 'market_event_content.dart';
import 'market_event_countdown.dart';
import 'market_event_icon.dart';

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
    final badgeColor = DashboardStyles.eventImpactColor(up);
    final badgeText =
        '${up ? '+' : ''}$pct% ${context.t('dashboard.event.price_word')}';
    final activatedLabel = MarketEventFormatters.date(
      trend.activatedAt,
      locale: context.l10n.locale,
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        final compact =
            constraints.maxWidth < DashboardStyles.eventCompactBreakpoint;
        final content = MarketEventContent(
          trend: trend,
          badgeText: badgeText,
          badgeColor: badgeColor,
          activatedLabel: activatedLabel,
        );
        final icon = MarketEventIcon(category: trend.category, positive: up);
        final timer = MarketEventCountdown(seconds: trend.remainingSeconds());

        return Container(
          padding: DashboardStyles.cardPadding,
          decoration: DashboardStyles.goldCardDecoration(),
          child: compact
              ? _CompactEventLayout(
                  icon: icon,
                  content: content,
                  timer: timer,
                )
              : _WideEventLayout(
                  icon: icon,
                  content: content,
                  timer: timer,
                ),
        );
      },
    );
  }
}

class _CompactEventLayout extends StatelessWidget {
  final Widget icon;
  final Widget content;
  final Widget timer;

  const _CompactEventLayout({
    required this.icon,
    required this.content,
    required this.timer,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            icon,
            const SizedBox(width: DashboardStyles.gapMd),
            Expanded(child: content),
          ],
        ),
        const SizedBox(height: DashboardStyles.gapMd),
        Align(
          alignment: Alignment.centerRight,
          child: timer,
        ),
      ],
    );
  }
}

class _WideEventLayout extends StatelessWidget {
  final Widget icon;
  final Widget content;
  final Widget timer;

  const _WideEventLayout({
    required this.icon,
    required this.content,
    required this.timer,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        icon,
        const SizedBox(width: DashboardStyles.gapMd),
        Expanded(child: content),
        const SizedBox(width: DashboardStyles.gapMd),
        timer,
      ],
    );
  }
}
