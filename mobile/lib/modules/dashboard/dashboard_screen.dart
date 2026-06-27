import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/app_config.dart';
import '../../i18n/locale_provider.dart';
import '../../models/player.dart';
import '../../providers/auth_provider.dart';
import '../../theme/app_colors.dart';
import 'widgets/kpi_card.dart';

/// Ekran przegladu (Dashboard) — natywne odwzorowanie webowego pulpitu gry:
/// kafelki KPI (gotowka, saldo konta, magazyn, cena ropy, status firmy).
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final player = auth.player;

    if (player == null) {
      if (auth.isLoading) {
        return const Center(child: CircularProgressIndicator());
      }
      return _ErrorState(
        message: auth.error ?? context.t('common.error_generic'),
        onRetry: auth.refreshPlayer,
      );
    }

    return RefreshIndicator(
      color: AppColors.gold,
      onRefresh: () => context.read<AuthProvider>().refreshPlayer(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          Text(
            context.t('dashboard.greeting', {'name': player.companyName}),
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: AppColors.text,
            ),
          ),
          const SizedBox(height: 16),
          _KpiGrid(player: player),
          if (player.activeLoans > 0) ...[
            const SizedBox(height: 12),
            _LoansBanner(count: player.activeLoans),
          ],
        ],
      ),
    );
  }
}

class _KpiGrid extends StatelessWidget {
  final Player player;
  const _KpiGrid({required this.player});

  @override
  Widget build(BuildContext context) {
    final money = NumberFormat('#,##0.00', 'pl_PL');
    final intFmt = NumberFormat('#,##0', 'pl_PL');
    final bbl = context.t('dashboard.bbl_unit');

    final (stateLabel, _) = _financialState(context, player.financialState);
    final pct = player.storage.fillPercent;

    return Column(
      children: [
        _Row2(
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
        const SizedBox(height: 12),
        _Row2(
          left: KpiCard(
            icon: Icons.battery_charging_full,
            iconColor: AppColors.iconStorage,
            label: context.t('dashboard.storage'),
            value:
                '${intFmt.format(player.storage.used)} / ${intFmt.format(player.storage.capacity)}',
            sub: '$bbl · ${context.t('dashboard.storage_filled', {
                  'pct': pct.toStringAsFixed(0)
                })}',
            progress: pct / 100,
          ),
          right: KpiCard(
            icon: Icons.local_fire_department,
            iconColor: AppColors.iconOil,
            label: context.t('dashboard.oil_price'),
            value: '${money.format(player.oilPrice)} ${context.t('dashboard.oil_unit')}',
            money: true,
          ),
        ),
        const SizedBox(height: 12),
        KpiCard(
          icon: Icons.business,
          iconColor: AppColors.iconStatus,
          label: context.t('dashboard.company_status'),
          value: stateLabel,
          sub: context.t('dashboard.company_age', {'days': player.companyAgeDays}),
          pulse: player.financialState == 'normal',
        ),
        const SizedBox(height: 12),
        _Row2(
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

/// Dwa kafelki w rzedzie, jednakowej wysokosci.
class _Row2 extends StatelessWidget {
  final Widget left;
  final Widget right;
  const _Row2({required this.left, required this.right});

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: left),
          const SizedBox(width: 12),
          Expanded(child: right),
        ],
      ),
    );
  }
}

class _LoansBanner extends StatelessWidget {
  final int count;
  const _LoansBanner({required this.count});

  @override
  Widget build(BuildContext context) {
    final key = count == 1
        ? 'dashboard.active_loans_one'
        : 'dashboard.active_loans_many';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.redDim,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.red.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          const Icon(Icons.warning_amber, color: AppColors.red, size: 20),
          const SizedBox(width: 10),
          Text(
            context.t(key, {'count': count}),
            style: const TextStyle(color: AppColors.text, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;
  const _ErrorState({required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off, size: 48, color: AppColors.red),
            const SizedBox(height: 12),
            Text(context.t('common.error_connection'),
                style: const TextStyle(fontSize: 16, color: AppColors.text)),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0x14FFFFFF),
                borderRadius: BorderRadius.circular(8),
              ),
              child: SelectableText(
                'URL: ${AppConfig.baseUrl}\n\n$message',
                style: const TextStyle(fontSize: 12, color: AppColors.text2),
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
