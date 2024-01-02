<?php

/**
 * @file
 */

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader;

it('can open a CSV file', function () {
  // Arrange.
  $filepath = 'tests/MockData/file_example_CSV_5000.csv';
  $expectedHeaderRow = [
    'Nr',
    'First Name',
    'Last Name',
    'Gender',
    'Country',
    'Age',
    'Date',
    'Id',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
  $this->assertTrue($reader->changeSheet(0));
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());
});

it('can traverse through the CSV file', function () {
  // Arrange.
  $filepath = 'tests/MockData/file_example_CSV_5000.csv';
  $expectedHeaderRow = [
    'Nr',
    'First Name',
    'Last Name',
    'Gender',
    'Country',
    'Age',
    'Date',
    'Id',
  ];
  $expectedFirstDataRow = [
    '1',
    'Dulce',
    'Abril',
    'Female',
    'United States',
    '32',
    '15/10/2017',
    '1562',
  ];
  $expectedRowsCount = 5001;

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
  $this->assertTrue($reader->changeSheet(0));
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());

  $reader->next();
  $this->assertSame(1, $reader->key());
  $this->assertSame($expectedFirstDataRow, $reader->current());
  $this->assertSame(2, $reader->count());

  while ($reader->valid()) {
      $reader->next();
  }

  $this->assertFalse($reader->valid());
  $this->assertSame($expectedRowsCount, $reader->key());
  $this->assertSame(($expectedRowsCount + 1), $reader->count());
});

it('can rewind the reader', function () {
  // Arrange.
  $filepath = 'tests/MockData/file_example_CSV_5000.csv';
  $expectedHeaderRow = [
    'Nr',
    'First Name',
    'Last Name',
    'Gender',
    'Country',
    'Age',
    'Date',
    'Id',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
  $this->assertTrue($reader->changeSheet(0));
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());

  $reader->next();
  $reader->next();
  $reader->next();
  $this->assertSame(3, $reader->key());
  $this->assertSame(4, $reader->count());

  $reader->rewind();
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());
});

it('can read a specific key', function () {
  // Arrange.
  $filepath = 'tests/MockData/file_example_CSV_5000.csv';
  $expectedRow = [
    '3',
    'Philip',
    'Gent',
    'Male',
    'France',
    '36',
    '21/05/2015',
    '2587',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);
  $reader->seek(4);

  // Assert.
  $this->assertSame(5, $reader->count());
  $this->assertSame(4, $reader->key());
  $this->assertSame($expectedRow, $reader->current());
});

it('throws an exception for non-readable file', function () {
  // Arrange.
  $nonExistentFilepath = '/path/to/nonexistent/file.csv';

  // Act & Assert.
  $this->expectException(FileNotReadableException::class);
  $this->expectExceptionMessage("File not readable ($nonExistentFilepath)");

  new SpreadsheetReader($nonExistentFilepath);
});
