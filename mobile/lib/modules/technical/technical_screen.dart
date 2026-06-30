import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../i18n/locale_provider.dart';
import '../../models/technical_data.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/app_colors.dart';

class TechnicalScreen extends StatefulWidget {
  const TechnicalScreen({super.key});

  @override
  State<TechnicalScreen> createState() => _TechnicalScreenState();
}

class _TechnicalScreenState extends State<TechnicalScreen>
    with SingleTickerProviderStateMixin {
  TechnicalData? _data;
  bool _loading = true;
  bool _hasError = false;
  late final TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
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
      final data = await ApiService.getTechnicalData(token);
      if (mounted) setState(() { _data = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() { _loading = false; _hasError = true; });
    }
  }

  Future<void> _showFireDialog(TechnicalEngineer eng) async {
    final name = '${eng.firstName} ${eng.lastName}';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: AppColors.bg3,
        title: Text(context.t('technical.fire.confirm_title'),
            style: const TextStyle(color: AppColors.text)),
        content: Text(
          context.t('technical.fire.confirm_body', {'name': name}),
          style: const TextStyle(color: AppColors.text2),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.t('common.cancel'),
                style: const TextStyle(color: AppColors.text2)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.red,
              foregroundColor: AppColors.text,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.t('technical.fire.ok')),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final token = context.read<AuthProvider>().token;
      if (token == null) return;
      await ApiService.fireTechnicalStaff(token, eng.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(context.t('technical.fire.success', {'name': name})),
        backgroundColor: AppColors.green,
      ));
      _load();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(context.t('technical.fire.error')),
        backgroundColor: AppColors.red,
      ));
    }
  }

  Future<void> _showAssignTaskSheet(TechnicalEngineer eng) async {
    final name = '${eng.firstName} ${eng.lastName}';
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.bg3,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => _AssignTaskSheet(
        engineer: eng,
        title: context.t('technical.assign.title', {'name': name}),
        onAssigned: () {
          _load();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) {
      return const Center(child: CircularProgressIndicator(color: AppColors.gold));
    }

    if (_hasError && _data == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(context.t('technical.error'),
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

    final data = _data;
    final companyName = data?.companyName ?? '';

    return Column(
      children: [
        // Header with company name
        Container(
          width: double.infinity,
          color: AppColors.bg2,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${context.t('technical.title')} — $companyName',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: AppColors.gold,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 8),
              TabBar(
                controller: _tabs,
                labelColor: AppColors.gold,
                unselectedLabelColor: AppColors.text2,
                indicatorColor: AppColors.gold,
                labelStyle: const TextStyle(
                    fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 0.6),
                tabs: [
                  Tab(
                    child: _TabLabel(
                      context.t('technical.tab.team'),
                      badge: data?.staffCount,
                    ),
                  ),
                  Tab(
                    child: _TabLabel(
                      context.t('technical.tab.well_staff'),
                      badge: data?.wellPersonnelCount,
                    ),
                  ),
                  Tab(
                    child: _TabLabel(
                      context.t('technical.tab.candidates'),
                      badge: data?.unreviewedCandidates,
                      badgeColor: (data?.unreviewedCandidates ?? 0) > 0
                          ? AppColors.orange
                          : null,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),

        Expanded(
          child: TabBarView(
            controller: _tabs,
            children: [
              // Tab 0 — ZESPÓŁ
              RefreshIndicator(
                onRefresh: _load,
                color: AppColors.gold,
                child: _TeamTab(
                  data: data,
                  onAssign: _showAssignTaskSheet,
                  onFire: _showFireDialog,
                ),
              ),

              // Tab 1 — PERSONEL ODWIERTÓW
              RefreshIndicator(
                onRefresh: _load,
                color: AppColors.gold,
                child: _WellStaffTab(data: data),
              ),

              // Tab 2 — KANDYDACI
              RefreshIndicator(
                onRefresh: _load,
                color: AppColors.gold,
                child: _CandidatesTab(data: data),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

// ─── Tab label with optional badge ────────────────────────────────────────────

class _TabLabel extends StatelessWidget {
  final String text;
  final int? badge;
  final Color? badgeColor;

  const _TabLabel(this.text, {this.badge, this.badgeColor});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(text),
        if (badge != null && badge! > 0) ...[
          const SizedBox(width: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
            decoration: BoxDecoration(
              color: badgeColor ?? AppColors.goldDim,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              '$badge',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: badgeColor != null ? AppColors.text : AppColors.gold,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

// ─── Tab 0: ZESPÓŁ ────────────────────────────────────────────────────────────

class _TeamTab extends StatelessWidget {
  final TechnicalData? data;
  final void Function(TechnicalEngineer) onAssign;
  final void Function(TechnicalEngineer) onFire;

  const _TeamTab({required this.data, required this.onAssign, required this.onFire});

  @override
  Widget build(BuildContext context) {
    final engineers = data?.engineers ?? [];
    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        if (data?.director != null) ...[
          _DirectorCard(
            director: data!.director!,
            bonus: data!.managerBonus,
          ),
          const SizedBox(height: 12),
        ],
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Text(
            context.t('technical.engineers.header', {'count': '${engineers.length}'}),
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.text3,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
            ),
          ),
        ),
        if (engineers.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 20),
            child: Center(
              child: Text(
                context.t('technical.engineers.empty'),
                style: const TextStyle(color: AppColors.text2),
              ),
            ),
          )
        else
          ...engineers.map(
            (e) => _EngineerCard(
              engineer: e,
              onAssign: () => onAssign(e),
              onFire: () => onFire(e),
            ),
          ),
      ],
    );
  }
}

// ─── Director card ─────────────────────────────────────────────────────────────

class _DirectorCard extends StatelessWidget {
  final TechnicalDirector director;
  final ManagerBonus bonus;

  const _DirectorCard({required this.director, required this.bonus});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.bg3,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.goldBorder),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                // Gold avatar with initials
                Container(
                  width: 46,
                  height: 46,
                  decoration: const BoxDecoration(
                    color: AppColors.goldDark,
                    shape: BoxShape.circle,
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    director.initials,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.text,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${director.firstName} ${director.lastName}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                          color: AppColors.text,
                        ),
                      ),
                      Text(
                        context.t('technical.director.label'),
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.gold,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Info row: tenure · exp · salary
            Text(
              '${context.t('technical.director.tenure', {'days': '${director.daysEmployed}'})} · '
              '${context.t('technical.director.experience', {'years': '${director.experienceYears}'})} · '
              '${context.t('technical.director.salary', {'salary': _fmt(director.salary)})}',
              style: const TextStyle(fontSize: 12, color: AppColors.text2),
            ),
            const SizedBox(height: 10),
            // Bonus chips
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (bonus.timePct > 0)
                  _BonusChip(
                    context.t('technical.bonus.time', {'pct': '${bonus.timePct.toStringAsFixed(1)}'}),
                    AppColors.green,
                  ),
                if (bonus.costPct > 0)
                  _BonusChip(
                    context.t('technical.bonus.cost', {'pct': '${bonus.costPct.toStringAsFixed(1)}'}),
                    AppColors.green,
                  ),
                _BonusChip(
                  context.t('technical.bonus.org', {'skill': '${bonus.skill}'}),
                  AppColors.gold,
                ),
              ],
            ),
            const SizedBox(height: 10),
            // Progress bar — always 100% for the manager (represents full authority)
            LinearProgressIndicator(
              value: 1.0,
              backgroundColor: AppColors.goldDim,
              valueColor: const AlwaysStoppedAnimation<Color>(AppColors.gold),
              minHeight: 3,
              borderRadius: BorderRadius.circular(2),
            ),
          ],
        ),
      ),
    );
  }

  String _fmt(double v) => v
      .toStringAsFixed(0)
      .replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]} ');
}

class _BonusChip extends StatelessWidget {
  final String label;
  final Color color;

  const _BonusChip(this.label, this.color);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.4)),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600),
      ),
    );
  }
}

