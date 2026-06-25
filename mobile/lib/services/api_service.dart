import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
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
    late Map<String, dynamic> body;
    try {
      body = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException(response.statusCode, 'Invalid JSON response');
    }
    if (response.statusCode >= 400) {
      throw ApiException(
        response.statusCode,
        body['error'] as String? ?? 'Unknown error',
      );
    }
    return body;
  }
}
