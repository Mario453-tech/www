import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../models/player.dart';
import '../providers/auth_provider.dart';
import 'wells_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _tabIndex = 0;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Icon(Icons.oil_barrel,
                color: Theme.of(context).colorScheme.primary, size: 22),
            const SizedBox(width: 8),
            const Text('OilEmpire'),
          ],
        ),
        actions: [
          if (auth.player != null)
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: IconButton(
                icon: const Icon(Icons.refresh),
                onPressed: auth.refreshPlayer,
                tooltip: 'Odśwież',
              ),
            ),
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'logout') auth.logout();
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: const [
                    Icon(Icons.logout, size: 18),
                    SizedBox(width: 8),
                    Text('Wyloguj'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: IndexedStack(
        index: _tabIndex,
        children: [
          _OverviewTab(player: auth.player),
          const WellsScreen(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tabIndex,
        onDestinationSelected: (i) => setState(() => _tabIndex = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: Icon(Icons.oil_barrel_outlined),
            selectedIcon: Icon(Icons.oil_barrel),
            label: 'Studnie',
          ),
        ],
      ),
    );
  }
}

class _OverviewTab extends StatelessWidget {
  final Player? player;
  const _OverviewTab({this.player});

  @override
  Widget build(BuildContext context) {
    if (player == null) {
      return const Center(child: CircularProgressIndicator());
    }
    final p = player!;
    final cs = Theme.of(context).colorScheme;
    final moneyFmt = NumberFormat('#,##0.00 PLN', 'pl_PL');

    return RefreshIndicator(
      onRefresh: () => context.read<AuthProvider>().refreshPlayer(),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Witaj, ${p.username}',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: 4),
            _FinancialStateBadge(state: p.financialState),
            const SizedBox(height: 20),
            _BigCard(
              icon: Icons.account_balance_wallet,
              label: 'Gotówka',
              value: moneyFmt.format(p.cash),
              color: cs.primary,
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _SmallCard(
                    icon: Icons.oil_barrel,
                    label: 'Aktywne studnie',
                    value: '${p.activeWells}',
                    color: Colors.green,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _SmallCard(
                    icon: Icons.credit_score,
                    label: 'Credit score',
                    value: '${p.creditScore}',
                    color: _creditColor(p.creditScore),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _StorageCard(storage: p.storage),
            if (p.activeLoans > 0) ...[
              const SizedBox(height: 12),
              Card(
                color: cs.errorContainer,
                child: ListTile(
                  leading: Icon(Icons.warning, color: cs.onErrorContainer),
                  title: Text(
                    '${p.activeLoans} aktywn${p.activeLoans == 1 ? 'y kredyt' : 'e kredyty'}',
                    style: TextStyle(color: cs.onErrorContainer),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Color _creditColor(int score) {
    if (score >= 700) return Colors.green;
    if (score >= 500) return Colors.orange;
    return Colors.red;
  }
}

class _FinancialStateBadge extends StatelessWidget {
  final String state;
  const _FinancialStateBadge({required this.state});

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (state) {
      'stable' => ('Stabilny', Colors.green),
      'warning' => ('Uwaga', Colors.orange),
      'crisis' => ('KRYZYS', Colors.red),
      'bankrupt' => ('BANKRUTUJE', Colors.red),
      _ => (state, Colors.grey),
    };

    return Chip(
      avatar: Icon(Icons.circle, size: 10, color: color),
      label: Text(label, style: TextStyle(color: color)),
      side: BorderSide(color: color.withValues(alpha: 0.4)),
      backgroundColor: color.withValues(alpha: 0.1),
      padding: EdgeInsets.zero,
    );
  }
}

class _BigCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _BigCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.15),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: color, size: 28),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant,
                        ),
                  ),
                  Text(
                    value,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: color,
                        ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SmallCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _SmallCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 8),
            Text(
              value,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: color,
                  ),
            ),
            Text(
              label,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StorageCard extends StatelessWidget {
  final Storage storage;
  const _StorageCard({required this.storage});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final pct = storage.fillPercent;
    final fmt = NumberFormat('#,##0', 'pl_PL');

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.storage, size: 18),
                const SizedBox(width: 8),
                Text('Magazyn ropy',
                    style: Theme.of(context).textTheme.titleSmall),
                const Spacer(),
                Text(
                  '${fmt.format(storage.used)} / ${fmt.format(storage.capacity)} bbl',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: cs.onSurfaceVariant),
                ),
              ],
            ),
            const SizedBox(height: 8),
            LinearProgressIndicator(
              value: pct / 100,
              minHeight: 8,
              borderRadius: BorderRadius.circular(4),
              color: pct > 85
                  ? Colors.red
                  : pct > 60
                      ? Colors.orange
                      : Colors.blue,
            ),
            const SizedBox(height: 4),
            Text(
              '${pct.toStringAsFixed(1)}% zapełniony',
              style: Theme.of(context)
                  .textTheme
                  .labelSmall
                  ?.copyWith(color: cs.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}
