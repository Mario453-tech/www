import 'app_module.dart';

/// Sorts modules, exposes navigation modules, and merges translations.
class ModuleRegistry {
  final List<AppModule> _modules;

  ModuleRegistry(List<AppModule> modules)
      : _modules = List.of(modules)..sort((a, b) => a.order.compareTo(b.order));

  List<AppModule> get all => List.unmodifiable(_modules);

  List<AppModule> get navModules =>
      _modules.where((m) => m.showInNav).toList(growable: false);

  AppModule? byId(String id) {
    for (final module in _modules) {
      if (module.id == id) return module;
    }
    return null;
  }

  Map<String, Map<String, String>> buildTranslations(
    List<String> locales, {
    String fallback = 'pl',
    Map<String, Map<String, String>> base = const {},
  }) {
    final fallbackMap = <String, String>{};
    fallbackMap.addAll(base[fallback] ?? const {});
    for (final module in _modules) {
      fallbackMap.addAll(module.translations[fallback] ?? const {});
    }

    final result = <String, Map<String, String>>{};
    for (final locale in locales) {
      final map = <String, String>{};
      if (locale != fallback) {
        map.addAll(fallbackMap);
      }
      map.addAll(base[locale] ?? const {});
      for (final module in _modules) {
        map.addAll(module.translations[locale] ?? const {});
      }
      result[locale] = map;
    }
    return result;
  }
}
