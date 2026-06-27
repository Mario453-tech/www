import 'package:flutter/material.dart';
import '../../../theme/app_colors.dart';
import '../../../theme/app_theme.dart';

/// Kafelek KPI odwzorowujacy `.status-kpi` z webowego dashboardu:
/// ikona w zlotym kwadracie, WIELKA etykieta, duza wartosc (opcjonalnie zielona),
/// podtytul oraz opcjonalny pasek postepu (magazyn).
///
/// KPI card mirroring the web `.status-kpi`: gold icon tile, uppercase label,
/// large value (optionally green), subtitle and an optional progress bar.
class KpiCard extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String value;
  final String? sub;

  /// Czy wartosc ma byc zielona (pieniadze).
  final bool money;

  /// 0..1 — gdy podane, pokazuje pasek postepu (np. zapelnienie magazynu).
  final double? progress;

  /// Pulsujaca zielona kropka (status firmy).
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
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.bg3, AppColors.bg2],
        ),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.goldBorder.withValues(alpha: 0.15)),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _IconTile(icon: icon, color: iconColor),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label.toUpperCase(),
                        style: AppTheme.kpiLabel, maxLines: 2),
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
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                              letterSpacing: -0.4,
                              color: money ? AppColors.green : AppColors.text,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                    if (sub != null) ...[
                      const SizedBox(height: 2),
                      Text(sub!,
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.text3)),
                    ],
                  ],
                ),
              ),
            ],
          ),
          if (progress != null) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(2),
              child: LinearProgressIndicator(
                value: progress!.clamp(0.0, 1.0),
                minHeight: 3,
                backgroundColor: const Color(0x12FFFFFF),
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
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.30)),
      ),
      child: Icon(icon, size: 18, color: color),
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
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (_, __) {
        final t = _c.value;
        return Container(
          width: 9,
          height: 9,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.green,
            boxShadow: [
              BoxShadow(
                color: AppColors.green.withValues(alpha: (1 - t) * 0.5),
                blurRadius: 0,
                spreadRadius: t * 6,
              ),
            ],
          ),
        );
      },
    );
  }
}
