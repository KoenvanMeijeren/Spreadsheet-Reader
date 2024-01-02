<?php

namespace KoenVanMeijeren\SpreadsheetReader\Exceptions;

/**
 * Provides a class for FileNotReadableException.
 */
final class FileNotReadableException extends \Exception
{
    public function __construct(string $filepath, int $code = 0, \Throwable $previous = null)
    {
        $message = "File not readable ($filepath)";
        parent::__construct($message, $code, $previous);
    }
}