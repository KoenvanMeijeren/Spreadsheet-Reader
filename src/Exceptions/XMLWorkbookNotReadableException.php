<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for XMLWorkbookNotReadableException.
 */
final class XMLWorkbookNotReadableException extends \Exception {

  /**
   * Constructs a new object.
   */
  public function __construct(string $filepath, int $code = 0, \Throwable $previous = NULL) {
    $message = "XML Workbook not readable ($filepath)";
    parent::__construct($message, $code, $previous);

  }

}
