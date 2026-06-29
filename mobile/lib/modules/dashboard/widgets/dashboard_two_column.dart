import 'package:flutter/material.dart';
import '../dashboard_styles.dart';

class DashboardTwoColumn extends StatelessWidget {
  final Widget left;
  final Widget right;

  const DashboardTwoColumn({
    super.key,
    required this.left,
    required this.right,
  });

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: left),
          const SizedBox(width: DashboardStyles.gapMd),
          Expanded(child: right),
        ],
      ),
    );
  }
}
