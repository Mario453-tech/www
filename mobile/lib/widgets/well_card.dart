import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../i18n/locale_provider.dart';
import '../models/well.dart';
import '../theme/app_colors.dart';

class WellCard extends StatelessWidget {
  final Well well;

  const WellCard({super.key, required this.well});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final intlLocale = context.l10n.locale == 'en' ? 'en_US' : 'pl_PL';
    final fmt = NumberFormat('#,##0.00', intlLocale);

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  well.wellType == 'offshore' ? Icons.water : Icons.terrain,
                  color: cs.primary,
                  size: 20,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    well.name,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                _StatusChip(status: well.status),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              well.location,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                  ),
            ),
            const Divider(height: 16),
            Row(
              children: [
                _Stat(
                  icon: Icons.speed,
                  label: context.t('wells.card.production'),
                  value:
                      '${fmt.format(well.productionPerHour)} ${context.t('common.unit.bbl_per_hour')}',
                ),
                const SizedBox(width: 16),
                _Stat(
                  icon: Icons.trending_down,
                  label: context.t('wells.card.costs'),
                  value:
                      '${fmt.format(well.upkeepPerHour)} ${context.t('common.unit.pln_per_hour')}',
                  valueColor: AppColors.red,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _Stat(
                  icon: Icons.build_outlined,
                  label: context.t('wells.card.technical_condition'),
                  value: '${well.technicalCondition}%',
                  valueColor: _conditionColor(well.technicalCondition),
                ),
                const SizedBox(width: 16),
                _Stat(
                  icon: Icons.warning_amber_outlined,
                  label: context.t('wells.card.risk'),
                  value: _riskLabel(context, well.riskLevel),
                  valueColor: _riskColor(well.riskLevel),
                ),
              ],
            ),
            if (well.reservoirMax > 0) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  const Icon(Icons.local_gas_station, size: 14),
                  const SizedBox(width: 4),
                  Text(
                    context.t('wells.card.reservoir', {
                      'remaining': fmt.format(well.reservoirRemaining),
                      'max': fmt.format(well.reservoirMax),
                      'unit': context.t('common.unit.bbl'),
                    }),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
              const SizedBox(height: 4),
              LinearProgressIndicator(
                value: well.reservoirPercent / 100,
                minHeight: 5,
                borderRadius: BorderRadius.circular(3),
                color: _reservoirColor(well.reservoirPercent),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _riskLabel(BuildContext context, String level) {
    switch (level) {
      case 'low':
        return context.t('wells.risk.low');
      case 'medium':
        return context.t('wells.risk.medium');
      case 'high':
        return context.t('wells.risk.high');
      default:
        return context.t('wells.risk.unknown');
    }
  }

  Color _conditionColor(int value) {
    if (value >= 70) return AppColors.green;
    if (value >= 40) return AppColors.orange;
    return AppColors.red;
  }

  Color _riskColor(String level) {
    switch (level) {
      case 'low':
        return AppColors.green;
      case 'medium':
        return AppColors.orange;
      case 'high':
        return AppColors.red;
      default:
        return AppColors.text3;
    }
  }

  Color _reservoirColor(double pct) {
    if (pct > 50) return AppColors.blue;
    if (pct > 20) return AppColors.orange;
    return AppColors.red;
  }
}

class _StatusChip extends StatelessWidget {
  final String status;

  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'active' => (context.t('wells.status.active'), AppColors.green),
      'paused' => (context.t('wells.status.paused'), AppColors.orange),
      'paused_cash' => (
          context.t('wells.status.paused_cash'),
          AppColors.orange
        ),
      'paused_staff' => (
          context.t('wells.status.paused_staff'),
          AppColors.orange
        ),
      'paused_storage' => (
          context.t('wells.status.paused_storage'),
          AppColors.orange
        ),
      'damaged' => (context.t('wells.status.damaged'), AppColors.red),
      'blowout' => (context.t('wells.status.blowout'), AppColors.red),
      'contaminated' => (context.t('wells.status.contaminated'), AppColors.red),
      'no_operator' => (
          context.t('wells.status.no_operator'),
          AppColors.orange
        ),
      'no_technician' => (
          context.t('wells.status.no_technician'),
          AppColors.orange
        ),
      'seized' => (context.t('wells.status.seized'), AppColors.red),
      'layer_switch' => (
          context.t('wells.status.layer_switch'),
          AppColors.orange
        ),
      'sold' => (context.t('wells.status.sold'), AppColors.text3),
      'offline' => (context.t('wells.status.offline'), AppColors.text3),
      _ => (context.t('wells.status.unknown'), AppColors.text3),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.5)),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: color,
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  const _Stat({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Expanded(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 14, color: cs.onSurfaceVariant),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: cs.onSurfaceVariant,
                      ),
                ),
                Text(
                  value,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w600,
                        color: valueColor,
                      ),
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
