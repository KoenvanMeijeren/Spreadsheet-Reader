<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for FileNotReadableException.
 */
final class FileNotReadableException extends \Exception {

  /**
   * Constructs a FileNotReadableException object.
   */
  public function __construct(string $filepath, int $code = 0, \Throwable $previous = NULL) {
    $message = "File not readable ($filepath)";
    parent::__construct($message, $code, $previous);

  }

}
