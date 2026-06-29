import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../i18n/locale_provider.dart';
import '../../models/market.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import 'dashboard_styles.dart';
import 'widgets/dashboard_error_state.dart';
import 'widgets/dashboard_kpi_grid.dart';
import 'widgets/loans_banner.dart';
import 'widgets/market_event_card.dart';

/// Native dashboard screen mirroring the web game summary.
///
/// All values come from the server tick. The app only reads API data, refreshes
/// every 60 seconds, and locally redraws the market-event countdown.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen>
    with WidgetsBindingObserver {
  MarketState? _market;
  Timer? _refreshTimer;
  Timer? _tickTimer;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadMarket());
    _refreshTimer =
        Timer.periodic(const Duration(seconds: 60), (_) => _refreshAll());
    _tickTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted && (_market?.trend?.isActive ?? false)) {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _refreshTimer?.cancel();
    _tickTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshAll();
    }
  }

  Future<void> _refreshAll() async {
    if (!mounted) return;
    final auth = context.read<AuthProvider>();
    final locale = context.read<LocaleProvider>().locale;
    await auth.refreshPlayer();
    await _loadMarketWith(auth.token, locale: locale);
  }

  Future<void> _loadMarket() async {
    if (!mounted) return;
    await _loadMarketWith(
      context.read<AuthProvider>().token,
      locale: context.read<LocaleProvider>().locale,
    );
  }

  Future<void> _loadMarketWith(String? token, {String? locale}) async {
    if (token == null) return;
    try {
      final market = await ApiService.getMarket(token, locale: locale);
      if (mounted) {
        setState(() => _market = market);
      }
    } catch (_) {
      // Market data is optional for the dashboard, so keep the screen usable.
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final player = auth.player;

    if (player == null) {
      if (auth.isLoading) {
        return const Center(child: CircularProgressIndicator());
      }
      return DashboardErrorState(
        message: auth.error ?? context.t('common.error_generic'),
        onRetry: auth.refreshPlayer,
      );
    }

    final trend = _market?.trend;
    final showEvent = trend != null && trend.isActive;

    return RefreshIndicator(
      color: DashboardStyles.refreshColor,
      onRefresh: _refreshAll,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: DashboardStyles.screenPadding,
        children: [
          Text(
            context.t('dashboard.greeting', {'name': player.companyName}),
            style: DashboardStyles.greeting,
          ),
          const SizedBox(height: DashboardStyles.gapLg),
          DashboardKpiGrid(player: player),
          if (showEvent) ...[
            const SizedBox(height: DashboardStyles.gapMd),
            MarketEventCard(trend: trend),
          ],
          if (player.activeLoans > 0) ...[
            const SizedBox(height: DashboardStyles.gapMd),
            LoansBanner(count: player.activeLoans),
          ],
        ],
      ),
    );
  }
}
