import 'package:flutter/material.dart';

/// Paleta kolorow odwzorowana 1:1 z motywu webowego gry
/// (assets/css/variables.css + dashboard.css). Jedno zrodlo prawdy o kolorach.
///
/// Color palette mirrored 1:1 from the web game theme. Single source of truth.
class AppColors {
  AppColors._();

  // ── Zloto / Gold ──────────────────────────────────────────────
  static const gold = Color(0xFFC8A84B);
  static const goldBright = Color(0xFFE8CC7A); // --gold2
  static const goldDark = Color(0xFFA08030); // --gold3
  static const goldDim = Color(0x1FC8A84B); // rgba(200,168,75,.12)
  static const goldBorder = Color(0x40C8A84B); // rgba(200,168,75,.25)
  static const goldSurface = Color(0x1AC8A84B); // rgba(200,168,75,.10)

  // ── Tla / Backgrounds ─────────────────────────────────────────
  static const bg = Color(0xFF08080F); // --bg
  static const bg2 = Color(0xFF0F0F18); // --bg2
  static const bg3 = Color(0xFF161622); // --bg3
  static const bg4 = Color(0xFF1E1E2E); // --bg4
  static const topbar = Color(0xFF080810);

  // ── Tekst / Text ──────────────────────────────────────────────
  static const text = Color(0xFFE8E8F0); // --text
  static const text2 = Color(0x99E8E8F0); // rgba(232,232,240,.6)
  static const text3 = Color(0x59E8E8F0); // rgba(232,232,240,.35)

  // ── Semantyczne / Semantic ────────────────────────────────────
  static const green = Color(0xFF4EC97A); // --green (pieniadze / money)
  static const greenDim = Color(0x1A4EC97A); // rgba(78,201,122,.10)
  static const red = Color(0xFFE05555); // --red
  static const redDim = Color(0x1FE05555); // rgba(224,85,85,.12)
  static const blue = Color(0xFF5B9CF6); // --blue
  static const orange = Color(0xFFF0A050); // --orange
  static const warn = Color(0xFFE6B43C); // --warn

  // ── Obramowania / Borders ─────────────────────────────────────
  static const border = Color(0x2EC8A84B); // rgba(200,168,75,.18)
  static const borderSubtle = Color(0x14FFFFFF); // rgba(255,255,255,.08)
  static const borderFaint = Color(0x0FFFFFFF); // rgba(255,255,255,.06)

  // ── Ikony kafelkow KPI (z GameShell.php) ──────────────────────
  static const iconCash = Color(0xFFC8860A);
  static const iconBank = Color(0xFF5B8DD9);
  static const iconStorage = Color(0xFF2A9D6E);
  static const iconOil = Color(0xFFE0B020);
  static const iconStatus = Color(0xFF20B2AA);
}
