import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'app_localizations.dart';

/// Trzyma aktywny jezyk, utrwala go w SharedPreferences i udostepnia
/// [AppLocalizations] dla biezacego jezyka. Gotowe mapy tlumaczen (per jezyk)
/// wstrzykiwane sa z [ModuleRegistry] przy starcie.
///
/// Holds the active locale, persists it, and exposes [AppLocalizations]. The
/// per-locale translation maps are injected from the ModuleRegistry at startup.
class LocaleProvider extends ChangeNotifier {
  static const _prefsKey = 'app_locale';

  /// Obslugiwane jezyki (jak w grze: PL/EN).
  static const supported = ['pl', 'en'];

  final Map<String, Map<String, String>> _byLocale;
  String _locale;

  LocaleProvider(this._byLocale, {String initial = 'pl'})
      : _locale = supported.contains(initial) ? initial : 'pl';

  String get locale => _locale;

  /// Slownik dla aktywnego jezyka.
  AppLocalizations get l10n =>
      AppLocalizations(_locale, _byLocale[_locale] ?? const {});

  /// Wczytuje zapisany jezyk z pamieci urzadzenia.
  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final saved = prefs.getString(_prefsKey);
      if (saved != null && supported.contains(saved)) {
        _locale = saved;
        notifyListeners();
      }
    } catch (_) {
      // brak pamieci / blad odczytu — zostajemy przy domyslnym
    }
  }

  /// Ustawia jezyk i utrwala go.
  Future<void> setLocale(String locale) async {
    if (!supported.contains(locale) || locale == _locale) return;
    _locale = locale;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, locale);
    } catch (_) {/* best-effort */}
  }

  /// Przelacza miedzy PL i EN (uzywane przez pigulke jezyka).
  Future<void> toggle() => setLocale(_locale == 'pl' ? 'en' : 'pl');
}

/// Skrot `context.t('klucz')` oraz `context.l10n`.
extension LocalizationX on BuildContext {
  AppLocalizations get l10n => watch<LocaleProvider>().l10n;

  String t(String key, [Map<String, Object?> params = const {}]) =>
      Provider.of<LocaleProvider>(this, listen: true).l10n.t(key, params);
}
