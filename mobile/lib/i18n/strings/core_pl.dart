/// Core shared translations (PL). Per-page strings live inside modules when
/// the module owns a dedicated translation file.
const Map<String, String> corePl = {
  // App
  'app.name': 'OilEmpire',
  'app.tagline': 'Panel gracza',

  // Navigation
  'nav.dashboard': 'Dashboard',
  'nav.game': 'Gra',
  'nav.wells': 'Studnie',
  'nav.market': 'Rynek',

  // Common
  'common.retry': 'Spróbuj ponownie',
  'common.refresh': 'Odśwież',
  'common.loading': 'Ładowanie',
  'common.cancel': 'Anuluj',
  'common.close': 'Zamknij',
  'common.error_connection': 'Błąd połączenia z serwerem.',
  'common.error_generic': 'Wystąpił błąd.',
  'common.currency': 'PLN',
  'common.separator_dot': '·',
  'common.language': 'Język',
  'common.debug_url_label': 'URL',
  'common.unit.bbl': 'bbl',
  'common.unit.bbl_per_hour': 'bbl/h',
  'common.unit.pln_per_hour': 'PLN/h',

  // Auth
  'auth.login_title': 'OilEmpire',
  'auth.login_subtitle': 'Panel gracza',
  'auth.login_field': 'Login lub e-mail',
  'auth.password_field': 'Hasło',
  'auth.login_button': 'Zaloguj się',
  'auth.logout': 'Wyloguj',
  'auth.validation_login': 'Podaj login',
  'auth.validation_password': 'Podaj hasło',
  'auth.error_credentials': 'Nieprawidłowy login lub hasło.',
  'auth.error_session': 'Błąd weryfikacji sesji.',

  // Wells
  'wells.filters.all': 'Wszystkie',
  'wells.filters.active': 'Aktywne',
  'wells.filters.paused': 'Wstrzymane',
  'wells.filters.damaged': 'Uszkodzone',
  'wells.empty': 'Brak studni',
  'wells.card.production': 'Produkcja',
  'wells.card.costs': 'Koszty',
  'wells.card.technical_condition': 'Stan tech.',
  'wells.card.risk': 'Ryzyko',
  'wells.card.reservoir': 'Złoże: {remaining} / {max} {unit}',
  'wells.risk.low': 'NISKIE',
  'wells.risk.medium': 'ŚREDNIE',
  'wells.risk.high': 'WYSOKIE',
  'wells.status.active': 'Aktywna',
  'wells.status.paused': 'Wstrzymana',
  'wells.status.paused_cash': 'Wstrzymana: gotówka',
  'wells.status.paused_staff': 'Wstrzymana: personel',
  'wells.status.paused_storage': 'Wstrzymana: magazyn',
  'wells.status.damaged': 'Uszkodzona',
  'wells.status.blowout': 'AWARIA',
  'wells.status.offline': 'Offline',
  'wells.status.contaminated': 'Skażona',
  'wells.status.no_operator': 'Brak operatora',
  'wells.status.no_technician': 'Brak technika',
  'wells.status.seized': 'Zajęta',
  'wells.status.layer_switch': 'Zmiana warstwy',
  'wells.status.sold': 'Sprzedana',
  'wells.status.unknown': 'Nieznany',
  'wells.risk.unknown': 'NIEZNANE',

  // WebView
  'webview.error_connection': 'Brak połączenia z serwerem',

  // API
  'api.error.invalid_login_response':
      'Nieprawidłowa odpowiedź logowania z serwera.',
  'api.error.empty_response': '(pusta odpowiedź)',
  'api.error.non_json_response':
      'Serwer zwrócił nie-JSON (HTTP {code}):\n{snippet}',
  'api.error.unexpected_format':
      'Nieoczekiwany format odpowiedzi (HTTP {code})',
  'api.error.server_http': 'Błąd serwera (HTTP {code})',
  'webview.error_blocked_navigation':
      'Zablokowano niebezpieczne przekierowanie.',
  'game.bridge.loading': 'Łączenie z grą...',
  'game.bridge.error': 'Nie udało się otworzyć gry.',
  'api.error.invalid_bridge_response':
      'Nieprawidłowa odpowiedź logowania do gry.',
};
