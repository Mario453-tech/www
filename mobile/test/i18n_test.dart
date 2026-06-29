import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/i18n/app_localizations.dart';
import 'package:oil_empire/i18n/strings/core_en.dart';
import 'package:oil_empire/i18n/strings/core_pl.dart';

void main() {
  group('AppLocalizations', () {
    const l = AppLocalizations('pl', {
      'dashboard.cash': 'Gotówka',
      'dashboard.greeting': 'Witaj, {name}',
      'dashboard.age': 'Firma istnieje :days dni',
      'api.error.non_json_response':
          'Serwer zwrócił nie-JSON (HTTP {code}):\n{snippet}',
    });

    test('returns a translation for an existing key', () {
      expect(l.t('dashboard.cash'), 'Gotówka');
    });

    test('returns the key when translation is missing', () {
      expect(l.t('missing.key'), 'missing.key');
    });

    test('replaces brace placeholders', () {
      expect(l.t('dashboard.greeting', {'name': 'Jan'}), 'Witaj, Jan');
    });

    test('replaces colon placeholders', () {
      expect(l.t('dashboard.age', {'days': 120}), 'Firma istnieje 120 dni');
    });

    test('has() detects existing keys', () {
      expect(l.has('dashboard.cash'), isTrue);
      expect(l.has('nope'), isFalse);
    });

    test('resolve() resolves plain keys', () {
      expect(l.resolve('dashboard.cash'), 'Gotówka');
    });

    test('resolve() keeps unknown text unchanged', () {
      expect(l.resolve('raw backend text'), 'raw backend text');
    });

    test('resolve() expands compact API payloads and preserves pipe chars', () {
      expect(
        l.resolve('api.error.non_json_response|500|fatal | html | body'),
        'Serwer zwrócił nie-JSON (HTTP 500):\nfatal | html | body',
      );
    });
  });

  group('core translations', () {
    test('PL and EN expose the same core keys', () {
      expect(coreEn.keys.toSet(), corePl.keys.toSet());
    });

    test('EN keeps PLN labels because backend values are not converted', () {
      expect(coreEn['common.currency'], 'PLN');
      expect(coreEn['common.unit.pln_per_hour'], 'PLN/h');
    });
  });
}
