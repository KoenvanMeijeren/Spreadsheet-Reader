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
}
