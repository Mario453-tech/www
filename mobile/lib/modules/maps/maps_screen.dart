import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../i18n/locale_provider.dart';
import '../../models/map_data.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/app_colors.dart';

class MapsScreen extends StatefulWidget {
  const MapsScreen({super.key});

  @override
  State<MapsScreen> createState() => _MapsScreenState();
}

class _MapsScreenState extends State<MapsScreen> {
  MapData? _data;
  bool _loading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    if (!mounted) return;
    setState(() {
      _loading = true;
      _hasError = false;
    });
    try {
      final data = await ApiService.getMapData(token);
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _loading = false; _hasError = true; });
    }
  }

  Future<void> _applyPermit(MapRegion region) async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;

    final costStr = region.permit.applicationCost != null
        ? _fmt(region.permit.applicationCost!)
        : '?';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(context.t('maps.apply_confirm_title')),
        content: Text(context.t('maps.apply_confirm_body', {
          'region': region.name,
          'cost': costStr,
        })),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.t('common.cancel')),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.t('maps.apply_confirm_ok')),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final result = await ApiService.applyPermit(token, region.id);
      if (!mounted) return;
      final minutes = (result['review_minutes'] as num?)?.toInt() ?? 0;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(context.t('maps.apply_success', {'minutes': '$minutes'})),
        backgroundColor: AppColors.green,
      ));
      _load();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(context.t('maps.apply_error')),
        backgroundColor: AppColors.red,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_hasError && _data == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(context.t('maps.error'),
                style: const TextStyle(color: AppColors.text2)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _load,
              child: Text(context.t('common.retry')),
            ),
          ],
        ),
      );
    }

    final regions = _data?.regions ?? [];

    return RefreshIndicator(
      onRefresh: _load,
      color: AppColors.gold,
      child: regions.isEmpty
          ? ListView(
              children: [
                const SizedBox(height: 80),
                Center(
                  child: Text(context.t('maps.regions.empty'),
                      style: const TextStyle(color: AppColors.text2)),
                ),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: regions.length,
              itemBuilder: (_, i) => _RegionCard(
                region: regions[i],
                locations: _data!.locationsForRegion(regions[i].id),
                onApply: () => _applyPermit(regions[i]),
              ),
            ),
    );
  }

  String _fmt(double v) =>
      v.toStringAsFixed(0).replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]} ',
          );
}

class _RegionCard extends StatefulWidget {
  final MapRegion region;
  final List<MapLocation> locations;
  final VoidCallback onApply;

  const _RegionCard({
    required this.region,
    required this.locations,
    required this.onApply,
  });

  @override
  State<_RegionCard> createState() => _RegionCardState();
}

class _RegionCardState extends State<_RegionCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final permit = widget.region.permit;
    final hasLocations = widget.locations.isNotEmpty;

    return Card(
      color: AppColors.bg3,
      margin: const EdgeInsets.symmetric(vertical: 5),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(
          color: permit.hasActive ? AppColors.green.withOpacity(0.4) : AppColors.border,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Header
          InkWell(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(10)),
            onTap: hasLocations ? () => setState(() => _expanded = !_expanded) : null,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
              child: Row(
                children: [
                  // Color dot from region
                  Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: _hexColor(widget.region.colorHex),
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      widget.region.name,
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, color: AppColors.text),
                    ),
                  ),
                  _PermitChip(permit: permit),
                  if (hasLocations) ...[
                    const SizedBox(width: 4),
                    Icon(
                      _expanded ? Icons.expand_less : Icons.expand_more,
                      color: AppColors.text2,
                      size: 18,
                    ),
                  ],
                ],
              ),
            ),
          ),

          // Permit action row
          if (!permit.hasActive) ...[
            const Divider(height: 1, color: AppColors.borderFaint),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 10, 8),
              child: _PermitActionRow(
                permit: permit,
                onApply: widget.onApply,
              ),
            ),
          ],

          // Locations list (expanded)
          if (_expanded && hasLocations) ...[
            const Divider(height: 1, color: AppColors.borderFaint),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.t('maps.locations.header'),
                    style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.text3,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.8),
                  ),
                  const SizedBox(height: 6),
                  ...widget.locations.map((loc) => _LocationRow(loc: loc)),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Color _hexColor(String hex) {
    try {
      final h = hex.replaceFirst('#', '');
      return Color(int.parse('FF$h', radix: 16));
    } catch (_) {
      return AppColors.gold;
    }
  }
}

