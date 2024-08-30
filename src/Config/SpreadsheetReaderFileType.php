<?php

namespace KoenVanMeijeren\SpreadsheetReader\Config;

/**
 * The supported types for the spreadsheet reader.
 */
enum SpreadsheetReaderFileType: string {
  case XLSX = 'XLSX';
  case XLS = 'XLS';
  case CSV = 'CSV';
  case ODS = 'ODS';
  case UNSUPPORTED = 'UNSUPPORTED';

  /**
   * Creates a file type from the MIME type.
   */
  public static function tryFromMimeType(?string $mimeType): self {
    return match ($mimeType) {
      'text/csv', 'text/comma-separated-values', 'text/plain' => self::CSV,
      'application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel',
      'application/x-ms-excel', 'application/x-excel', 'application/x-dos_ms_excel',
      'application/xls', 'application/xlt', 'application/x-xls' => self::XLS,
      'application/vnd.oasis.opendocument.spreadsheet',
      'application/vnd.oasis.opendocument.spreadsheet-template' => self::ODS,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
      'application/xlsx', 'application/xltx' => self::XLSX,
      default => self::UNSUPPORTED,
    };
  }

  /**
   * Creates a file type from the file extension.
   */
  public static function tryFromExtension(string $fileExtension): self {
    return match ($fileExtension) {
      'xlsx', 'xltx', 'xlsm', 'xltm' => self::XLSX,
      'xls', 'xlt' => self::XLS,
      'ods', 'odt' => self::ODS,
      'csv' => self::CSV,
      default => self::UNSUPPORTED,
    };
  }

}
