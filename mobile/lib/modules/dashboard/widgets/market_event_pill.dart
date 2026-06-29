import 'package:flutter/material.dart';
import '../../../theme/app_colors.dart';
import '../dashboard_styles.dart';

class MarketEventPill extends StatelessWidget {
  final String text;
  final Color bg;
  final Color fg;
  final Color? border;

  const MarketEventPill({
    super.key,
    required this.text,
    required this.bg,
    required this.fg,
    this.border,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: DashboardStyles.pillPadding,
      decoration: DashboardStyles.pillDecoration(bg, border),
      child: Text(
        text,
        style: DashboardStyles.pillText.copyWith(color: fg),
      ),
    );
  }

  factory MarketEventPill.neutral(String text) => MarketEventPill(
        text: text,
        bg: DashboardStyles.neutralPillBg,
        fg: AppColors.text2,
      );

  factory MarketEventPill.date(String text) => MarketEventPill(
        text: text,
        bg: DashboardStyles.transparentPillBg,
        fg: AppColors.text3,
        border: AppColors.borderSubtle,
      );
}
