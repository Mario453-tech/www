class Storage {
  final int capacity;
  final int used;

  const Storage({required this.capacity, required this.used});

  double get fillPercent => capacity > 0 ? (used / capacity * 100) : 0;

  factory Storage.fromJson(Map<String, dynamic> j) => Storage(
        capacity: (j['capacity'] as num?)?.toInt() ?? 0,
        used: (j['used'] as num?)?.toInt() ?? 0,
      );
}

class Player {
  final int id;
  final String username;
  final double cash;
  final String financialState;
  final int creditScore;
  final bool offlineMode;
  final Storage storage;
  final int activeWells;
  final int activeLoans;

  const Player({
    required this.id,
    required this.username,
    required this.cash,
    required this.financialState,
    required this.creditScore,
    required this.offlineMode,
    required this.storage,
    required this.activeWells,
    required this.activeLoans,
  });

  factory Player.fromJson(Map<String, dynamic> j) => Player(
        id: (j['id'] as num?)?.toInt() ?? 0,
        username: j['username'] as String? ?? '',
        cash: (j['cash'] as num?)?.toDouble() ?? 0.0,
        financialState: j['financial_state'] as String? ?? 'stable',
        creditScore: (j['credit_score'] as num?)?.toInt() ?? 0,
        offlineMode: j['offline_mode'] == true || j['offline_mode'] == 1,
        storage: Storage.fromJson(
            j['storage'] as Map<String, dynamic>? ?? {}),
        activeWells: (j['active_wells'] as num?)?.toInt() ?? 0,
        activeLoans: (j['active_loans'] as num?)?.toInt() ?? 0,
      );
}
