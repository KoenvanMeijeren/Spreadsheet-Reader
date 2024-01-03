<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for XMLWorkbookNotReadableException.
 */
final class XMLSharedStringsNotReadableException extends \Exception {

  /**
   * Constructs a new object.
   */
  public function __construct(string $filepath, int $code = 0, \Throwable $previous = NULL) {
    $message = "XML Shared strings not readable ($filepath)";
    parent::__construct($message, $code, $previous);

  }

}
