import 'package:flutter/widgets.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'app_localizations.dart';

/// Holds the active locale, persists it, and exposes [AppLocalizations].
class LocaleProvider extends ChangeNotifier {
  static const _prefsKey = 'app_locale';

  static const supported = ['pl', 'en'];

  final Map<String, Map<String, String>> _byLocale;
  String _locale;

  LocaleProvider(this._byLocale, {String initial = 'pl'})
      : _locale = supported.contains(initial) ? initial : 'pl';

  String get locale => _locale;

  AppLocalizations get l10n =>
      AppLocalizations(_locale, _byLocale[_locale] ?? const {});

  Future<void> load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final saved = prefs.getString(_prefsKey);
      if (saved != null && supported.contains(saved)) {
        _locale = saved;
        notifyListeners();
      }
    } catch (_) {
      // Keep the default locale when device storage is unavailable.
    }
  }

  Future<void> setLocale(String locale) async {
    if (!supported.contains(locale) || locale == _locale) return;
    _locale = locale;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, locale);
    } catch (_) {
      // Best effort persistence.
    }
  }

  Future<void> toggle() => setLocale(_locale == 'pl' ? 'en' : 'pl');
}

extension LocalizationX on BuildContext {
  AppLocalizations get l10n => watch<LocaleProvider>().l10n;

  String t(String key, [Map<String, Object?> params = const {}]) =>
      Provider.of<LocaleProvider>(this, listen: true).l10n.t(key, params);

  String resolveText(String value) =>
      Provider.of<LocaleProvider>(this, listen: true).l10n.resolve(value);
}
