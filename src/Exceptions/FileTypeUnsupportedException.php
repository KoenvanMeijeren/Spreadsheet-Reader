<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for FileNotReadableException.
 */
final class FileTypeUnsupportedException extends \Exception {

  /**
   * Constructs a FileNotReadableException object.
   */
  public function __construct(string $filetype, int $code = 0, \Throwable $previous = NULL) {
    $message = "File type is unsupported ({$filetype})";
    parent::__construct($message, $code, $previous);

  }

}
