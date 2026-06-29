import 'package:flutter/material.dart';
import '../../../i18n/locale_provider.dart';
import '../../../theme/app_colors.dart';
import '../dashboard_styles.dart';

class LoansBanner extends StatelessWidget {
  final int count;

  const LoansBanner({super.key, required this.count});

  @override
  Widget build(BuildContext context) {
    final key = count == 1
        ? 'dashboard.active_loans_one'
        : 'dashboard.active_loans_many';

    return Container(
      padding: DashboardStyles.loansBannerPadding,
      decoration: DashboardStyles.loansBannerDecoration(),
      child: Row(
        children: [
          const Icon(
            Icons.warning_amber,
            color: AppColors.red,
            size: DashboardStyles.loansBannerIconSize,
          ),
          const SizedBox(width: 10),
          Text(
            context.t(key, {'count': count}),
            style: DashboardStyles.loansBannerText,
          ),
        ],
      ),
    );
  }
}
