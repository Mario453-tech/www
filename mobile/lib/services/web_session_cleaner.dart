import 'package:webview_flutter/webview_flutter.dart';

class WebSessionCleaner {
  WebSessionCleaner._();

  static Future<void> clearCookies() async {
    await WebViewCookieManager().clearCookies();
  }

  static Future<void> clearControllerStorage(
      WebViewController controller) async {
    await controller.clearCache();
    await controller.clearLocalStorage();
  }
}
