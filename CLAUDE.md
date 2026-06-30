# CLAUDE.md — repozytorium www

## Flutter — ZASADA OBOWIĄZKOWA

### Zawsze buduj aplikację po zmianach

Po każdej zmianie kodu Flutter (pliki w `mobile/lib/`) OBOWIĄZKOWO:

1. `flutter analyze --no-pub` — zero błędów przed commitem
2. Zbuduj APK:
   ```bash
   cd /home/user/www/mobile
   /opt/flutter-sdk/bin/flutter build apk --release --no-pub
   ```
   Plik wynikowy: `mobile/build/app/outputs/flutter-apk/app-release.apk`
3. Poinformuj użytkownika o ścieżce do APK do instalacji na telefonie.

Bez build i analyze zmiana nie jest skończona — kod może kompilować się na serwerze,
ale na urządzeniu może nie działać (błędy runtime, brakujące zasoby itp.).

**UWAGA środowisko zdalne:** W środowisku claude.ai/code (remote cloud) nie ma
Android SDK — `flutter build apk` nie zadziała (brak ANDROID_HOME). W takim
przypadku poinformuj użytkownika, że APK musi być zbudowany lokalnie lub przez CI/CD
z danego brancha. Zawsze wykonaj przynajmniej `flutter analyze --no-pub`.

### Flutter SDK

Zainstalowany w `/opt/flutter-sdk`. Używaj pełnej ścieżki:
- `analyze`: `/opt/flutter-sdk/bin/flutter analyze --no-pub`
- `build apk`: `/opt/flutter-sdk/bin/flutter build apk --release --no-pub`
- `pub get`: `/opt/flutter-sdk/bin/flutter pub get`

Git safe.directory może wymagać:
```bash
git config --global --add safe.directory /opt/flutter-sdk
```

### Branch roboczy

Wszystkie zmiany mobile idą na branch `claude/restart-basch-process-omr7r7`.