class _PermitChip extends StatelessWidget {
  final PermitInfo permit;
  const _PermitChip({required this.permit});

  @override
  Widget build(BuildContext context) {
    final (label, bg, fg) = switch (permit.status) {
      'active'       => (context.t('maps.permit.active'),   AppColors.greenDim, AppColors.green),
      'pending'      => (context.t('maps.permit.pending'),  AppColors.goldDim,  AppColors.warn),
      'delayed'      => (context.t('maps.permit.delayed'),  AppColors.goldDim,  AppColors.warn),
      'refused'      => (context.t('maps.permit.refused'),  AppColors.redDim,   AppColors.red),
      'no_decision'  => (context.t('maps.permit.no_decision'), AppColors.redDim, AppColors.red),
      _              => (context.t('maps.permit.none'),     AppColors.borderFaint, AppColors.text3),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: fg.withOpacity(0.3)),
      ),
      child: Text(label, style: TextStyle(fontSize: 10, color: fg, fontWeight: FontWeight.w700)),
    );
  }
}

class _PermitActionRow extends StatelessWidget {
  final PermitInfo permit;
  final VoidCallback onApply;
  const _PermitActionRow({required this.permit, required this.onApply});

  @override
  Widget build(BuildContext context) {
    // Show time info for pending / refused with cooldown
    if (permit.isPending) {
      final min = permit.minutesLeft;
      return Text(
        min != null
            ? context.t('maps.permit.minutes_left', {'minutes': '$min'})
            : context.t('maps.permit.pending'),
        style: const TextStyle(fontSize: 12, color: AppColors.warn),
      );
    }

    if (permit.status == 'refused' &&
        permit.cooldownMinutes != null &&
        permit.cooldownMinutes! > 0) {
      return Text(
        context.t('maps.permit.cooldown', {'minutes': '${permit.cooldownMinutes}'}),
        style: const TextStyle(fontSize: 12, color: AppColors.red),
      );
    }

    if (permit.status == 'no_decision') {
      return Text(
        context.t('maps.permit.no_decision'),
        style: const TextStyle(fontSize: 12, color: AppColors.red),
      );
    }

    // Can apply: status == 'none' or refused with expired cooldown
    if (permit.canApply) {
      final cost = permit.applicationCost;
      return Row(
        children: [
          if (cost != null) ...[
            Text(
              context.t('maps.apply_permit_cost', {'cost': _fmt(cost)}),
              style: const TextStyle(fontSize: 12, color: AppColors.text2),
            ),
            const SizedBox(width: 8),
          ],
          ElevatedButton(
            onPressed: onApply,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.goldDark,
              foregroundColor: AppColors.text,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
              textStyle:
                  const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: Text(context.t('maps.apply_permit')),
          ),
        ],
      );
    }

    return const SizedBox.shrink();
  }

  String _fmt(double v) =>
      v.toStringAsFixed(0).replaceAllMapped(
            RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]} ',
          );
}

class _LocationRow extends StatelessWidget {
  final MapLocation loc;
  const _LocationRow({required this.loc});

  @override
  Widget build(BuildContext context) {
    final (label, color) = loc.occupiedByMe
        ? (context.t('maps.location.occupied_me'), AppColors.green)
        : loc.occupiedByAnyone
            ? (context.t('maps.location.occupied_other'), AppColors.text3)
            : (context.t('maps.location.available'), AppColors.blue);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Icon(
            loc.occupiedByMe
                ? Icons.oil_barrel
                : loc.occupiedByAnyone
                    ? Icons.block
                    : Icons.place_outlined,
            size: 14,
            color: color,
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(loc.name,
                style:
                    const TextStyle(fontSize: 12, color: AppColors.text)),
          ),
          Text(
            '${loc.wellType == 'offshore' ? '⚓' : '🏭'} $label',
            style: TextStyle(fontSize: 11, color: color),
          ),
        ],
      ),
    );
  }
}
