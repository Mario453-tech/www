import 'package:flutter/material.dart';
import 'app_colors.dart';

/// Motyw aplikacji odwzorowujacy ciemna, zloto-akcentowana szate gry webowej.
/// App theme mirroring the dark, gold-accented look of the web game.
class AppTheme {
  AppTheme._();

  /// Styl etykiet sekcji/kafelkow: WIELKIE LITERY z rozstrzeleniem (jak w web).
  /// Uppercase, letter-spaced label style used across cards/headers.
  static const TextStyle kpiLabel = TextStyle(
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.5,
    color: AppColors.text3,
  );

  static const TextStyle sectionHeader = TextStyle(
    fontSize: 13,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.2,
    color: AppColors.gold,
  );

  static ThemeData dark() {
    const scheme = ColorScheme.dark(
      primary: AppColors.gold,
      onPrimary: Color(0xFF000000),
      primaryContainer: AppColors.goldSurface,
      onPrimaryContainer: AppColors.goldBright,
      secondary: AppColors.green,
      onSecondary: Color(0xFF000000),
      tertiary: AppColors.blue,
      surface: AppColors.bg2,
      onSurface: AppColors.text,
      error: AppColors.red,
      onError: Color(0xFFFFFFFF),
      outline: AppColors.border,
      outlineVariant: AppColors.borderFaint,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.bg,
      canvasColor: AppColors.bg,
      dividerColor: AppColors.borderSubtle,

      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.topbar,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
      ),

      cardTheme: CardThemeData(
        color: AppColors.bg3,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: AppColors.border, width: 1),
        ),
      ),

      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.topbar,
        indicatorColor: AppColors.goldSurface,
        elevation: 0,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: selected ? AppColors.gold : AppColors.text2,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? AppColors.gold : AppColors.text2,
          );
        }),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0x09FFFFFF),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.gold, width: 1.5),
        ),
        labelStyle: const TextStyle(color: AppColors.text2),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.gold,
          foregroundColor: Colors.black,
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.2,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.gold,
        linearTrackColor: Color(0x12FFFFFF),
      ),
    );
  }
}
