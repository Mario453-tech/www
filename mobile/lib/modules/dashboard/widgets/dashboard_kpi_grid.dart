import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../i18n/locale_provider.dart';
import '../../../models/player.dart';
import '../../../theme/app_colors.dart';
import '../dashboard_styles.dart';
import 'dashboard_two_column.dart';
import 'kpi_card.dart';

class DashboardKpiGrid extends StatelessWidget {
  final Player player;

  const DashboardKpiGrid({super.key, required this.player});

  @override
  Widget build(BuildContext context) {
    final intlLocale = context.l10n.locale == 'en' ? 'en_US' : 'pl_PL';
    final money = NumberFormat('#,##0.00', intlLocale);
    final intFmt = NumberFormat('#,##0', intlLocale);
    final bbl = context.t('dashboard.bbl_unit');
    final (stateLabel, _) = _financialState(context, player.financialState);
    final pct = player.storage.fillPercent;

    return Column(
      children: [
        DashboardTwoColumn(
          left: KpiCard(
            icon: Icons.attach_money,
            iconColor: AppColors.iconCash,
            label: context.t('dashboard.cash'),
            value: money.format(player.cash),
            sub: context.t('common.currency'),
            money: true,
          ),
          right: KpiCard(
            icon: Icons.account_balance,
            iconColor: AppColors.iconBank,
            label: context.t('dashboard.bank_balance'),
            value: money.format(player.bankBalance),
            sub: context.t('common.currency'),
            money: true,
          ),
        ),
        const SizedBox(height: DashboardStyles.gapMd),
        DashboardTwoColumn(
          left: KpiCard(
            icon: Icons.battery_charging_full,
            iconColor: AppColors.iconStorage,
            label: context.t('dashboard.storage'),
            value:
                '${intFmt.format(player.storage.used)} / ${intFmt.format(player.storage.capacity)}',
            sub:
                '$bbl ${context.t('common.separator_dot')} ${context.t('dashboard.storage_filled', {
                  'pct': pct.toStringAsFixed(0),
                })}',
            progress: pct / 100,
          ),
          right: KpiCard(
            icon: Icons.local_fire_department,
            iconColor: AppColors.iconOil,
            label: context.t('dashboard.oil_price'),
            value:
                '${money.format(player.oilPrice)} ${context.t('dashboard.oil_unit')}',
            money: true,
          ),
        ),
        const SizedBox(height: DashboardStyles.gapMd),
        KpiCard(
          icon: Icons.business,
          iconColor: AppColors.iconStatus,
          label: context.t('dashboard.company_status'),
          value: stateLabel,
          sub: context.t('dashboard.company_age', {
            'days': player.companyAgeDays,
          }),
          pulse: player.financialState == 'normal',
        ),
        const SizedBox(height: DashboardStyles.gapMd),
        DashboardTwoColumn(
          left: KpiCard(
            icon: Icons.oil_barrel,
            iconColor: AppColors.green,
            label: context.t('dashboard.active_wells'),
            value: '${player.activeWells}',
          ),
          right: KpiCard(
            icon: Icons.credit_score,
            iconColor: _creditColor(player.creditScore),
            label: context.t('dashboard.credit_score'),
            value: '${player.creditScore}',
          ),
        ),
      ],
    );
  }

  (String, Color) _financialState(BuildContext context, String state) {
    switch (state) {
      case 'warning':
        return (context.t('dashboard.state.warning'), AppColors.orange);
      case 'crisis':
        return (context.t('dashboard.state.crisis'), AppColors.red);
      case 'bankrupt':
        return (context.t('dashboard.state.bankrupt'), AppColors.red);
      case 'normal':
      case 'stable':
      default:
        return (context.t('dashboard.state.normal'), AppColors.green);
    }
  }

  Color _creditColor(int score) {
    if (score >= 700) return AppColors.green;
    if (score >= 500) return AppColors.orange;
    return AppColors.red;
  }
}
