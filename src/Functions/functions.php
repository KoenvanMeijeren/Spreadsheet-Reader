<?php

/**
 * @file
 * The functions.php file.
 */

/**
 * Gets the integer value of a 4-byte string.
 */
function get_int4d(string $data, int $pos): int {
  $value = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
  if ($value >= 4294967294) {
    $value = -2;
  }
  return $value;
}

/**
 * Http://uk.php.net/manual/en/function.getdate.php.
 */
function gm_get_date(int|float|NULL $ts = NULL): array {
  $k = ['seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0];
  return (array_combine($k, explode(":", gmdate('s:i:G:j:w:n:Y:z:l:F:U', !$ts ? time() : (int) $ts))));
}

/**
 * Convert a 1900 based date offset into a Unix timestamp.
 */
function v(string $data, int|float $pos): int {
  return ord($data[$pos]) | ord($data[$pos + 1]) << 8;
}
