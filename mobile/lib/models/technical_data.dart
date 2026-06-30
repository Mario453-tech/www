class TechnicalDirector {
  final int id;
  final String firstName;
  final String lastName;
  final String initials;
  final int skillOrganization;
  final int experienceYears;
  final int daysEmployed;
  final double salary;

  const TechnicalDirector({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.initials,
    required this.skillOrganization,
    required this.experienceYears,
    required this.daysEmployed,
    required this.salary,
  });

  factory TechnicalDirector.fromJson(Map<String, dynamic> j) => TechnicalDirector(
        id: (j['id'] as num?)?.toInt() ?? 0,
        firstName: j['first_name'] as String? ?? '',
        lastName: j['last_name'] as String? ?? '',
        initials: j['initials'] as String? ?? '',
        skillOrganization: (j['skill_organization'] as num?)?.toInt() ?? 0,
        experienceYears: (j['experience_years'] as num?)?.toInt() ?? 0,
        daysEmployed: (j['days_employed'] as num?)?.toInt() ?? 0,
        salary: (j['salary'] as num?)?.toDouble() ?? 0,
      );
}

class ManagerBonus {
  final int skill;
  final double timePct;
  final double costPct;

  const ManagerBonus({
    required this.skill,
    required this.timePct,
    required this.costPct,
  });

  factory ManagerBonus.fromJson(Map<String, dynamic> j) => ManagerBonus(
        skill: (j['skill'] as num?)?.toInt() ?? 0,
        timePct: (j['time_pct'] as num?)?.toDouble() ?? 0,
        costPct: (j['cost_pct'] as num?)?.toDouble() ?? 0,
      );
}

class TechnicalEngineer {
  final int id;
  final String firstName;
  final String lastName;
  final String specCode;
  final String specIcon;
  final String specName;
  final int skillLevel;
  final int experienceYears;
  final double salary;
  final String status;
  final String? activeTaskType;
  final String? activeTaskLabel;
  final String? activeTaskEnd;

  const TechnicalEngineer({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.specCode,
    required this.specIcon,
    required this.specName,
    required this.skillLevel,
    required this.experienceYears,
    required this.salary,
    required this.status,
    this.activeTaskType,
    this.activeTaskLabel,
    this.activeTaskEnd,
  });

  bool get isAvailable => status == 'active';
  bool get isBusy => status == 'busy';

  factory TechnicalEngineer.fromJson(Map<String, dynamic> j) => TechnicalEngineer(
        id: (j['id'] as num?)?.toInt() ?? 0,
        firstName: j['first_name'] as String? ?? '',
        lastName: j['last_name'] as String? ?? '',
        specCode: j['spec_code'] as String? ?? '',
        specIcon: j['spec_icon'] as String? ?? '???',
        specName: j['spec_name'] as String? ?? '',
        skillLevel: (j['skill_level'] as num?)?.toInt() ?? 1,
        experienceYears: (j['experience_years'] as num?)?.toInt() ?? 0,
        salary: (j['salary'] as num?)?.toDouble() ?? 0,
        status: j['status'] as String? ?? 'active',
        activeTaskType: j['active_task_type'] as String?,
        activeTaskLabel: j['active_task_label'] as String?,
        activeTaskEnd: j['active_task_end'] as String?,
      );
}

class WellPersonnelSlot {
  final int? id;
  final String name;
  final String specCode;
  final int skill;

  const WellPersonnelSlot({
    this.id,
    required this.name,
    required this.specCode,
    required this.skill,
  });

  factory WellPersonnelSlot.fromJson(Map<String, dynamic> j) => WellPersonnelSlot(
        id: (j['id'] as num?)?.toInt(),
        name: j['name'] as String? ?? '',
        specCode: j['spec_code'] as String? ?? j['spec'] as String? ?? '',
        skill: (j['skill'] as num?)?.toInt() ?? (j['skill_level'] as num?)?.toInt() ?? 0,
      );
}

class WellPersonnelEntry {
  final int wellId;
  final String wellName;
  final String wellStatus;
  final bool hasOperator;
  final bool hasTechnician;
  final WellPersonnelSlot? operator;
  final WellPersonnelSlot? technician;

  const WellPersonnelEntry({
    required this.wellId,
    required this.wellName,
    required this.wellStatus,
    required this.hasOperator,
    required this.hasTechnician,
    this.operator,
    this.technician,
  });

  factory WellPersonnelEntry.fromJson(Map<String, dynamic> j) {
    final opRaw = j['operator'];
    final techRaw = j['technician'];
    return WellPersonnelEntry(
      wellId: (j['well_id'] as num?)?.toInt() ?? 0,
      wellName: j['well_name'] as String? ?? '',
      wellStatus: j['well_status'] as String? ?? '',
      hasOperator: j['has_operator'] as bool? ?? false,
      hasTechnician: j['has_technician'] as bool? ?? false,
      operator: opRaw is Map<String, dynamic> ? WellPersonnelSlot.fromJson(opRaw) : null,
      technician: techRaw is Map<String, dynamic> ? WellPersonnelSlot.fromJson(techRaw) : null,
    );
  }
}

