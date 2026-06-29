import 'package:flutter/material.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_theme.dart';
import '../dashboard_styles.dart';

/// KPI card mirroring the web `.status-kpi` block.
class KpiCard extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final String? sub;
  final bool money;
  final double? progress;
  final bool pulse;

  const KpiCard({
    super.key,
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.value,
    this.sub,
    this.money = false,
    this.progress,
    this.pulse = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: DashboardStyles.kpiCardDecoration(),
      padding: DashboardStyles.cardPadding,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _IconTile(icon: icon, color: iconColor),
              const SizedBox(width: DashboardStyles.gapMd),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label.toUpperCase(),
                      style: AppTheme.kpiLabel,
                      maxLines: 2,
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        if (pulse) ...[
                          const _PulseDot(),
                          const SizedBox(width: 8),
                        ],
                        Flexible(
                          child: Text(
                            value,
                            style: DashboardStyles.kpiValue(money),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    if (sub != null) ...[
                      const SizedBox(height: 2),
                      Text(sub!, style: DashboardStyles.kpiSub),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (progress != null) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(
                DashboardStyles.progressRadius,
              ),
              child: LinearProgressIndicator(
                value: progress!.clamp(0.0, 1.0),
                minHeight: DashboardStyles.progressHeight,
                backgroundColor: DashboardStyles.progressBackground,
                valueColor: const AlwaysStoppedAnimation(AppColors.gold),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _IconTile extends StatelessWidget {
  final IconData icon;
  final Color color;

  const _IconTile({required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: DashboardStyles.kpiIconTileSize,
      height: DashboardStyles.kpiIconTileSize,
      decoration: DashboardStyles.kpiIconTileDecoration(color),
      child: Icon(icon, size: DashboardStyles.kpiIconSize, color: color),
    );
  }
}

class _PulseDot extends StatefulWidget {
  const _PulseDot();

  @override
  State<_PulseDot> createState() => _PulseDotState();
}

class _PulseDotState extends State<_PulseDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (_, __) {
        final progress = _controller.value;
        return Container(
          width: DashboardStyles.pulseDotSize,
          height: DashboardStyles.pulseDotSize,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: DashboardStyles.pulseDotColor,
            boxShadow: DashboardStyles.pulseDotShadow(progress),
          ),
        );
      },
    );
  }
}
