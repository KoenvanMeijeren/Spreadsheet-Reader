<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for XMLContentNotReadableException.
 */
final class XMLContentNotReadableException extends \Exception {

  /**
   * Constructs a new object.
   */
  public function __construct(string $filepath, int $code = 0, \Throwable $previous = NULL) {
    $message = "XML Content not readable ($filepath)";
    parent::__construct($message, $code, $previous);

  }

}
