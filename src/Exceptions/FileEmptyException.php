<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for FileNotReadableException.
 */
final class FileEmptyException extends \Exception {

  /**
   * Constructs a FileNotReadableException object.
   */
  public function __construct(string $filepath, int $code = 0, \Throwable $previous = NULL) {
    $message = "File is empty ({$filepath})";
    parent::__construct($message, $code, $previous);

  }

}
