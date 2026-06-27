import 'core_pl.dart';
import 'core_en.dart';

/// Baza wspolnych tlumaczen (niezalezna od modulow), przekazywana do
/// [ModuleRegistry.buildTranslations] jako `base`.
const Map<String, Map<String, String>> coreStrings = {
  'pl': corePl,
  'en': coreEn,
};
