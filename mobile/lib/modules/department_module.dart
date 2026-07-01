import 'package:flutter/material.dart';
import '../widgets/department_webview.dart';
import 'app_module.dart';

/// Generic module for any web department page.
/// showInNav = false → appears only in the side drawer, not bottom nav.
class DepartmentModule extends AppModule {
  final String _id;
  final String _titleKey;
  final IconData _icon;
  final int _order;
  final String _path;
  final String _title;

  DepartmentModule({
    required String id,
    required String titleKey,
    required IconData icon,
    required int order,
    required String path,
    required String title,
  })  : _id = id,
        _titleKey = titleKey,
        _icon = icon,
        _order = order,
        _path = path,
        _title = title;

  @override
  String get id => _id;

  @override
  String get titleKey => _titleKey;

  @override
  IconData get navIcon => _icon;

  @override
  int get order => _order;

  @override
  bool get showInNav => false;

  @override
  Widget buildScreen(BuildContext context) =>
      DepartmentWebView(path: _path, title: _title);
}
