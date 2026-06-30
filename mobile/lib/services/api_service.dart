import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/app_config.dart';
import '../models/map_data.dart';
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

class WebBridgeResult {
  final String bridgeUrl;
  final int expiresInSeconds;

  const WebBridgeResult({
    required this.bridgeUrl,
    required this.expiresInSeconds,
  });
}

class ApiService {
  static const _timeout = Duration(seconds: 15);

  static Map<String, String> _headers(String? token, {String? locale}) => {
        'Content-Type': 'application/json; charset=utf-8',
        'Accept': 'application/json',
        if (locale != null) 'Accept-Language': locale,
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
      throw const ApiException(200, 'api.error.invalid_login_response');
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
    } catch (_) {
      // Best effort logout.
    }
  }

  static Future<WebBridgeResult> createWebBridge(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/auth/webview-bridge.php');
    final response =
        await http.post(uri, headers: _headers(token)).timeout(_timeout);
    final body = _decode(response);
    final bridgeUrl = body['bridge_url'] as String?;
    final expiresInSeconds = body['expires_in_seconds'] as num?;
    if (bridgeUrl == null || expiresInSeconds == null) {
      throw const ApiException(200, 'api.error.invalid_bridge_response');
    }

    return WebBridgeResult(
      bridgeUrl: bridgeUrl,
      expiresInSeconds: expiresInSeconds.toInt(),
    );
  }

  static Future<Player> getPlayer(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/player/');
    final response =
        await http.get(uri, headers: _headers(token)).timeout(_timeout);
    return Player.fromJson(_decode(response));
  }

  static Future<MarketState> getMarket(String token, {String? locale}) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/market/');
    final response = await http
        .get(uri, headers: _headers(token, locale: locale))
        .timeout(_timeout);
    return MarketState.fromJson(_decode(response));
  }

  static Future<List<Well>> getWells(String token, {String? status}) async {
    var uri = Uri.parse('${AppConfig.baseUrl}/wells/');
    if (status != null) {
      uri = uri.replace(queryParameters: {'status': status});
    }
    final response =
        await http.get(uri, headers: _headers(token)).timeout(_timeout);
    final body = _decode(response);
    final list = body['wells'] as List<dynamic>? ?? [];
    return list.map((e) => Well.fromJson(e as Map<String, dynamic>)).toList();
  }

  static Future<MapData> getMapData(String token) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/maps/');
    final response =
        await http.get(uri, headers: _headers(token)).timeout(_timeout);
    return MapData.fromJson(_decode(response));
  }

  static Future<Map<String, dynamic>> applyPermit(
      String token, int regionId) async {
    final uri = Uri.parse('${AppConfig.baseUrl}/permits/apply.php');
    final response = await http
        .post(
          uri,
          headers: _headers(token),
          body: jsonEncode({'region_id': regionId}),
        )
        .timeout(_timeout);
    return _decode(response);
  }

  static Map<String, dynamic> _decode(http.Response response) {
    final raw = response.body;
    dynamic parsed;

    try {
      parsed = jsonDecode(raw);
    } catch (_) {
      final trimmed = raw.trim();
      final snippet = trimmed.isEmpty
          ? 'api.error.empty_response'
          : trimmed.substring(0, trimmed.length > 400 ? 400 : trimmed.length);
      throw ApiException(
        response.statusCode,
        trimmed.isEmpty
            ? 'api.error.empty_response'
            : 'api.error.non_json_response|${response.statusCode}|$snippet',
      );
    }

    if (parsed is! Map<String, dynamic>) {
      throw ApiException(
        response.statusCode,
        'api.error.unexpected_format|${response.statusCode}',
      );
    }

    if (response.statusCode >= 400) {
      throw ApiException(
        response.statusCode,
        _serverErrorMessage(parsed, response.statusCode),
      );
    }

    return parsed;
  }

  static String _serverErrorMessage(Map<String, dynamic> body, int statusCode) {
    final raw = (body['error'] ?? body['message']) as String?;
    if (raw != null && _looksLikeTranslationKey(raw)) {
      return raw;
    }
    return 'api.error.server_http|$statusCode';
  }

  static bool _looksLikeTranslationKey(String value) {
    return RegExp(r'^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$').hasMatch(value);
  }
}
