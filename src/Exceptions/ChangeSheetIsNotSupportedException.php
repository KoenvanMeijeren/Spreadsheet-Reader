<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides an exception.
 */
final class ChangeSheetIsNotSupportedException extends \Exception {

  /**
   * Constructs a new object.
   */
  public function __construct(string $filetype, int $code = 0, \Throwable $previous = NULL) {
    $message = "Change sheet is not supported for this file type ($filetype)";
    parent::__construct($message, $code, $previous);
  }

}
