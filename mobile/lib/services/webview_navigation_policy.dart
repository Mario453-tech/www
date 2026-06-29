class WebViewNavigationPolicy {
  static const allowedRootHost = 'oilempire.pl';

  static bool isAllowedUrl(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) {
      return false;
    }
    if (uri.scheme == 'about') {
      return true;
    }
    if (uri.scheme != 'https') {
      return false;
    }
    return uri.host == allowedRootHost ||
        uri.host.endsWith('.$allowedRootHost');
  }
}
