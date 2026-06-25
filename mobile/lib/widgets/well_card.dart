import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/well.dart';

class WellCard extends StatelessWidget {
  final Well well;
  const WellCard({super.key, required this.well});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final fmt = NumberFormat('#,##0.00', 'pl_PL');

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
                  well.wellType == 'offshore'
                      ? Icons.water
                      : Icons.terrain,
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
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: cs.onSurfaceVariant),
            ),
            const Divider(height: 16),
            Row(
              children: [
                _Stat(
                  icon: Icons.speed,
                  label: 'Produkcja',
                  value: '${fmt.format(well.productionPerHour)} bbl/h',
                ),
                const SizedBox(width: 16),
                _Stat(
                  icon: Icons.trending_down,
                  label: 'Koszty',
                  value: '${fmt.format(well.upkeepPerHour)} PLN/h',
                  valueColor: Colors.redAccent,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _Stat(
                  icon: Icons.build_outlined,
                  label: 'Stan tech.',
                  value: '${well.technicalCondition}%',
                  valueColor: _conditionColor(well.technicalCondition),
                ),
                const SizedBox(width: 16),
                _Stat(
                  icon: Icons.warning_amber_outlined,
                  label: 'Ryzyko',
                  value: well.riskLevel.toUpperCase(),
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
                    'Złoże: ${fmt.format(well.reservoirRemaining)} / ${fmt.format(well.reservoirMax)} bbl',
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

  Color _conditionColor(int v) {
    if (v >= 70) return Colors.green;
    if (v >= 40) return Colors.orange;
    return Colors.red;
  }

  Color _riskColor(String level) {
    switch (level) {
      case 'low':
        return Colors.green;
      case 'medium':
        return Colors.orange;
      case 'high':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  Color _reservoirColor(double pct) {
    if (pct > 50) return Colors.blue;
    if (pct > 20) return Colors.orange;
    return Colors.red;
  }
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      'active' => ('Aktywna', Colors.green),
      'paused' => ('Wstrzymana', Colors.orange),
      'damaged' => ('Uszkodzona', Colors.red),
      'blowout' => ('AWARIA', Colors.red),
      'offline' => ('Offline', Colors.grey),
      _ => (status, Colors.grey),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.15),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.5)),
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
