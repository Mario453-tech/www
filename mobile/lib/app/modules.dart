import '../modules/app_module.dart';
import '../modules/dashboard/dashboard_module.dart';
import '../modules/game/game_module.dart';

/// Single registration point for mobile modules.
List<AppModule> buildAppModules() => [
      DashboardModule(),
      GameModule(),
    ];
