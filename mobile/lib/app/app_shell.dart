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
        titleSpacing: 0,
        leading: Builder(
          builder: (ctx) => IconButton(
            icon: const Icon(Icons.menu, color: AppColors.text),
            tooltip: context.t('common.menu'),
            onPressed: () => Scaffold.of(ctx).openDrawer(),
          ),
        ),
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
        ],
      ),
      drawer: _AppDrawer(
        currentIndex: safeIndex,
        modules: modules,
        onSelectModule: (i) {
          setState(() => _index = i);
          Navigator.pop(context);
        },
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

class _AppDrawer extends StatelessWidget {
  final int currentIndex;
  final List<dynamic> modules;
  final ValueChanged<int> onSelectModule;

  const _AppDrawer({
    required this.currentIndex,
    required this.modules,
    required this.onSelectModule,
  });

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final locale = context.watch<LocaleProvider>();
    final username = auth.username ?? '';
    final companyName = auth.player?.companyName ?? '';
    final initials = _initials(username);

    return Drawer(
      child: Column(
        children: [
          DrawerHeader(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: AppColors.gold,
                  child: Text(
                    initials,
                    style: const TextStyle(
                      color: Color(0xFF1A1A2E),
                      fontWeight: FontWeight.w900,
                      fontSize: 18,
                      letterSpacing: 1,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  username,
                  style: const TextStyle(
                    color: AppColors.text,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (companyName.isNotEmpty && companyName != username)
                  Text(
                    companyName,
                    style: const TextStyle(
                      color: AppColors.text2,
                      fontSize: 12,
                      letterSpacing: 0.5,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                for (var i = 0; i < modules.length; i++)
                  ListTile(
                    leading: Icon(
                      i == currentIndex
                          ? modules[i].navIconSelected
                          : modules[i].navIcon,
                      color: i == currentIndex
                          ? AppColors.gold
                          : AppColors.text2,
                    ),
                    title: Text(
                      context.t(modules[i].titleKey),
                      style: TextStyle(
                        color: i == currentIndex
                            ? AppColors.gold
                            : AppColors.text,
                        fontWeight: i == currentIndex
                            ? FontWeight.w700
                            : FontWeight.normal,
                      ),
                    ),
                    selected: i == currentIndex,
                    onTap: () => onSelectModule(i),
                  ),
                const Divider(),
                ListTile(
                  leading: const Icon(
                    Icons.language,
                    color: AppColors.text2,
                  ),
                  title: Text(
                    context.t('common.language'),
                    style: const TextStyle(color: AppColors.text),
                  ),
                  trailing: _LanguagePill(
                    locale: locale.locale,
                    onTap: () => context.read<LocaleProvider>().toggle(),
                  ),
                  onTap: () => context.read<LocaleProvider>().toggle(),
                ),
                ListTile(
                  leading: const Icon(
                    Icons.logout,
                    color: AppColors.text2,
                  ),
                  title: Text(
                    context.t('auth.logout'),
                    style: const TextStyle(color: AppColors.text),
                  ),
                  onTap: () {
                    Navigator.pop(context);
                    context.read<AuthProvider>().logout();
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _initials(String username) {
    final parts = username.trim().split(RegExp(r'[\s._\-]+'));
    if (parts.length >= 2 && parts[0].isNotEmpty && parts[1].isNotEmpty) {
      return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    }
    if (username.isEmpty) return '?';
    final len = username.length >= 2 ? 2 : 1;
    return username.substring(0, len).toUpperCase();
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
