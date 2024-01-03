<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for FileNotReadableException.
 */
final class ZipfileNotReadableException extends \Exception {

  /**
   * Constructs a FileNotReadableException object.
   */
  public function __construct(string $filepath, int $zipfileStatus, int $code = 0, \Throwable $previous = NULL) {
    $message = "Zipfile not readable ($filepath). Error code: {$zipfileStatus}";
    parent::__construct($message, $code, $previous);

  }

}
