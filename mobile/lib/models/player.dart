class Storage {
  final int capacity;
  final int used;

  const Storage({required this.capacity, required this.used});

  double get fillPercent => capacity > 0 ? (used / capacity * 100) : 0;

  factory Storage.fromJson(Map<String, dynamic> j) => Storage(
        capacity: (j['max_bbl'] as num?)?.toInt() ?? (j['capacity'] as num?)?.toInt() ?? 0,
        used: (j['current_bbl'] as num?)?.toInt() ?? (j['used'] as num?)?.toInt() ?? 0,
      );
}

class Player {
  final int id;
  final String username;
  final String companyName;
  final double cash;
  final double bankBalance;
  final double oilPrice;
  final int companyAgeDays;
  final String financialState;
  final int creditScore;
  final bool offlineMode;
  final Storage storage;
  final int activeWells;
  final int activeLoans;

  const Player({
    required this.id,
    required this.username,
    required this.companyName,
    required this.cash,
    required this.bankBalance,
    required this.oilPrice,
    required this.companyAgeDays,
    required this.financialState,
    required this.creditScore,
    required this.offlineMode,
    required this.storage,
    required this.activeWells,
    required this.activeLoans,
  });

  factory Player.fromJson(Map<String, dynamic> j) {
    final stats = j['stats'] as Map<String, dynamic>? ?? {};
    return Player(
      id: (j['id'] as num?)?.toInt() ?? 0,
      username: j['username'] as String? ?? '',
      companyName: (j['company_name'] as String?)?.trim().isNotEmpty == true
          ? j['company_name'] as String
          : (j['username'] as String? ?? ''),
      cash: (j['cash'] as num?)?.toDouble() ?? 0.0,
      bankBalance: (j['bank_balance'] as num?)?.toDouble() ?? 0.0,
      oilPrice: (j['oil_price'] as num?)?.toDouble() ?? 0.0,
      companyAgeDays: (j['company_age_days'] as num?)?.toInt() ?? 0,
      financialState: j['financial_state'] as String? ?? 'normal',
      creditScore: (j['credit_score'] as num?)?.toInt() ?? 0,
      offlineMode: j['offline_mode'] == true || j['offline_mode'] == 1,
      storage: Storage.fromJson(j['storage'] as Map<String, dynamic>? ?? {}),
      activeWells: (stats['active_wells'] as num?)?.toInt() ?? (j['active_wells'] as num?)?.toInt() ?? 0,
      activeLoans: (stats['active_loans'] as num?)?.toInt() ?? (j['active_loans'] as num?)?.toInt() ?? 0,
    );
  }
}
