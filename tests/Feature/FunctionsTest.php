<?php

/**
 * @file
 */

it('returns correct integer for a given 4-byte string', function () {
  // Arrange.
  $data = "\x01\x02\x03\x04"; // 67305985 in little-endian

  // Act.
  $result = get_int4d($data, 0);

  // Assert.
  $this->assertEquals(67305985, $result);
});

it('returns correct integer for a different 4-byte string', function () {
  // Arrange.
  $data = "\x00\x00\x00\x00"; // 0 in little-endian

  // Act.
  $result = get_int4d($data, 0);

  // Assert.
  $this->assertEquals(0, $result);
});

it('handles large values correctly', function () {
  // Arrange.
  $data = "\xFF\xFF\xFF\xFF"; // 4294967295 in little-endian

  // Act.
  $result = get_int4d($data, 0);

  // Assert.
  $this->assertEquals(-2, $result); // Function treats values >= 4294967294 as -2
});

it('returns correct array for current timestamp', function () {
  // Arrange & act.
  $result = gm_get_date();
  $expected = array_combine(
    ['seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0],
    explode(":", gmdate('s:i:G:j:w:n:Y:z:l:F:U', time()))
  );

  // Assert.
  $this->assertEquals($expected, $result);
});

it('returns correct array for a specific timestamp', function () {
  // Arrange.
  $timestamp = 1638382800; // Specific GMT timestamp

  // Act.
  $result = gm_get_date($timestamp);
  $expected = array_combine(
    ['seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0],
    explode(":", gmdate('s:i:G:j:w:n:Y:z:l:F:U', $timestamp))
  );

  // Assert.
  $this->assertEquals($expected, $result);
});

it('returns correct array for a float timestamp', function () {
  // Arrange.
  $timestamp = 1638382800.123; // Specific GMT timestamp with milliseconds

  // Act.
  $result = gm_get_date($timestamp);
  $expected = array_combine(
    ['seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0],
    explode(":", gmdate('s:i:G:j:w:n:Y:z:l:F:U', (int)$timestamp))
  );

  // Assert.
  $this->assertEquals($expected, $result);
});

it('returns correct integer for 2-byte string', function () {
  // Arrange.
  $data = "\x01\x02"; // 513 in little-endian

  // Act.
  $result = v($data, 0);

  // Assert.
  $this->assertEquals(513, $result);
});

it('returns correct integer for a different 2-byte string', function () {
  // Arrange.
  $data = "\xFF\xFF"; // 65535 in little-endian

  // Act.
  $result = v($data, 0);

  // Assert.
  $this->assertEquals(65535, $result);
});

it('returns correct integer for a 2-byte string at a different position', function () {
  // Arrange.
  $data = "\x00\x00\x01\x02"; // 513 in little-endian from position 2

  // Act.
  $result = v($data, 2);

  // Assert.
  $this->assertEquals(513, $result);
});