// ─── Engineer card ─────────────────────────────────────────────────────────────

class _EngineerCard extends StatelessWidget {
  final TechnicalEngineer engineer;
  final VoidCallback onAssign;
  final VoidCallback onFire;

  const _EngineerCard({
    required this.engineer,
    required this.onAssign,
    required this.onFire,
  });

  @override
  Widget build(BuildContext context) {
    final eng = engineer;
    final (statusLabel, statusColor) = switch (eng.status) {
      'busy'     => (context.t('technical.engineer.busy'),     AppColors.orange),
      'on_leave' => (context.t('technical.engineer.on_leave'), AppColors.text3),
      _          => (context.t('technical.engineer.available'), AppColors.green),
    };

    return Card(
      color: AppColors.bg3,
      margin: const EdgeInsets.symmetric(vertical: 5),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: AppColors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header row: spec icon + name + status chip
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: AppColors.bg4,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: AppColors.goldBorder),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    eng.specIcon,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: AppColors.gold,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${eng.firstName} ${eng.lastName}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppColors.text,
                        ),
                      ),
                      Text(
                        eng.specName.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 10,
                          color: AppColors.text3,
                          letterSpacing: 0.6,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: statusColor.withOpacity(0.4)),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      fontSize: 10,
                      color: statusColor,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 10),

            // Skill bar
            Row(
              children: [
                Text(
                  context.t('technical.engineer.skill'),
                  style: const TextStyle(fontSize: 11, color: AppColors.text3),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: LinearProgressIndicator(
                    value: eng.skillLevel / 10.0,
                    backgroundColor: AppColors.bg4,
                    valueColor: const AlwaysStoppedAnimation<Color>(AppColors.gold),
                    minHeight: 4,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  '${eng.skillLevel}/10',
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.gold,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 8),

            // Exp and salary
            Text(
              '${context.t('technical.engineer.experience', {'years': '${eng.experienceYears}'})}'
              '  ·  '
              '${context.t('technical.engineer.salary', {'salary': _fmt(eng.salary)})}',
              style: const TextStyle(fontSize: 12, color: AppColors.text2),
            ),

            if (eng.isAvailable) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  // Assign task link
                  GestureDetector(
                    onTap: onAssign,
                    child: Text(
                      context.t('technical.engineer.assign_task'),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.gold,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  const Spacer(),
                  // Fire button
                  SizedBox(
                    height: 28,
                    child: ElevatedButton(
                      onPressed: onFire,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.red.withOpacity(0.15),
                        foregroundColor: AppColors.red,
                        side: BorderSide(color: AppColors.red.withOpacity(0.4)),
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        textStyle: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w700),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        elevation: 0,
                      ),
                      child: Text(context.t('technical.engineer.fire')),
                    ),
                  ),
                ],
              ),
            ] else if (eng.isBusy && eng.activeTaskType != null) ...[
              const SizedBox(height: 6),
              Text(
                eng.activeTaskLabel ?? eng.activeTaskType!,
                style: const TextStyle(fontSize: 11, color: AppColors.orange),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _fmt(double v) => v
      .toStringAsFixed(0)
      .replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]} ');
}

