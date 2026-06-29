/// Single-locale translation lookup with placeholder substitution.
class AppLocalizations {
  final String locale;
  final Map<String, String> _strings;

  const AppLocalizations(this.locale, this._strings);

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

  bool has(String key) => _strings.containsKey(key);

  /// Resolves either a plain translation key or a compact error payload:
  /// `key|param1|param2`.
  String resolve(String value) {
    if (has(value)) {
      return t(value);
    }

    if (!value.contains('|')) {
      return value;
    }

    final parts = value.split('|');
    final key = parts.first;
    if (!has(key)) {
      return value;
    }

    switch (key) {
      case 'api.error.non_json_response':
        return t(key, {
          'code': parts.length > 1 ? parts[1] : '',
          'snippet': parts.length > 2 ? parts.sublist(2).join('|') : '',
        });
      case 'api.error.unexpected_format':
      case 'api.error.server_http':
        return t(key, {
          'code': parts.length > 1 ? parts[1] : '',
        });
      default:
        return t(key);
    }
  }

  int get count => _strings.length;
}
