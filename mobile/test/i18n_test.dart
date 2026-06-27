import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/i18n/app_localizations.dart';

void main() {
  group('AppLocalizations', () {
    const l = AppLocalizations('pl', {
      'dashboard.cash': 'Gotówka',
      'dashboard.greeting': 'Witaj, {name}',
      'dashboard.age': 'Firma istnieje :days dni',
    });

    test('zwraca tlumaczenie dla istniejacego klucza', () {
      expect(l.t('dashboard.cash'), 'Gotówka');
    });

    test('zwraca sam klucz gdy brak tlumaczenia', () {
      expect(l.t('missing.key'), 'missing.key');
    });

    test('podstawia parametr w nawiasach klamrowych {name}', () {
      expect(l.t('dashboard.greeting', {'name': 'Jan'}), 'Witaj, Jan');
    });

    test('podstawia parametr z dwukropkiem :days', () {
      expect(l.t('dashboard.age', {'days': 120}), 'Firma istnieje 120 dni');
    });

    test('has() wykrywa obecnosc klucza', () {
      expect(l.has('dashboard.cash'), isTrue);
      expect(l.has('nope'), isFalse);
    });
  });
}
