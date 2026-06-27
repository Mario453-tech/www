import '../modules/app_module.dart';
import '../modules/dashboard/dashboard_module.dart';
import '../modules/game/game_module.dart';

/// JEDYNE miejsce, w ktorym rejestruje sie moduly aplikacji.
/// Aby dodac nowy modul (np. Rynek, Studnie): stworz klase rozszerzajaca
/// [AppModule] i dopisz ja do tej listy. Reszta (nawigacja, tlumaczenia,
/// ekran) podepnie sie automatycznie.
///
/// The ONE place where app modules are registered. To add a module: create an
/// [AppModule] subclass and add it here — nav, translations and screen wire up
/// automatically.
List<AppModule> buildAppModules() => [
      DashboardModule(),
      GameModule(),
    ];
