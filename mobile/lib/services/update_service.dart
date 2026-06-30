import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import '../config/app_config.dart';
import '../theme/app_colors.dart';

class UpdateService {
  static const _versionUrl = '${AppConfig.baseUrl}/app/version.php';
  static const _timeout = Duration(seconds: 8);

  static Future<void> check(BuildContext context) async {
    try {
      final response = await http
          .get(Uri.parse(_versionUrl))
          .timeout(_timeout);
      if (response.statusCode != 200) return;
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final serverBuild = data['build'] as int? ?? 0;
      if (serverBuild <= AppConfig.buildNumber) return;
      final force = data['force'] as bool? ?? false;
      final downloadUrl = data['download_url'] as String? ?? '';
      final changelog = data['changelog_pl'] as String? ?? '';
      if (context.mounted) {
        await _showDialog(context, serverBuild, changelog, downloadUrl, force);
      }
    } catch (_) {
      // Silent — update check is best-effort.
    }
  }

  static Future<void> _showDialog(
    BuildContext context,
    int serverBuild,
    String changelog,
    String downloadUrl,
    bool force,
  ) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: !force,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.bg3,
        title: Row(
          children: [
            const Icon(Icons.system_update, color: AppColors.gold, size: 22),
            const SizedBox(width: 8),
            Text(
              'Dostępna aktualizacja',
              style: const TextStyle(color: AppColors.text, fontSize: 16),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Build #$serverBuild jest dostępny.',
              style: const TextStyle(color: AppColors.text2, fontSize: 14),
            ),
            if (changelog.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                changelog,
                style: const TextStyle(color: AppColors.text2, fontSize: 13),
              ),
            ],
            const SizedBox(height: 12),
            const Text(
              'Pobierz plik APK i zainstaluj na telefonie.',
              style: TextStyle(color: AppColors.text3, fontSize: 12),
            ),
          ],
        ),
        actions: [
          if (!force)
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text(
                'Nie teraz',
                style: TextStyle(color: AppColors.text2),
              ),
            ),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.gold,
              foregroundColor: AppColors.bg3,
            ),
            icon: const Icon(Icons.download, size: 18),
            label: const Text('Pobierz'),
            onPressed: () async {
              final uri = Uri.tryParse(downloadUrl);
              if (uri != null) {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              }
              if (ctx.mounted && !force) Navigator.of(ctx).pop();
            },
          ),
        ],
      ),
    );
  }
}
