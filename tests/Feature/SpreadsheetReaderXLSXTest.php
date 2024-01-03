<?php

/**
 * @file
 */

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderInterface;
use KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader;

it('can open an XLSX file', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $expectedHeaderRow = [
    '',
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
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());
});

it('can open an empty XLSX file', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_empty.xlsx');
  $expectedHeaderRow = ['', '', '', '', '', '', ''];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());
});

it('can open an XLSX file with only a header', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_only_header.xlsx');
  $expectedHeaderRow = [
    '',
    'First Name',
    'Last Name',
    'Gender',
    'Country',
    'Age',
    'Date',
    'Id',
    '',
    'Test',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
  $this->assertSame(1, $reader->count());
  $this->assertSame(0, $reader->key());
  $this->assertSame($expectedHeaderRow, $reader->current());
});

it('can traverse through the XLSX file', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $expectedHeaderRow = [
    '',
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
  $expectedRowIndex = 5001;

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertCount(1, $reader->sheets());
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
  $this->assertSame($expectedRowIndex, $reader->key());
  $this->assertSame(($expectedRowIndex + 1), $reader->count());
});

it('can rewind the reader', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $expectedHeaderRow = [
    '',
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

it('can seek for a specific index', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
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

it('can seek for a value which is already traversed', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $seek_index = 3;
  $expected_row = [
    '2',
    'Mara',
    'Hashimoto',
    'Female',
    'Great Britain',
    '25',
    '16/08/2016',
    '1582',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  $last_row = [];
  for ($index = 0; $index < $seek_index * 2; $index++) {
    $reader->next();
    $last_row = $reader->current();
  }

  $reader->seek($seek_index);

  // Assert.
  $this->assertSame($seek_index, $reader->key());
  $this->assertSame($expected_row, $reader->current());
  $this->assertNotSame($last_row, $reader->current());
});

it('does not rewind if the current position is already the desired key', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $mocked_reader = Mockery::mock(SpreadsheetReaderInterface::class, [
    'filepath' => $filepath,
  ]);
  $seek_index = 3;

  // Act & assert.
  $mocked_reader->shouldReceive('key')->andReturn($seek_index);
  $mocked_reader->shouldReceive('valid')->never();
  $mocked_reader->shouldReceive('rewind')->never();
  $mocked_reader->shouldReceive('next')->never();

  $reader = new SpreadsheetReader(filepath: '', reader: $mocked_reader);
  $reader->seek($seek_index);

  $this->assertSame($seek_index, $reader->key());

  // Clean up.
  Mockery::close();
});

it('can seek for non-existing indexes', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $seek_index = 5300;

  // Act & assert.
  $this->expectException(OutOfBoundsException::class);
  $this->expectExceptionMessage("SpreadsheetError: Position {$seek_index} not found");

  $reader = new SpreadsheetReader($filepath);
  $reader->seek($seek_index);
});

it('throws an exception for non-readable file', function () {
  // Arrange.
  $nonExistentFilepath = '/path/to/nonexistent/file.csv';

  // Act & Assert.
  $this->expectException(FileNotReadableException::class);
  $this->expectExceptionMessage("File not readable ($nonExistentFilepath)");

  new SpreadsheetReader($nonExistentFilepath);
});

it('runs with good performance and the memory does not peek', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $memory_start = bytes_to_mega_bytes(memory_get_usage());

  // Act.
  $reader = new SpreadsheetReader($filepath);
  while ($reader->valid()) {
    $reader->next();
    if (!$reader->valid()) {
        break;
    }

    $this->assertNotEmpty($reader->current());
  }

  // Assert.
  $memory_end = bytes_to_mega_bytes(memory_get_usage());
  $memory_used = $memory_end - $memory_start;

  $this->assertTrue(in_range($memory_used, 0, 0.2, TRUE), "Memory used: {$memory_used}");
});

it('can return the sheets', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_2_sheets.xlsx');
  $expectedSheets = [
    'Sheet1',
    'Sheet2',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);

  // Assert.
  $this->assertSame($expectedSheets, $reader->sheets());
});

it('can change the sheet', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_2_sheets.xlsx');
  $expectedSheet1Row = [
    '1',
    'Dulce',
    'Abril',
    'Female',
    'United States',
    '32',
    '15/10/2017',
    '1562',
  ];
  $expectedSheet2Row = [
    '1',
    'John',
    'Doe',
    'Male',
    'United States',
    '14',
    '15/10/2017',
    '1562',
  ];

  // Act.
  $reader = new SpreadsheetReader($filepath);
  $reader->next();
  $reader->next();
  $sheet1Row = $reader->current();
  $reader->changeSheet(1);
  $reader->next();
  $reader->next();
  $sheet2Row = $reader->current();

  // Assert.
  $this->assertSame($expectedSheet1Row, $sheet1Row);
  $this->assertSame($expectedSheet2Row, $sheet2Row);
});

it('throws an exception when trying to change the sheet on non-existing index', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example_XLSX_5000.xlsx');
  $index = 1;

  // Act & assert.
  $this->expectException(OutOfBoundsException::class);
  $this->expectExceptionMessage("SpreadsheetError: Position {$index} not found!");

  $reader = new SpreadsheetReader($filepath);
  $reader->changeSheet($index);
});
