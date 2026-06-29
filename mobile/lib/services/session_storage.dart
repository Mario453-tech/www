import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

abstract class SecureValueStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
  Future<void> deleteAll();
}

class FlutterSecureValueStore implements SecureValueStore {
  static const _androidOptions = AndroidOptions(
    encryptedSharedPreferences: true,
  );

  final FlutterSecureStorage _storage;

  FlutterSecureValueStore({FlutterSecureStorage? storage})
      : _storage =
            storage ?? const FlutterSecureStorage(aOptions: _androidOptions);

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) => _storage.write(
        key: key,
        value: value,
      );

  @override
  Future<void> delete(String key) => _storage.delete(key: key);

  @override
  Future<void> deleteAll() => _storage.deleteAll();
}

class SessionStorage {
  static const tokenKey = 'auth_token';
  static const usernameKey = 'auth_username';

  final SecureValueStore _secure;

  SessionStorage({SecureValueStore? secure})
      : _secure = secure ?? FlutterSecureValueStore();

  Future<void> migrateLegacyPrefs({SharedPreferences? prefs}) async {
    final localPrefs = prefs ?? await SharedPreferences.getInstance();
    final legacyToken = localPrefs.getString(tokenKey);
    final legacyUsername = localPrefs.getString(usernameKey);

    if (legacyToken != null && await readToken() == null) {
      await _secure.write(tokenKey, legacyToken);
    }
    if (legacyUsername != null && await readUsername() == null) {
      await _secure.write(usernameKey, legacyUsername);
    }

    await localPrefs.remove(tokenKey);
    await localPrefs.remove(usernameKey);
  }

  Future<String?> readToken() => _secure.read(tokenKey);

  Future<String?> readUsername() => _secure.read(usernameKey);

  Future<void> saveSession({
    required String token,
    required String username,
  }) async {
    await _secure.write(tokenKey, token);
    await _secure.write(usernameKey, username);
  }

  Future<void> clearSession() async {
    await _secure.delete(tokenKey);
    await _secure.delete(usernameKey);
  }
}