// ─── Tab 1: PERSONEL ODWIERTÓW ─────────────────────────────────────────────────

class _WellStaffTab extends StatelessWidget {
  final TechnicalData? data;
  const _WellStaffTab({required this.data});

  @override
  Widget build(BuildContext context) {
    final wells = data?.wellPersonnel ?? [];
    if (wells.isEmpty) {
      return ListView(
        children: [
          const SizedBox(height: 80),
          Center(
            child: Text(context.t('technical.well_staff.empty'),
                style: const TextStyle(color: AppColors.text2)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: wells.length,
      itemBuilder: (_, i) => _WellPersonnelRow(entry: wells[i]),
    );
  }
}

class _WellPersonnelRow extends StatelessWidget {
  final WellPersonnelEntry entry;
  const _WellPersonnelRow({required this.entry});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.bg3,
      margin: const EdgeInsets.symmetric(vertical: 5),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              entry.wellName,
              style: const TextStyle(
                  fontWeight: FontWeight.w700, color: AppColors.text),
            ),
            const SizedBox(height: 8),
            _SlotRow(
              label: context.t('technical.well_staff.operator'),
              slot: entry.operator,
              missing: !entry.hasOperator,
              missingLabel: context.t('technical.well_staff.missing'),
            ),
            const SizedBox(height: 4),
            _SlotRow(
              label: context.t('technical.well_staff.technician'),
              slot: entry.technician,
              missing: !entry.hasTechnician,
              missingLabel: context.t('technical.well_staff.missing'),
            ),
          ],
        ),
      ),
    );
  }
}

class _SlotRow extends StatelessWidget {
  final String label;
  final WellPersonnelSlot? slot;
  final bool missing;
  final String missingLabel;

  const _SlotRow({
    required this.label,
    required this.slot,
    required this.missing,
    required this.missingLabel,
  });

  @override
  Widget build(BuildContext context) {
    final color = missing ? AppColors.orange : AppColors.text2;
    return Row(
      children: [
        SizedBox(
          width: 70,
          child: Text(
            label,
            style: const TextStyle(fontSize: 11, color: AppColors.text3),
          ),
        ),
        if (missing)
          Text(missingLabel, style: TextStyle(fontSize: 12, color: color))
        else ...[
          Text(slot?.name ?? '', style: TextStyle(fontSize: 12, color: color)),
          if (slot != null && slot!.specCode.isNotEmpty) ...[
            const SizedBox(width: 6),
            Text(
              slot!.specCode.toUpperCase(),
              style: const TextStyle(fontSize: 10, color: AppColors.text3),
            ),
          ],
        ],
      ],
    );
  }
}

