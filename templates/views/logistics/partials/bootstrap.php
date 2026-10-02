<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$currencyLabel = $locale === 'en' ? 'USD' : ($locale === 'de' ? 'EUR' : 'PLN');
$currencyLocale = $locale === 'en' ? 'en-US' : 'pl-PL';
$numberDecimalSep = $locale === 'en' ? '.' : ',';
$numberThousandsSep = $locale === 'en' ? ',' : ' ';
