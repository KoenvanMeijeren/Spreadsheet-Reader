<?php

/**
 * @file
 */

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderFileType;

it('can create the correct file type from extensions', function () {
  $this->assertEquals(SpreadsheetReaderFileType::UNSUPPORTED, SpreadsheetReaderFileType::tryFromExtension('pdf'));
  $this->assertEquals(SpreadsheetReaderFileType::CSV, SpreadsheetReaderFileType::tryFromExtension('csv'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromExtension('xls'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromExtension('xlt'));
  $this->assertEquals(SpreadsheetReaderFileType::ODS, SpreadsheetReaderFileType::tryFromExtension('ods'));
  $this->assertEquals(SpreadsheetReaderFileType::ODS, SpreadsheetReaderFileType::tryFromExtension('odt'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromExtension('xlsx'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromExtension('xlsm'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromExtension('xltx'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromExtension('xltm'));
});

it('can create the correct file type from mime types', function () {
  $this->assertEquals(SpreadsheetReaderFileType::UNSUPPORTED, SpreadsheetReaderFileType::tryFromMimeType('application/pdf'));
  $this->assertEquals(SpreadsheetReaderFileType::CSV, SpreadsheetReaderFileType::tryFromMimeType('text/csv'));
  $this->assertEquals(SpreadsheetReaderFileType::CSV, SpreadsheetReaderFileType::tryFromMimeType('text/comma-separated-values'));
  $this->assertEquals(SpreadsheetReaderFileType::CSV, SpreadsheetReaderFileType::tryFromMimeType('text/plain'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/vnd.ms-excel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/msexcel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/x-msexcel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/x-ms-excel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/x-excel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/x-dos_ms_excel'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/xls'));
  $this->assertEquals(SpreadsheetReaderFileType::XLS, SpreadsheetReaderFileType::tryFromMimeType('application/xlt'));
  $this->assertEquals(SpreadsheetReaderFileType::ODS, SpreadsheetReaderFileType::tryFromMimeType('application/vnd.oasis.opendocument.spreadsheet'));
  $this->assertEquals(SpreadsheetReaderFileType::ODS, SpreadsheetReaderFileType::tryFromMimeType('application/vnd.oasis.opendocument.spreadsheet-template'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromMimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromMimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.template'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromMimeType('application/xlsx'));
  $this->assertEquals(SpreadsheetReaderFileType::XLSX, SpreadsheetReaderFileType::tryFromMimeType('application/xltx'));
});
