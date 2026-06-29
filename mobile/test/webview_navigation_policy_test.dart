import 'package:flutter_test/flutter_test.dart';
import 'package:oil_empire/services/webview_navigation_policy.dart';

void main() {
  group('WebViewNavigationPolicy', () {
    test('allows production host and subdomains over HTTPS', () {
      expect(
          WebViewNavigationPolicy.isAllowedUrl('https://oilempire.pl'), isTrue);
      expect(
        WebViewNavigationPolicy.isAllowedUrl('https://api.oilempire.pl/path'),
        isTrue,
      );
    });

    test('blocks cleartext and foreign hosts', () {
      expect(
          WebViewNavigationPolicy.isAllowedUrl('http://oilempire.pl'), isFalse);
      expect(
          WebViewNavigationPolicy.isAllowedUrl('https://example.com'), isFalse);
      expect(
        WebViewNavigationPolicy.isAllowedUrl('https://bad-oilempire.pl'),
        isFalse,
      );
    });
  });
}
