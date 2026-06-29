import 'package:flutter/material.dart';
import '../../../config/app_config.dart';
import '../../../i18n/locale_provider.dart';
import '../../../theme/app_colors.dart';
import '../dashboard_styles.dart';

class DashboardErrorState extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const DashboardErrorState({
    super.key,
    required this.message,
    this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: DashboardStyles.errorPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.cloud_off,
              size: DashboardStyles.errorIconSize,
              color: AppColors.red,
            ),
            const SizedBox(height: 12),
            Text(
              context.t('common.error_connection'),
              style: DashboardStyles.errorTitle,
            ),
            const SizedBox(height: 8),
            Container(
              padding: DashboardStyles.errorMessagePadding,
              decoration: DashboardStyles.errorMessageDecoration(),
              child: SelectableText(
                '${context.t('common.debug_url_label')}: ${AppConfig.baseUrl}\n\n${context.resolveText(message)}',
                style: DashboardStyles.errorDetails,
              ),
            ),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(context.t('common.retry')),
            ),
          ],
        ),
      ),
    );
  }
}
