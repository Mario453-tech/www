import 'package:flutter/material.dart';
import '../modules/app_module.dart';
import '../modules/dashboard/dashboard_module.dart';
import '../modules/game/game_module.dart';
import '../modules/maps/maps_module.dart';
import '../modules/technical/technical_module.dart';
import '../modules/department_module.dart';

/// Single registration point for mobile modules.
///
/// Bottom nav: modules with showInNav = true (Dashboard, Game, Maps, Technical).
/// Drawer only: DepartmentModule entries (showInNav = false).
List<AppModule> buildAppModules() => [
      // ── Bottom navigation ────────────────────────────────────────────────
      DashboardModule(),   // order 10
      GameModule(),        // order 20
      MapsModule(),        // order 30
      TechnicalModule(),   // order 40

      // ── Drawer-only departments ──────────────────────────────────────────
      DepartmentModule(
        id: 'market',
        titleKey: 'nav.market',
        icon: Icons.storefront_outlined,
        order: 50,
        path: '/market',
        title: 'Rynek',
      ),
      DepartmentModule(
        id: 'bank',
        titleKey: 'nav.bank',
        icon: Icons.account_balance_outlined,
        order: 60,
        path: '/bank',
        title: 'Bank',
      ),
      DepartmentModule(
        id: 'hr',
        titleKey: 'nav.hr',
        icon: Icons.people_alt_outlined,
        order: 70,
        path: '/hr',
        title: 'Zarząd / HR',
      ),
      DepartmentModule(
        id: 'legal',
        titleKey: 'nav.legal',
        icon: Icons.gavel_outlined,
        order: 80,
        path: '/legal',
        title: 'Dział Prawny',
      ),
      DepartmentModule(
        id: 'logistics',
        titleKey: 'nav.logistics',
        icon: Icons.local_shipping_outlined,
        order: 90,
        path: '/logistics',
        title: 'Logistyka',
      ),
      DepartmentModule(
        id: 'boardroom',
        titleKey: 'nav.boardroom',
        icon: Icons.business_center_outlined,
        order: 100,
        path: '/boardroom',
        title: 'Sala Zarządu',
      ),
      DepartmentModule(
        id: 'sabotage',
        titleKey: 'nav.sabotage',
        icon: Icons.bug_report_outlined,
        order: 110,
        path: '/sabotage',
        title: 'Sabotaż',
      ),
    ];
