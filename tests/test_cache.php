<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Cache.php';

$pass = 0;
$fail = 0;

function assert_eq(mixed $actual, mixed $expected, string $label): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        echo " $label\n";
        $pass++;
    } else {
        echo " $label — oczekiwano: " . var_export($expected, true) . ", otrzymano: " . var_export($actual, true) . "\n";
        $fail++;
    }
}

// Test 1: set/get
Cache::set('test_key', ['foo' => 'bar'], 60);
$val = Cache::get('test_key');
assert_eq($val['foo'] ?? null, 'bar', 'Cache set/get działa');

// Test 2: delete
Cache::delete('test_key');
assert_eq(Cache::get('test_key'), null, 'Cache delete działa');

// Test 3: TTL wygasa
Cache::set('test_ttl', 'value', 1);
sleep(2);
assert_eq(Cache::get('test_ttl'), null, 'Cache TTL wygasa po 1s');

// Test 4: flush
Cache::set('flush_a', 'x', 60);
Cache::set('flush_b', 'y', 60);
Cache::flush();
assert_eq(Cache::get('flush_a'), null, 'Cache flush — klucz A usunięty');
assert_eq(Cache::get('flush_b'), null, 'Cache flush — klucz B usunięty');

echo "\n--- Wynik: $pass OK, $fail BŁĘDÓW ---\n";
