<?php

namespace KoenVanMeijeren\SpreadsheetReader\Config;

/**
 * Provides a class for SpreadsheetReaderCSVConfigOptions.
 */
final class SpreadsheetReaderCSVConfig {

  /**
   * Constructs a new object.
   */
  public function __construct(
    public string $delimiter = '',
    public string $enclosure = '"',
  ) {}

}
