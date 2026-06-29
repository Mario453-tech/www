import 'package:flutter/material.dart';
import '../../theme/app_colors.dart';
import '../../theme/app_theme.dart';

/// Shared visual constants for the dashboard module.
class DashboardStyles {
  DashboardStyles._();

  static const double eventCompactBreakpoint = 430;
  static const double cardRadius = 12;
  static const double panelRadius = 10;
  static const double iconTileRadius = 8;
  static const double pillRadius = 999;
  static const double eventIconSize = 38;
  static const double eventTimerWidth = 112;
  static const double errorIconSize = 48;
  static const double kpiIconTileSize = 38;
  static const double kpiIconSize = 18;
  static const double loansBannerIconSize = 20;
  static const double pulseDotSize = 9;
  static const double pulseSpreadRadius = 6;
  static const double eventTimerDotSize = 7;
  static const double progressRadius = 2;
  static const double progressHeight = 3;
  static const double gapMd = 12;
  static const double gapLg = 16;

  static const EdgeInsets screenPadding = EdgeInsets.fromLTRB(16, 16, 16, 24);
  static const EdgeInsets cardPadding = EdgeInsets.all(14);
  static const EdgeInsets errorPadding = EdgeInsets.all(24);
  static const EdgeInsets errorMessagePadding = EdgeInsets.all(12);
  static const EdgeInsets loansBannerPadding = EdgeInsets.all(14);
  static const EdgeInsets pillPadding =
      EdgeInsets.symmetric(horizontal: 12, vertical: 6);
  static const EdgeInsets timerPadding =
      EdgeInsets.symmetric(horizontal: 10, vertical: 10);
  static const Color refreshColor = AppColors.gold;
  static const Color eventTimerDotColor = AppColors.orange;
  static const Color pulseDotColor = AppColors.green;
  static const Color neutralPillBg = Color(0x1AFFFFFF);
  static const Color transparentPillBg = Colors.transparent;
  static const Color errorMessageBg = Color(0x14FFFFFF);
  static const Color progressBackground = Color(0x12FFFFFF);

  static const LinearGradient cardGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [AppColors.bg3, AppColors.bg2],
  );

  static BoxDecoration goldCardDecoration({double radius = cardRadius}) =>
      BoxDecoration(
        gradient: cardGradient,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.goldBorder),
      );

  static BoxDecoration subtlePanelDecoration({double radius = panelRadius}) =>
      BoxDecoration(
        color: const Color(0x47000000),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.borderSubtle),
      );

  static BoxDecoration loansBannerDecoration() => BoxDecoration(
        color: AppColors.redDim,
        borderRadius: BorderRadius.circular(panelRadius),
        border: Border.all(color: AppColors.red.withValues(alpha: 0.3)),
      );

  static BoxDecoration eventIconDecoration(Color color) => BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(panelRadius),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      );

  static BoxDecoration kpiCardDecoration() => BoxDecoration(
        gradient: cardGradient,
        borderRadius: BorderRadius.circular(panelRadius),
        border: Border.all(color: AppColors.goldBorder.withValues(alpha: 0.15)),
      );

  static BoxDecoration kpiIconTileDecoration(Color color) => BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(iconTileRadius),
        border: Border.all(color: color.withValues(alpha: 0.30)),
      );

  static Color eventImpactColor(bool positive) =>
      positive ? AppColors.green : AppColors.red;

  static Color eventImpactPillBg(Color color) => color.withValues(alpha: 0.12);

  static Color eventImpactPillBorder(Color color) =>
      color.withValues(alpha: 0.4);

  static BoxDecoration pillDecoration(Color bg, Color? border) => BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(pillRadius),
        border: border != null ? Border.all(color: border) : null,
      );

  static BoxDecoration errorMessageDecoration() => BoxDecoration(
        color: errorMessageBg,
        borderRadius: BorderRadius.circular(iconTileRadius),
      );

  static TextStyle get greeting => const TextStyle(
        fontSize: 22,
        fontWeight: FontWeight.w800,
        color: AppColors.text,
      );

  static TextStyle get eventMessage => const TextStyle(
        fontSize: 14,
        height: 1.35,
        fontWeight: FontWeight.w700,
        color: AppColors.text,
      );

  static TextStyle get timerLabel =>
      AppTheme.kpiLabel.copyWith(fontSize: 8, letterSpacing: 1.2);

  static const TextStyle timerValue = TextStyle(
    fontSize: 27,
    fontWeight: FontWeight.w800,
    letterSpacing: 1.1,
    color: AppColors.orange,
    height: 1,
  );

  static const TextStyle timerSub = TextStyle(
    fontSize: 9,
    height: 1.25,
    color: AppColors.text3,
  );

  static const TextStyle errorTitle = TextStyle(
    fontSize: 16,
    color: AppColors.text,
  );

  static const TextStyle errorDetails = TextStyle(
    fontSize: 12,
    color: AppColors.text2,
  );

  static TextStyle kpiValue(bool money) => TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w800,
        height: 1.1,
        letterSpacing: -0.4,
        color: money ? AppColors.green : AppColors.text,
      );

  static const TextStyle kpiSub = TextStyle(
    fontSize: 12,
    color: AppColors.text3,
  );

  static const TextStyle pillText = TextStyle(
    fontSize: 11,
    fontWeight: FontWeight.w700,
    letterSpacing: 0.8,
  );

  static const TextStyle loansBannerText = TextStyle(
    color: AppColors.text,
    fontWeight: FontWeight.w600,
  );

  static List<BoxShadow> pulseDotShadow(double progress) => [
        BoxShadow(
          color: pulseDotColor.withValues(alpha: (1 - progress) * 0.5),
          blurRadius: 0,
          spreadRadius: progress * pulseSpreadRadius,
        ),
      ];
}