// ─── Tab 2: KANDYDACI ──────────────────────────────────────────────────────────

class _CandidatesTab extends StatelessWidget {
  final TechnicalData? data;
  const _CandidatesTab({required this.data});

  @override
  Widget build(BuildContext context) {
    final cands = data?.candidates ?? [];
    if (cands.isEmpty) {
      return ListView(
        children: [
          const SizedBox(height: 80),
          Center(
            child: Text(context.t('technical.candidates.empty'),
                style: const TextStyle(color: AppColors.text2)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: cands.length,
      itemBuilder: (_, i) => _CandidateCard(candidate: cands[i]),
    );
  }
}

class _CandidateCard extends StatelessWidget {
  final TechnicalCandidate candidate;
  const _CandidateCard({required this.candidate});

  @override
  Widget build(BuildContext context) {
    final c = candidate;
    final reviewLabel = c.hasReview
        ? context.t('technical.candidate.reviewed')
        : context.t('technical.candidate.not_reviewed');
    final reviewColor = c.hasReview ? AppColors.green : AppColors.orange;

    return Card(
      color: AppColors.bg3,
      margin: const EdgeInsets.symmetric(vertical: 5),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: const BorderSide(color: AppColors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${c.firstName} ${c.lastName}',
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, color: AppColors.text),
                      ),
                      Text(
                        c.specName.toUpperCase(),
                        style: const TextStyle(
                            fontSize: 10, color: AppColors.text3, letterSpacing: 0.5),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(
                    color: reviewColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: reviewColor.withOpacity(0.4)),
                  ),
                  child: Text(
                    reviewLabel,
                    style: TextStyle(
                        fontSize: 10, color: reviewColor, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            // Skill bar
            Row(
              children: [
                Expanded(
                  child: LinearProgressIndicator(
                    value: c.skillLevel / 10.0,
                    backgroundColor: AppColors.bg4,
                    valueColor: const AlwaysStoppedAnimation<Color>(AppColors.gold),
                    minHeight: 4,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  '${c.skillLevel}/10',
                  style: const TextStyle(
                      fontSize: 11, color: AppColors.gold, fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Text(
                  context.t('technical.candidate.experience', {'years': '${c.experienceYears}'}),
                  style: const TextStyle(fontSize: 12, color: AppColors.text2),
                ),
                const SizedBox(width: 12),
                Text(
                  context.t('technical.candidate.salary', {
                    'salary': c.salary
                        .toStringAsFixed(0)
                        .replaceAllMapped(
                          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
                          (m) => '${m[1]} ',
                        ),
                  }),
                  style: const TextStyle(fontSize: 12, color: AppColors.text2),
                ),
                const Spacer(),
                Text(
                  context.t('technical.candidate.expires', {'hours': '${c.hoursRemaining}'}),
                  style: TextStyle(
                    fontSize: 11,
                    color: c.hoursRemaining < 24 ? AppColors.red : AppColors.text3,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Assign Task bottom sheet ──────────────────────────────────────────────────

class _AssignTaskSheet extends StatefulWidget {
  final TechnicalEngineer engineer;
  final String title;
  final VoidCallback onAssigned;

  const _AssignTaskSheet({
    required this.engineer,
    required this.title,
    required this.onAssigned,
  });

  @override
  State<_AssignTaskSheet> createState() => _AssignTaskSheetState();
}

class _AssignTaskSheetState extends State<_AssignTaskSheet> {
  List<AvailableTask>? _tasks;
  List<WellOption>? _wells;
  bool _loadingTasks = true;
  AvailableTask? _selectedTask;
  WellOption? _selectedWell;
  bool _assigning = false;
  String? _errorMsg;

  @override
  void initState() {
    super.initState();
    _loadTasks();
  }

  Future<void> _loadTasks() async {
    final token = context.read<AuthProvider>().token;
    if (token == null) return;
    try {
      final result =
          await ApiService.getTechnicalAvailableTasks(token, widget.engineer.id);
      final taskList = (result['tasks'] as List<dynamic>? ?? [])
          .map((e) => AvailableTask.fromJson(e as Map<String, dynamic>))
          .toList();
      final wellList = (result['wells'] as List<dynamic>? ?? [])
          .map((e) => WellOption.fromJson(e as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _tasks = taskList;
          _wells = wellList;
          _loadingTasks = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingTasks = false);
    }
  }

  Future<void> _assign() async {
    if (_selectedTask == null) return;
    if (_selectedTask!.needsWell && _selectedWell == null) return;

    final token = context.read<AuthProvider>().token;
    if (token == null) return;

    setState(() { _assigning = true; _errorMsg = null; });

    try {
      await ApiService.assignTechnicalTask(
        token,
        widget.engineer.id,
        _selectedTask!.type,
        wellId: _selectedTask!.needsWell ? _selectedWell?.id : null,
      );
      if (!mounted) return;
      // Capture before pop — context becomes deactivated after Navigator.pop().
      final messenger = ScaffoldMessenger.of(context);
      final successMsg = context.t('technical.assign.success', {
        'hours_min': '${_selectedTask!.hoursMin}',
        'hours_max': '${_selectedTask!.hoursMax}',
      });
      Navigator.pop(context);
      messenger.showSnackBar(SnackBar(
        content: Text(successMsg),
        backgroundColor: AppColors.green,
      ));
      widget.onAssigned();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _assigning = false;
        _errorMsg = context.t('technical.assign.error');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomPad = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottomPad),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Title
          Text(
            widget.title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.text,
            ),
          ),
          const SizedBox(height: 16),

          if (_loadingTasks)
            Center(
              child: Column(
                children: [
                  const CircularProgressIndicator(color: AppColors.gold),
                  const SizedBox(height: 8),
                  Text(context.t('technical.assign.loading'),
                      style: const TextStyle(color: AppColors.text2)),
                ],
              ),
            )
          else if (_tasks == null || _tasks!.isEmpty)
            Text(context.t('technical.assign.no_tasks'),
                style: const TextStyle(color: AppColors.text2))
          else ...[
            // Task picker
            Text(context.t('technical.assign.select_task'),
                style: const TextStyle(fontSize: 11, color: AppColors.text3)),
            const SizedBox(height: 6),
            Container(
              decoration: BoxDecoration(
                color: AppColors.bg4,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: AppColors.border),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<AvailableTask>(
                  value: _selectedTask,
                  dropdownColor: AppColors.bg4,
                  hint: Text(context.t('technical.assign.select_task'),
                      style: const TextStyle(color: AppColors.text3, fontSize: 13)),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  items: _tasks!.map((t) {
                    final hours = '${t.hoursMin}–${t.hoursMax} h';
                    return DropdownMenuItem(
                      value: t,
                      child: Text(
                        '${t.label}  ($hours)',
                        style: const TextStyle(color: AppColors.text, fontSize: 13),
                      ),
                    );
                  }).toList(),
                  onChanged: (t) => setState(() {
                    _selectedTask = t;
                    _selectedWell = null;
                    _errorMsg = null;
                  }),
                ),
              ),
            ),

            // Well picker (shown only when task needs_well)
            if (_selectedTask != null && _selectedTask!.needsWell) ...[
              const SizedBox(height: 12),
              Text(context.t('technical.assign.select_well'),
                  style: const TextStyle(fontSize: 11, color: AppColors.text3)),
              const SizedBox(height: 6),
              Container(
                decoration: BoxDecoration(
                  color: AppColors.bg4,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.border),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<WellOption>(
                    value: _selectedWell,
                    dropdownColor: AppColors.bg4,
                    hint: Text(context.t('technical.assign.select_well'),
                        style: const TextStyle(color: AppColors.text3, fontSize: 13)),
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    items: (_wells ?? []).map((w) {
                      return DropdownMenuItem(
                        value: w,
                        child: Text(w.name,
                            style: const TextStyle(color: AppColors.text, fontSize: 13)),
                      );
                    }).toList(),
                    onChanged: (w) => setState(() {
                      _selectedWell = w;
                      _errorMsg = null;
                    }),
                  ),
                ),
              ),
            ],

            if (_errorMsg != null) ...[
              const SizedBox(height: 8),
              Text(_errorMsg!, style: const TextStyle(color: AppColors.red, fontSize: 12)),
            ],

            const SizedBox(height: 16),

            ElevatedButton(
              onPressed: (_assigning ||
                      _selectedTask == null ||
                      (_selectedTask!.needsWell && _selectedWell == null))
                  ? null
                  : _assign,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.goldDark,
                foregroundColor: AppColors.text,
                disabledBackgroundColor: AppColors.bg4,
                disabledForegroundColor: AppColors.text3,
              ),
              child: _assigning
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: AppColors.text))
                  : Text(context.t('technical.assign.confirm')),
            ),
          ],
        ],
      ),
    );
  }
}
