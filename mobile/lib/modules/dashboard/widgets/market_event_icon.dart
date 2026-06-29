import 'package:flutter/material.dart';
import '../dashboard_styles.dart';

class MarketEventIcon extends StatelessWidget {
  final String category;
  final bool positive;

  const MarketEventIcon({
    super.key,
    required this.category,
    required this.positive,
  });

  @override
  Widget build(BuildContext context) {
    final color = DashboardStyles.eventImpactColor(positive);
    return Container(
      width: DashboardStyles.eventIconSize,
      height: DashboardStyles.eventIconSize,
      decoration: DashboardStyles.eventIconDecoration(color),
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
