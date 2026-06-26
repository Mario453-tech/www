import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../models/player.dart';

class AuthProvider extends ChangeNotifier {
  static const _keyToken = 'auth_token';
  static const _keyUsername = 'auth_username';

  String? _token;
  String? _username;
  Player? _player;
  bool _isLoading = true;
  String? _error;

  bool get isLoggedIn => _token != null;
  bool get isLoading => _isLoading;
  String? get token => _token;
  String? get username => _username;
  Player? get player => _player;
  String? get error => _error;

  Future<void> init() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _token = prefs.getString(_keyToken);
      _username = prefs.getString(_keyUsername);
      if (_token != null) {
        await _refreshPlayer();
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login(String login, String password) async {
    _error = null;
    _isLoading = true;
    notifyListeners();

    try {
      final result = await ApiService.login(login, password);
      _token = result.token;
      _username = result.username;

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_keyToken, result.token);
      await prefs.setString(_keyUsername, result.username);

      await _refreshPlayer();
      if (_token == null) {
        _error = 'Błąd weryfikacji sesji.';
        _isLoading = false;
        notifyListeners();
        return false;
      }
      _isLoading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.statusCode == 401 ? 'Nieprawidłowy login lub hasło.' : e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Błąd połączenia z serwerem.';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    final tokenToRevoke = _token;
    _token = null;
    _username = null;
    _player = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyToken);
    await prefs.remove(_keyUsername);
    notifyListeners();
    if (tokenToRevoke != null) {
      try { await ApiService.logout(tokenToRevoke); } catch (_) {}
    }
  }

  Future<void> refreshPlayer() => _refreshPlayer();

  Future<void> _refreshPlayer() async {
    if (_token == null) return;
    _error = null;
    try {
      _player = await ApiService.getPlayer(_token!);
      notifyListeners();
    } on ApiException catch (e) {
      if (e.statusCode == 401) await logout();
    } catch (e) {
      _error = 'Błąd połączenia z serwerem.';
      notifyListeners();
    }
  }
}
