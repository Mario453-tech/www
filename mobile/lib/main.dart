import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';
import 'app/app_shell.dart';
import 'app/modules.dart';
import 'i18n/locale_provider.dart';
import 'i18n/strings/core_strings.dart';
import 'modules/module_registry.dart';
import 'providers/auth_provider.dart';
import 'screens/login_screen.dart';
import 'theme/app_theme.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('pl_PL', null);

  // Rejestr modulow + scalone tlumaczenia (baza wspolna + per modul).
  final registry = ModuleRegistry(buildAppModules());
  final translations = registry.buildTranslations(
    LocaleProvider.supported,
    fallback: 'pl',
    base: coreStrings,
  );

  final localeProvider = LocaleProvider(translations)..load();

  runApp(
    MultiProvider(
      providers: [
        Provider<ModuleRegistry>.value(value: registry),
        ChangeNotifierProvider(create: (_) => AuthProvider()..init()),
        ChangeNotifierProvider.value(value: localeProvider),
      ],
      child: const OilEmpireApp(),
    ),
  );
}

class OilEmpireApp extends StatelessWidget {
  const OilEmpireApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'OilEmpire',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark(),
      home: Consumer<AuthProvider>(
        builder: (_, auth, __) {
          if (auth.isLoading && !auth.isLoggedIn) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          return auth.isLoggedIn ? const AppShell() : const LoginScreen();
        },
      ),
    );
  }
}
