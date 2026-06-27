import 'app_module.dart';

/// Rejestr modulow aplikacji. Sortuje moduly wg [AppModule.order], udostepnia
/// liste do nawigacji oraz scala tlumaczenia wszystkich modulow (+ baza wspolna)
/// w mapy per jezyk, z fallbackiem brakujacych kluczy do jezyka bazowego.
///
/// Module registry: sorts modules by order, exposes the nav list, and merges all
/// per-module translations (plus a shared base) into per-locale maps with
/// fallback of missing keys to the base locale.
class ModuleRegistry {
  final List<AppModule> _modules;

  ModuleRegistry(List<AppModule> modules)
      : _modules = List.of(modules)..sort((a, b) => a.order.compareTo(b.order));

  /// Wszystkie moduly, posortowane.
  List<AppModule> get all => List.unmodifiable(_modules);

  /// Moduly widoczne w nawigacji, posortowane.
  List<AppModule> get navModules =>
      _modules.where((m) => m.showInNav).toList(growable: false);

  /// Zwraca modul po id lub null.
  AppModule? byId(String id) {
    for (final m in _modules) {
      if (m.id == id) return m;
    }
    return null;
  }

  /// Scala tlumaczenia: dla kazdego jezyka startuje od pelnej mapy jezyka
  /// bazowego (fallback), a nastepnie nadpisuje wpisami danego jezyka.
  /// Dzieki temu brakujace klucze (np. w EN) spadaja na jezyk bazowy (PL).
  ///
  /// [base] to wspolne tlumaczenia (common/nav/auth) niezalezne od modulow.
  Map<String, Map<String, String>> buildTranslations(
    List<String> locales, {
    String fallback = 'pl',
    Map<String, Map<String, String>> base = const {},
  }) {
    // 1. Zbuduj pelna mape jezyka bazowego.
    final fallbackMap = <String, String>{};
    fallbackMap.addAll(base[fallback] ?? const {});
    for (final m in _modules) {
      fallbackMap.addAll(m.translations[fallback] ?? const {});
    }

    final result = <String, Map<String, String>>{};
    for (final locale in locales) {
      final map = <String, String>{};
      if (locale != fallback) {
        map.addAll(fallbackMap); // fallback dla brakujacych kluczy
      }
      map.addAll(base[locale] ?? const {});
      for (final m in _modules) {
        map.addAll(m.translations[locale] ?? const {});
      }
      result[locale] = map;
    }
    return result;
  }
}
