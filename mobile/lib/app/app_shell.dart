import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../i18n/locale_provider.dart';
import '../modules/module_registry.dart';
import '../providers/auth_provider.dart';
import '../services/screen_security_service.dart';
import '../theme/app_colors.dart';

/// Main authenticated shell with app bar, module content, and bottom navigation.
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    ScreenSecurityService.setProtected(true);
  }

  @override
  void dispose() {
    ScreenSecurityService.setProtected(false);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final registry = context.read<ModuleRegistry>();
    final modules = registry.navModules;
    final auth = context.watch<AuthProvider>();
    final locale = context.watch<LocaleProvider>();

    final safeIndex =
        modules.isEmpty ? 0 : _index.clamp(0, modules.length - 1).toInt();

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(
          children: [
            const Icon(Icons.oil_barrel, color: AppColors.gold, size: 22),
            const SizedBox(width: 8),
            Text(
              context.t('app.name'),
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                letterSpacing: 1.5,
                color: AppColors.text,
              ),
            ),
          ],
        ),
        actions: [
          _LanguagePill(
            locale: locale.locale,
            onTap: () => context.read<LocaleProvider>().toggle(),
          ),
          if (auth.player != null)
            IconButton(
              icon: const Icon(Icons.refresh),
              tooltip: context.t('common.refresh'),
              onPressed: auth.refreshPlayer,
            ),
          PopupMenuButton<String>(
            onSelected: (value) {
              if (value == 'logout') {
                context.read<AuthProvider>().logout();
              }
            },
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    const Icon(Icons.logout, size: 18),
                    const SizedBox(width: 8),
                    Text(context.t('auth.logout')),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: IndexedStack(
        index: safeIndex,
        children: [
          for (final module in modules) module.buildScreen(context),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeIndex,
        onDestinationSelected: (index) => setState(() => _index = index),
        destinations: [
          for (final module in modules)
            NavigationDestination(
              icon: Icon(module.navIcon),
              selectedIcon: Icon(module.navIconSelected),
              label: context.t(module.titleKey),
            ),
        ],
      ),
    );
  }
}

/// Language pill matching the web header control.
class _LanguagePill extends StatelessWidget {
  final String locale;
  final VoidCallback onTap;

  const _LanguagePill({required this.locale, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: const Color(0x09FFFFFF),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: AppColors.goldBorder),
          ),
          child: Text(
            locale.toUpperCase(),
            style: const TextStyle(
              color: AppColors.goldBright,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8,
            ),
          ),
        ),
      ),
    );
  }
}
