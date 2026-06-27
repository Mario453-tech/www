import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../models/market.dart';
import '../models/player.dart';
import '../models/well.dart';

class ApiException implements Exception {
  final int statusCode;
  final String message;
  const ApiException(this.statusCode, this.message);

  @override
  String toString() => 'ApiException($statusCode): $message';
}

class LoginResult {
  final String token;
  final int playerId;
  final String username;

  const LoginResult({
    required this.token,
    required this.playerId,
    required this.username,
  });
}

class ApiService {
  static const _timeout = Duration(seconds: 15);

  static Map<String, String> _headers(String? token) => {
        'Content-Type': 'application/json; charset=utf-8',
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  static Future<LoginResult> login(String login, String password) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/auth/login.php');
    final response = await http
        .post(
          uri,
          headers: _headers(null),
          body: jsonEncode({
            'login': login,
            'password': password,
            'device': AppConfig.deviceName,
          }),
        )
        .timeout(_timeout);

    final body = _decode(response);
    final token = body['token'] as String?;
    final playerId = body['player_id'] as num?;
    final username = body['username'] as String?;
    if (token == null || playerId == null || username == null) {
      throw const ApiException(200, 'Invalid login response from server');
    }
    return LoginResult(
      token: token,
      playerId: playerId.toInt(),
      username: username,
    );
  }

  static Future<void> logout(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/auth/logout.php');
    try {
      await http.post(uri, headers: _headers(token)).timeout(_timeout);
    } catch (_) {}
  }

  static Future<Player> getPlayer(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/player/');
    final response = await http
        .get(uri, headers: _headers(token))
        .timeout(_timeout);
    return Player.fromJson(_decode(response));
  }

  static Future<MarketState> getMarket(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/market/');
    final response = await http
        .get(uri, headers: _headers(token))
        .timeout(_timeout);
    return MarketState.fromJson(_decode(response));
  }

  static Future<List<Well>> getWells(String token, {String? status}) async {
    var uri = Uri.parse('${AppConfig.baseUrl}/wells/');
    if (status != null) {
      uri = uri.replace(queryParameters: {'status': status});
    }
    final response = await http
        .get(uri, headers: _headers(token))
        .timeout(_timeout);
    final body = _decode(response);
    final list = body['wells'] as List<dynamic>? ?? [];
    return list.map((e) => Well.fromJson(e as Map<String, dynamic>)).toList();
  }

  static Map<String, dynamic> _decode(http.Response response) {
    final raw = response.body;
    dynamic parsed;
    try {
      parsed = jsonDecode(raw);
    } catch (_) {
      // Serwer zwrocil cos co nie jest JSON-em (np. strona bledu HTML / fatal 500).
      // Pokazujemy kod HTTP i wycinek tresci, zeby mozna bylo zdiagnozowac przyczyne.
      final trimmed = raw.trim();
      final snippet = trimmed.isEmpty
          ? '(pusta odpowiedz)'
          : trimmed.substring(0, trimmed.length > 400 ? 400 : trimmed.length);
      throw ApiException(
        response.statusCode,
        'Serwer zwrocil nie-JSON (HTTP ${response.statusCode}):\n$snippet',
      );
    }
    if (parsed is! Map<String, dynamic>) {
      throw ApiException(
        response.statusCode,
        'Nieoczekiwany format odpowiedzi (HTTP ${response.statusCode})',
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException(
        response.statusCode,
        parsed['error'] as String? ?? 'Blad serwera (HTTP ${response.statusCode})',
      );
    }
    return parsed;
  }
}
