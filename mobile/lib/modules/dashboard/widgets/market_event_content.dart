import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../../../models/market.dart';
import '../dashboard_styles.dart';
import 'market_event_pill.dart';

class MarketEventContent extends StatelessWidget {
  final MarketTrend trend;
  final String badgeText;
  final Color badgeColor;
  final String? activatedLabel;

  const MarketEventContent({
    super.key,
    required this.trend,
    required this.badgeText,
    required this.badgeColor,
    required this.activatedLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            MarketEventPill.neutral(
              context.t('dashboard.event.active').toUpperCase(),
            ),
            MarketEventPill(
              text: badgeText.toUpperCase(),
              bg: DashboardStyles.eventImpactPillBg(badgeColor),
              fg: badgeColor,
              border: DashboardStyles.eventImpactPillBorder(badgeColor),
            ),
            if (activatedLabel != null) MarketEventPill.date(activatedLabel!),
          ],
        ),
        const SizedBox(height: 10),
        Text(trend.message, style: DashboardStyles.eventMessage),
      ],
    );
  }
}
