import 'package:flutter/foundation.dart';
import '../models/player.dart';
import '../services/api_service.dart';
import '../services/session_storage.dart';
import '../services/web_session_cleaner.dart';

class AuthProvider extends ChangeNotifier {
  final SessionStorage _storage;

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

  AuthProvider({SessionStorage? storage})
      : _storage = storage ?? SessionStorage();

  Future<void> init() async {
    try {
      await _storage.migrateLegacyPrefs();
      _token = await _storage.readToken();
      _username = await _storage.readUsername();
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

      await _storage.saveSession(
        token: result.token,
        username: result.username,
      );

      await _refreshPlayer();
      if (_token == null) {
        _error = 'auth.error_session';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      _isLoading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.statusCode == 401 ? 'auth.error_credentials' : e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'common.error_connection';
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
    _error = null;

    try {
      await _storage.clearSession();
    } catch (_) {
      // Local state is already cleared; remote token revocation still continues.
    }
    try {
      await WebSessionCleaner.clearCookies();
    } catch (_) {
      // Cookie cleanup is best effort; it must not block logout.
    }
    notifyListeners();

    if (tokenToRevoke != null) {
      try {
        await ApiService.logout(tokenToRevoke);
      } catch (_) {
        // Best effort logout.
      }
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
      if (e.statusCode == 401) {
        await logout();
      }
    } catch (_) {
      _error = 'common.error_connection';
      notifyListeners();
    }
  }
}
