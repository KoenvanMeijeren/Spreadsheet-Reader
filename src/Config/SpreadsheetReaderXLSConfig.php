<?php

namespace KoenVanMeijeren\SpreadsheetReader\Config;

/**
 * Provides a class for SpreadsheetReaderXLSConfig.
 */
final class SpreadsheetReaderXLSConfig {

  /**
   * Constructs a new object.
   */
  public function __construct(
    public bool $shouldStoreExtendedInfo = TRUE,
    public string $outputEncoding = '"',
  ) {}

}