class TechnicalCandidate {
  final int id;
  final String firstName;
  final String lastName;
  final String specCode;
  final String specName;
  final int skillLevel;
  final int experienceYears;
  final double salary;
  final int hoursRemaining;
  final int? reviewId;

  const TechnicalCandidate({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.specCode,
    required this.specName,
    required this.skillLevel,
    required this.experienceYears,
    required this.salary,
    required this.hoursRemaining,
    this.reviewId,
  });

  bool get hasReview => reviewId != null;

  factory TechnicalCandidate.fromJson(Map<String, dynamic> j) => TechnicalCandidate(
        id: (j['id'] as num?)?.toInt() ?? 0,
        firstName: j['first_name'] as String? ?? '',
        lastName: j['last_name'] as String? ?? '',
        specCode: j['spec_code'] as String? ?? '',
        specName: j['spec_name'] as String? ?? '',
        skillLevel: (j['skill_level'] as num?)?.toInt() ?? 1,
        experienceYears: (j['experience_years'] as num?)?.toInt() ?? 0,
        salary: (j['salary'] as num?)?.toDouble() ?? 0,
        hoursRemaining: (j['hours_remaining'] as num?)?.toInt() ?? 0,
        reviewId: (j['review_id'] as num?)?.toInt(),
      );
}

class TechnicalData {
  final String companyName;
  final TechnicalDirector? director;
  final ManagerBonus managerBonus;
  final int staffCount;
  final List<TechnicalEngineer> engineers;
  final List<WellPersonnelEntry> wellPersonnel;
  final int wellPersonnelCount;
  final List<TechnicalCandidate> candidates;
  final int unreviewedCandidates;

  const TechnicalData({
    required this.companyName,
    this.director,
    required this.managerBonus,
    required this.staffCount,
    required this.engineers,
    required this.wellPersonnel,
    required this.wellPersonnelCount,
    required this.candidates,
    required this.unreviewedCandidates,
  });

  factory TechnicalData.fromJson(Map<String, dynamic> j) {
    final dirRaw = j['director'];
    final bonusRaw = j['manager_bonus'];
    return TechnicalData(
      companyName: j['company_name'] as String? ?? '',
      director: dirRaw is Map<String, dynamic>
          ? TechnicalDirector.fromJson(dirRaw)
          : null,
      managerBonus: bonusRaw is Map<String, dynamic>
          ? ManagerBonus.fromJson(bonusRaw)
          : const ManagerBonus(skill: 0, timePct: 0, costPct: 0),
      staffCount: (j['staff_count'] as num?)?.toInt() ?? 0,
      engineers: (j['engineers'] as List<dynamic>? ?? [])
          .map((e) => TechnicalEngineer.fromJson(e as Map<String, dynamic>))
          .toList(),
      wellPersonnel: (j['well_personnel'] as List<dynamic>? ?? [])
          .map((e) => WellPersonnelEntry.fromJson(e as Map<String, dynamic>))
          .toList(),
      wellPersonnelCount: (j['well_personnel_count'] as num?)?.toInt() ?? 0,
      candidates: (j['candidates'] as List<dynamic>? ?? [])
          .map((e) => TechnicalCandidate.fromJson(e as Map<String, dynamic>))
          .toList(),
      unreviewedCandidates: (j['unreviewed_candidates'] as num?)?.toInt() ?? 0,
    );
  }
}

class AvailableTask {
  final String type;
  final String label;
  final bool needsWell;
  final int hoursMin;
  final int hoursMax;
  final int costMin;
  final int costMax;

  const AvailableTask({
    required this.type,
    required this.label,
    required this.needsWell,
    required this.hoursMin,
    required this.hoursMax,
    required this.costMin,
    required this.costMax,
  });

  factory AvailableTask.fromJson(Map<String, dynamic> j) => AvailableTask(
        type: j['type'] as String? ?? '',
        label: j['label'] as String? ?? '',
        needsWell: j['needs_well'] as bool? ?? false,
        hoursMin: (j['hours_min'] as num?)?.toInt() ?? 0,
        hoursMax: (j['hours_max'] as num?)?.toInt() ?? 0,
        costMin: (j['cost_min'] as num?)?.toInt() ?? 0,
        costMax: (j['cost_max'] as num?)?.toInt() ?? 0,
      );
}

class WellOption {
  final int id;
  final String name;
  final String status;

  const WellOption({required this.id, required this.name, required this.status});

  factory WellOption.fromJson(Map<String, dynamic> j) => WellOption(
        id: (j['id'] as num?)?.toInt() ?? 0,
        name: j['name'] as String? ?? '',
        status: j['status'] as String? ?? '',
      );
}
