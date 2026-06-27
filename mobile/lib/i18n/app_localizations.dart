/// Slownik tlumaczen dla jednego jezyka + wyszukiwanie z podstawieniami.
/// Odwzorowuje helper `t()` z gry: lookup `modul.klucz` oraz podstawienia
/// `:param` i `{param}`.
///
/// Single-locale translation lookup with placeholder substitution. Mirrors the
/// game's `t()` helper: `module.key` lookup and `:param` / `{param}` replacement.
class AppLocalizations {
  final String locale;
  final Map<String, String> _strings;

  const AppLocalizations(this.locale, this._strings);

  /// Zwraca przetlumaczony tekst dla [key]. Gdy klucza brak — zwraca sam klucz
  /// (ulatwia wykrycie brakujacych tlumaczen). [params] podstawia `:k` oraz `{k}`.
  String t(String key, [Map<String, Object?> params = const {}]) {
    var str = _strings[key] ?? key;
    if (params.isNotEmpty) {
      params.forEach((k, v) {
        final value = '$v';
        str = str.replaceAll(':$k', value).replaceAll('{$k}', value);
      });
    }
    return str;
  }

  /// Czy istnieje tlumaczenie dla [key].
  bool has(String key) => _strings.containsKey(key);

  int get count => _strings.length;
}
