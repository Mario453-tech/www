import 'package:flutter/material.dart';
import '../../widgets/department_webview.dart';

class TechnicalScreen extends StatelessWidget {
  const TechnicalScreen({super.key});

  @override
  Widget build(BuildContext context) =>
      const DepartmentWebView(path: '/technical', title: 'Dział Techniczny');
}
