<?php

/**
 * @file
 */

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileTypeUnsupportedException;
use KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader;

it('throws an exception on unsupported file type', function () {
  // Arrange.
  $filepath = get_mock_data_filepath('file_example.pdf');

  // Act & assert.
  $this->expectException(FileTypeUnsupportedException::class);
  $this->expectExceptionMessage('File type is unsupported (tests/MockData/file_example.pdf)');

  new SpreadsheetReader($filepath);
});
