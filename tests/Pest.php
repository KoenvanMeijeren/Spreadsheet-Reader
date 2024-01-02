<?php

/**
 * @file
 * The configuration for Pest.
 *
 * |--------------------------------------------------------------------------
 * | Test Case
 * |--------------------------------------------------------------------------
 * |
 * | The closure you provide to your test functions is always bound to a
 * | specific PHPUnit test case class. By default, that class is
 * | "PHPUnit\Framework\TestCase". Of course, you may need to change it using
 * | the "uses()" function to bind a different classes or traits.
 * | E.g. uses(Tests\TestCase::class)->in('Feature');.
 */

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of
| "expectations" methods that you can use to assert different things. Of course,
| you may extend the Expectation API at any time.
|
 */

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code
| specific to your project that you don't want to repeat in every file. Here you
| can also expose helpers as global functions to help you to reduce the number
| of lines of code in your test files.
|
 */

/**
 * Converts the bytes to megabytes.
 */
function bytes_to_mega_bytes(int $bytes, int $decimals = 2): float {
  return round($bytes / 1024 / 1024, $decimals);
}

/**
 * Determines if $number is between $min and $max.
 */
function in_range(int|float $number, int|float $min, int|float $max, bool $inclusive = FALSE): bool {
  return $inclusive
    ? ($number >= $min && $number <= $max)
    : ($number > $min && $number < $max);
}
