class Well {
  final int id;
  final String name;
  final String location;
  final String status;
  final String wellType;
  final String transportType;
  final double productionPerHour;
  final double upkeepPerHour;
  final int technicalCondition;
  final int wearLevel;
  final String equipmentTier;
  final int equipmentUpgradeLevel;
  final String productionMode;
  final double reservoirRemaining;
  final double reservoirMax;
  final String riskLevel;
  final double riskScore;
  final String? lastProductionAt;

  const Well({
    required this.id,
    required this.name,
    required this.location,
    required this.status,
    required this.wellType,
    required this.transportType,
    required this.productionPerHour,
    required this.upkeepPerHour,
    required this.technicalCondition,
    required this.wearLevel,
    required this.equipmentTier,
    required this.equipmentUpgradeLevel,
    required this.productionMode,
    required this.reservoirRemaining,
    required this.reservoirMax,
    required this.riskLevel,
    required this.riskScore,
    this.lastProductionAt,
  });

  double get reservoirPercent =>
      reservoirMax > 0 ? (reservoirRemaining / reservoirMax * 100).clamp(0, 100) : 0;

  bool get isActive => status == 'active';
  bool get isPaused => status == 'paused';
  bool get isDamaged => status == 'damaged' || status == 'blowout';

  factory Well.fromJson(Map<String, dynamic> j) => Well(
        id: (j['id'] as num).toInt(),
        name: j['name'] as String? ?? 'Well #${j['id']}',
        location: j['location'] as String? ?? '',
        status: j['status'] as String? ?? 'active',
        wellType: j['well_type'] as String? ?? 'onshore',
        transportType: j['transport_type'] as String? ?? 'nieustawiony',
        productionPerHour: (j['production_per_hour'] as num?)?.toDouble() ?? 0,
        upkeepPerHour: (j['upkeep_per_hour'] as num?)?.toDouble() ?? 0,
        technicalCondition: (j['technical_condition'] as num?)?.toInt() ?? 100,
        wearLevel: (j['wear_level'] as num?)?.toInt() ?? 0,
        equipmentTier: j['equipment_tier'] as String? ?? 'standard',
        equipmentUpgradeLevel: (j['equipment_upgrade_level'] as num?)?.toInt() ?? 0,
        productionMode: j['production_mode'] as String? ?? 'normal',
        reservoirRemaining: (j['reservoir_remaining'] as num?)?.toDouble() ?? 0,
        reservoirMax: (j['reservoir_max'] as num?)?.toDouble() ?? 0,
        riskLevel: j['risk_level'] as String? ?? 'low',
        riskScore: (j['risk_score'] as num?)?.toDouble() ?? 0,
        lastProductionAt: j['last_production_at'] as String?,
      );
}
