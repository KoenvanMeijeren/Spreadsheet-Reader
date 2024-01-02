<?php

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\SpreadsheetReaderCSV;

it('can open a CSV file', function () {
    // Arrange
    $filepath = 'tests/MockData/file_example_CSV_5000.csv';
    $expected_header_row = [
        0 => "Nr,First Name,Last Name,Gender,Country,Age,Date,Id",
    ];

    // Act
    $reader = new SpreadsheetReaderCSV($filepath);

    // Assert
    $this->assertCount(1, $reader->sheets());
    $this->assertTrue($reader->changeSheet(0));
    $this->assertSame(1, $reader->count());
    $this->assertSame(0, $reader->key());
    $this->assertSame($expected_header_row, $reader->current());
});

it('can traverse through the CSV file', function () {
    // Arrange
    $filepath = 'tests/MockData/file_example_CSV_5000.csv';
    $expected_header_row = [
        0 => "Nr,First Name,Last Name,Gender,Country,Age,Date,Id",
    ];
    $expected_first_data_row = [
        0 => '1,Dulce,Abril,Female,United States,32,15/10/2017,1562',
    ];
    $expected_rows_count = 5001;

    // Act
    $reader = new SpreadsheetReaderCSV($filepath);

    // Assert
    $this->assertCount(1, $reader->sheets());
    $this->assertTrue($reader->changeSheet(0));
    $this->assertSame(1, $reader->count());
    $this->assertSame(0, $reader->key());
    $this->assertSame($expected_header_row, $reader->current());

    $reader->next();
    $this->assertSame(1, $reader->key());
    $this->assertSame($expected_first_data_row, $reader->current());
    $this->assertSame(2, $reader->count());

    while ($reader->valid()) {
        $reader->next();
    }

    $this->assertFalse($reader->valid());
    $this->assertSame($expected_rows_count, $reader->key());
    $this->assertSame($expected_rows_count + 1, $reader->count());
});

it('can rewind the reader', function () {
    // Arrange
    $filepath = 'tests/MockData/file_example_CSV_5000.csv';
    $expected_header_row = [
        0 => "Nr,First Name,Last Name,Gender,Country,Age,Date,Id",
    ];

    // Act
    $reader = new SpreadsheetReaderCSV($filepath);

    // Assert
    $this->assertCount(1, $reader->sheets());
    $this->assertTrue($reader->changeSheet(0));
    $this->assertSame(1, $reader->count());
    $this->assertSame(0, $reader->key());
    $this->assertSame($expected_header_row, $reader->current());

    $reader->next();
    $reader->next();
    $reader->next();
    $this->assertSame(3, $reader->key());
    $this->assertSame(4, $reader->count());

    $reader->rewind();
    $this->assertSame(1, $reader->count());
    $this->assertSame(0, $reader->key());
    $this->assertSame($expected_header_row, $reader->current());
});

it('throws an exception for non-readable file', function () {
    // Arrange
    $nonExistentFilepath = '/path/to/nonexistent/file.csv';

    // Act & Assert
    $this->expectException(FileNotReadableException::class);
    $this->expectExceptionMessage("File not readable ($nonExistentFilepath)");

    new SpreadsheetReaderCSV($nonExistentFilepath);
});
