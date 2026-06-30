const Map<String, String> technicalPl = {
  'technical.title': 'DZIAŁ TECHNICZNY',
  'technical.loading': 'Ładowanie działu technicznego...',
  'technical.error': 'Nie udało się załadować danych. Spróbuj ponownie.',

  // Tabs
  'technical.tab.team': 'ZESPÓŁ',
  'technical.tab.well_staff': 'PERSONEL ODWIERTÓW',
  'technical.tab.candidates': 'KANDYDACI',

  // Director
  'technical.director.label': 'Kierownik techniczny',
  'technical.director.tenure': 'Staż: {days} dni',
  'technical.director.experience': 'Doświadczenie: {years} lat',
  'technical.director.salary': 'Pensja: {salary} PLN/mies.',

  // Manager bonus chips
  'technical.bonus.time': '-{pct}% czasu zadań',
  'technical.bonus.cost': '-{pct}% kosztów zadań',
  'technical.bonus.org': 'Org. {skill}/10',

  // Engineers header
  'technical.engineers.header': 'Inżynierowie ({count})',
  'technical.engineers.empty': 'Brak inżynierów w zespole.',

  // Engineer status
  'technical.engineer.available': 'Dostępny',
  'technical.engineer.busy': 'Zajęty',
  'technical.engineer.on_leave': 'Urlop',

  // Engineer card fields
  'technical.engineer.skill': 'Umiejętność',
  'technical.engineer.experience': '{years} lat doświadczenia',
  'technical.engineer.salary': '{salary} PLN/mies.',

  // Engineer actions
  'technical.engineer.assign_task': '► Zleć zadanie',
  'technical.engineer.fire': 'ZWOLNIJ',

  // Fire dialog
  'technical.fire.confirm_title': 'Zwolnić pracownika?',
  'technical.fire.confirm_body':
      'Czy na pewno chcesz zwolnić {name}? Tej operacji nie można cofnąć.',
  'technical.fire.ok': 'Zwolnij',
  'technical.fire.success': '{name} został zwolniony.',
  'technical.fire.error': 'Nie udało się zwolnić pracownika.',
  'technical.fire.busy_error': 'Nie można zwolnić pracownika w trakcie zadania.',

  // Assign task sheet
  'technical.assign.title': 'Zleć zadanie — {name}',
  'technical.assign.select_task': 'Wybierz zadanie',
  'technical.assign.select_well': 'Wybierz odwiert',
  'technical.assign.no_tasks': 'Brak dostępnych zadań dla tej specjalizacji.',
  'technical.assign.success': 'Zadanie zlecone. Czas: {hours_min}–{hours_max} h.',
  'technical.assign.error': 'Nie udało się zlecić zadania.',
  'technical.assign.hours': '{min}–{max} h',
  'technical.assign.cost': '{min}–{max} PLN',
  'technical.assign.loading': 'Ładowanie zadań...',
  'technical.assign.confirm': 'Zleć',

  // Well staff tab
  'technical.well_staff.header': 'Personel odwiertów',
  'technical.well_staff.empty': 'Brak odwiertów z przypisanym personelem.',
  'technical.well_staff.operator': 'Operator',
  'technical.well_staff.technician': 'Technik',
  'technical.well_staff.missing': 'Brak',
  'technical.well_staff.assign': 'Przypisz',
  'technical.well_staff.unassign': 'Odpisz',
  'technical.well_staff.no_available': 'Brak dostępnych pracowników.',
  'technical.well_staff.reassign': 'Przepisz',

  // Candidates tab
  'technical.candidates.header': 'Kandydaci ({count})',
  'technical.candidates.empty': 'Brak kandydatów do rekrutacji.',
  'technical.candidate.expires': 'Wygasa za {hours} h',
  'technical.candidate.reviewed': 'Oceniony',
  'technical.candidate.not_reviewed': 'Nieoceniony',
  'technical.candidate.experience': '{years} lat doświad.',
  'technical.candidate.salary': '{salary} PLN/mies.',
};
