class PermitInfo {
  final String status;
  final bool hasActive;
  final int? minutesLeft;
  final int? cooldownMinutes;
  final double? applicationCost;
  final double? requiredCapital;
  final int? requiredLegalLevel;

  const PermitInfo({
    required this.status,
    required this.hasActive,
    this.minutesLeft,
    this.cooldownMinutes,
    this.applicationCost,
    this.requiredCapital,
    this.requiredLegalLevel,
  });

  bool get canApply =>
      status == 'none' ||
      (status == 'refused' && (cooldownMinutes == null || cooldownMinutes == 0));

  bool get isPending => status == 'pending' || status == 'delayed';

  factory PermitInfo.fromJson(Map<String, dynamic> j) => PermitInfo(
        status: j['status'] as String? ?? 'none',
        hasActive: j['has_active'] as bool? ?? false,
        minutesLeft: (j['minutes_left'] as num?)?.toInt(),
        cooldownMinutes: (j['cooldown_minutes'] as num?)?.toInt(),
        applicationCost: (j['application_cost'] as num?)?.toDouble(),
        requiredCapital: (j['required_capital'] as num?)?.toDouble(),
        requiredLegalLevel: (j['required_legal_level'] as num?)?.toInt(),
      );
}

class MapRegion {
  final int id;
  final String code;
  final String name;
  final int politicalRisk;
  final double entryCost;
  final double taxRate;
  final double opexMult;
  final String colorHex;
  final PermitInfo permit;

  const MapRegion({
    required this.id,
    required this.code,
    required this.name,
    required this.politicalRisk,
    required this.entryCost,
    required this.taxRate,
    required this.opexMult,
    required this.colorHex,
    required this.permit,
  });

  factory MapRegion.fromJson(Map<String, dynamic> j) => MapRegion(
        id: (j['id'] as num?)?.toInt() ?? 0,
        code: j['code'] as String? ?? '',
        name: j['name'] as String? ?? '',
        politicalRisk: (j['political_risk'] as num?)?.toInt() ?? 1,
        entryCost: (j['entry_cost'] as num?)?.toDouble() ?? 0,
        taxRate: (j['tax_rate'] as num?)?.toDouble() ?? 0,
        opexMult: (j['opex_mult'] as num?)?.toDouble() ?? 1,
        colorHex: j['color_hex'] as String? ?? '#c8a84b',
        permit: PermitInfo.fromJson(
            (j['permit'] as Map<String, dynamic>?) ?? const {}),
      );
}

class MapLocation {
  final int id;
  final int regionId;
  final String name;
  final double? latitude;
  final double? longitude;
  final double oilRichness;
  final String wellType;
  final String tier;
  final double effectiveEntryCost;
  final bool occupiedByMe;
  final bool occupiedByAnyone;
  final int? myWellId;
  final String? myWellStatus;

  const MapLocation({
    required this.id,
    required this.regionId,
    required this.name,
    this.latitude,
    this.longitude,
    required this.oilRichness,
    required this.wellType,
    required this.tier,
    required this.effectiveEntryCost,
    required this.occupiedByMe,
    required this.occupiedByAnyone,
    this.myWellId,
    this.myWellStatus,
  });

  bool get isAvailable => !occupiedByAnyone;

  factory MapLocation.fromJson(Map<String, dynamic> j) => MapLocation(
        id: (j['id'] as num?)?.toInt() ?? 0,
        regionId: (j['region_id'] as num?)?.toInt() ?? 0,
        name: j['name'] as String? ?? '',
        latitude: (j['latitude'] as num?)?.toDouble(),
        longitude: (j['longitude'] as num?)?.toDouble(),
        oilRichness: (j['oil_richness'] as num?)?.toDouble() ?? 1,
        wellType: j['well_type'] as String? ?? 'onshore',
        tier: j['tier'] as String? ?? 'medium',
        effectiveEntryCost: (j['effective_entry_cost'] as num?)?.toDouble() ?? 0,
        occupiedByMe: j['occupied_by_me'] as bool? ?? false,
        occupiedByAnyone: j['occupied_by_anyone'] as bool? ?? false,
        myWellId: (j['my_well_id'] as num?)?.toInt(),
        myWellStatus: j['my_well_status'] as String?,
      );
}

class MapData {
  final List<MapRegion> regions;
  final List<MapLocation> locations;
  final int wellCount;

  const MapData({
    required this.regions,
    required this.locations,
    required this.wellCount,
  });

  List<MapLocation> locationsForRegion(int regionId) =>
      locations.where((l) => l.regionId == regionId).toList();

  factory MapData.fromJson(Map<String, dynamic> j) {
    final regionList = (j['regions'] as List<dynamic>? ?? [])
        .map((e) => MapRegion.fromJson(e as Map<String, dynamic>))
        .toList();
    final locationList = (j['locations'] as List<dynamic>? ?? [])
        .map((e) => MapLocation.fromJson(e as Map<String, dynamic>))
        .toList();
    return MapData(
      regions: regionList,
      locations: locationList,
      wellCount: (j['well_count'] as num?)?.toInt() ?? 0,
    );
  }
}
