#!/usr/bin/env php
<?php

/**
 * Terabras — validate the local translation overrides.
 *
 * plugins/terabras/locales/core/<lang>.php overrides upstream strings by msgid.
 * If upstream rewords a source string the key silently stops matching and the
 * override quietly becomes dead weight — the white-label wording would regress
 * with no error anywhere. This check turns that into a build failure.
 *
 *     php tests/terabras/check_locale_overrides.php
 *
 * Keys listed in OWN_STRINGS are introduced by Terabras' own templates and have
 * no upstream msgid, so they are expected not to match.
 */

const OWN_STRINGS = [
    'Administrator account',
    'This password is shown only once. Copy it now and store it somewhere safe.',
    'The administrator account %s was configured with the password provided to the installer.',
    'You can create, modify or delete accounts once logged in.',
    'Use %s',
    '%s internal database',
];

$root = dirname(__DIR__, 2);

/**
 * Every msgid in a .po file, with the multi-line continuation syntax folded in.
 *
 * @return array<string, true>
 */
function msgids(string $po_file): array
{
    $ids = [];
    $current = null;
    foreach (file($po_file, FILE_IGNORE_NEW_LINES) as $line) {
        if (str_starts_with($line, 'msgid ')) {
            $current = unquote(substr($line, 6));
        } elseif ($current !== null && str_starts_with($line, '"')) {
            $current .= unquote($line);
        } elseif ($current !== null) {
            $ids[$current] = true;
            $current = null;
        }
    }
    if ($current !== null) {
        $ids[$current] = true;
    }
    return $ids;
}

function unquote(string $s): string
{
    $s = trim($s);
    if (!str_starts_with($s, '"') || !str_ends_with($s, '"')) {
        return $s;
    }
    return stripcslashes(substr($s, 1, -1));
}

$failures = 0;
$checked = 0;

foreach (glob($root . '/plugins/terabras/locales/core/*.php') as $override_file) {
    $lang = basename($override_file, '.php');
    $po = $root . '/locales/' . $lang . '.po';
    if (!is_file($po)) {
        printf("  SKIP  %s (no upstream catalogue)\n", $lang);
        continue;
    }

    $overrides = require $override_file;
    $ids = msgids($po);

    $unknown = array_values(array_filter(
        array_keys($overrides),
        static fn(string $key): bool => !isset($ids[$key]) && !in_array($key, OWN_STRINGS, true)
    ));

    $checked += count($overrides);
    if ($unknown === []) {
        printf("  OK    %s: all %d keys match an upstream msgid\n", $lang, count($overrides));
        continue;
    }

    $failures += count($unknown);
    printf("  FAIL  %s: %d key(s) no longer match an upstream msgid:\n", $lang, count($unknown));
    foreach ($unknown as $key) {
        printf("          %s\n", var_export($key, true));
    }
}

// A value that still says "GLPI" defeats the point of the override.
foreach (glob($root . '/plugins/terabras/locales/core/*.php') as $override_file) {
    $lang = basename($override_file, '.php');
    foreach (require $override_file as $key => $value) {
        // "Terabras" is a legitimate rendering; the product name replacing the other one.
        if (is_string($value) && str_contains($value, 'GLPI')) {
            printf("  FAIL  %s: override for %s still says \"GLPI\": %s\n", $lang, var_export($key, true), $value);
            $failures++;
        }
    }
}

printf("\n%d override(s) checked, %d failure(s)\n", $checked, $failures);
exit($failures === 0 ? 0 : 1);
