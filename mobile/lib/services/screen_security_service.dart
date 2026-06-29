import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class ScreenSecurityService {
  static const MethodChannel _channel =
      MethodChannel('pl.oilempire.app/screen_security');

  ScreenSecurityService._();

  static Future<void> setProtected(bool enabled) async {
    if (!kReleaseMode) {
      return;
    }
    try {
      await _channel.invokeMethod<void>('setProtected', {'enabled': enabled});
    } catch (_) {
      // Screen privacy is a platform hardening layer, not an app blocker.
    }
  }
}
