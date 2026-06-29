import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/modules/app_module.dart';
import 'package:oil_empire/modules/module_registry.dart';

class _FakeModule extends AppModule {
  @override
  final String id;

  @override
  final int order;

  @override
  final bool showInNav;

  final Map<String, Map<String, String>> _translations;

  _FakeModule(
    this.id,
    this.order, {
    this.showInNav = true,
    Map<String, Map<String, String>>? translations,
  }) : _translations = translations ?? const {};

  @override
  String get titleKey => 'nav.$id';

  @override
  IconData get navIcon => Icons.circle;

  @override
  Map<String, Map<String, String>> get translations => _translations;

  @override
  Widget buildScreen(BuildContext context) => const SizedBox.shrink();
}

void main() {
  group('ModuleRegistry', () {
    test('sorts modules by order', () {
      final r = ModuleRegistry([
        _FakeModule('b', 20),
        _FakeModule('a', 10),
        _FakeModule('c', 30),
      ]);
      expect(r.all.map((m) => m.id), ['a', 'b', 'c']);
    });

    test('navModules skips modules with showInNav=false', () {
      final r = ModuleRegistry([
        _FakeModule('a', 10),
        _FakeModule('hidden', 20, showInNav: false),
      ]);
      expect(r.navModules.map((m) => m.id), ['a']);
    });

    test('byId returns module or null', () {
      final r = ModuleRegistry([_FakeModule('a', 10)]);
      expect(r.byId('a')?.id, 'a');
      expect(r.byId('x'), isNull);
    });

    test('buildTranslations merges base and modules', () {
      final r = ModuleRegistry([
        _FakeModule('m', 10, translations: {
          'pl': {'m.key': 'wartość'},
          'en': {'m.key': 'value'},
        }),
      ]);
      final t = r.buildTranslations([
        'pl',
        'en'
      ], base: {
        'pl': {'common.ok': 'OK-pl'},
        'en': {'common.ok': 'OK-en'},
      });
      expect(t['pl']!['m.key'], 'wartość');
      expect(t['pl']!['common.ok'], 'OK-pl');
      expect(t['en']!['m.key'], 'value');
    });

    test('missing EN key falls back to PL', () {
      final r = ModuleRegistry([
        _FakeModule('m', 10, translations: {
          'pl': {'m.only_pl': 'tylko po polsku', 'm.both': 'oba-pl'},
          'en': {'m.both': 'both-en'},
        }),
      ]);
      final t = r.buildTranslations(['pl', 'en'], fallback: 'pl');

      expect(t['en']!['m.both'], 'both-en');
      expect(t['en']!['m.only_pl'], 'tylko po polsku');
    });
  });
}
