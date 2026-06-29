import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/services/session_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MemorySecureValueStore implements SecureValueStore {
  final Map<String, String> values = {};

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async {
    values[key] = value;
  }

  @override
  Future<void> delete(String key) async {
    values.remove(key);
  }

  @override
  Future<void> deleteAll() async {
    values.clear();
  }
}

void main() {
  test('migrates legacy auth data from SharedPreferences to secure storage',
      () async {
    SharedPreferences.setMockInitialValues({
      SessionStorage.tokenKey: 'legacy-token',
      SessionStorage.usernameKey: 'legacy-user',
    });
    final prefs = await SharedPreferences.getInstance();
    final secure = MemorySecureValueStore();
    final storage = SessionStorage(secure: secure);

    await storage.migrateLegacyPrefs(prefs: prefs);

    expect(await storage.readToken(), 'legacy-token');
    expect(await storage.readUsername(), 'legacy-user');
    expect(prefs.getString(SessionStorage.tokenKey), isNull);
    expect(prefs.getString(SessionStorage.usernameKey), isNull);
  });

  test('migration does not overwrite an existing secure session', () async {
    SharedPreferences.setMockInitialValues({
      SessionStorage.tokenKey: 'legacy-token',
      SessionStorage.usernameKey: 'legacy-user',
    });
    final prefs = await SharedPreferences.getInstance();
    final secure = MemorySecureValueStore();
    secure.values[SessionStorage.tokenKey] = 'secure-token';
    secure.values[SessionStorage.usernameKey] = 'secure-user';
    final storage = SessionStorage(secure: secure);

    await storage.migrateLegacyPrefs(prefs: prefs);

    expect(await storage.readToken(), 'secure-token');
    expect(await storage.readUsername(), 'secure-user');
    expect(prefs.getString(SessionStorage.tokenKey), isNull);
    expect(prefs.getString(SessionStorage.usernameKey), isNull);
  });

  test('saveSession writes only secure auth values', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final storage = SessionStorage(secure: MemorySecureValueStore());

    await storage.saveSession(token: 'new-token', username: 'new-user');

    expect(await storage.readToken(), 'new-token');
    expect(await storage.readUsername(), 'new-user');
    expect(prefs.getString(SessionStorage.tokenKey), isNull);
    expect(prefs.getString(SessionStorage.usernameKey), isNull);
  });

  test('clearSession removes secure auth values', () async {
    final storage = SessionStorage(secure: MemorySecureValueStore());
    await storage.saveSession(token: 'token', username: 'user');

    await storage.clearSession();

    expect(await storage.readToken(), isNull);
    expect(await storage.readUsername(), isNull);
  });
}
