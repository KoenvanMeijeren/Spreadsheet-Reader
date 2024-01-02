<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderCSVConfig;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileEmptyException;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;

/**
 * Spreadsheet reader for CSV files.
 *
 * @internal This class is not meant to be used directly. Use SpreadsheetReader.
 */
final class SpreadsheetReaderCSV implements SpreadsheetReaderInterface {

  /**
   * Options array, pre-populated with the default values.
   */
  private SpreadsheetReaderCSVConfig $config;

  /**
   * Encoding of the file.
   */
  private string $encoding = 'UTF-8';

  /**
   * Length of the byte-order mark in the beginning of the file.
   */
  private int $bomLength = 0;

  /**
   * File handle.
   *
   * @var resource
   */
  private mixed $handle;

  /**
   * Path to file.
   */
  private string $filepath;

  /**
   * Current row index.
   */
  private int $currentRowIndex = 0;

  /**
   * Current row.
   */
  private mixed $currentRow = NULL;

  /**
   * Constructs a new spreadsheet reader for CSV files.
   *
   * @throws \KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException
   */
  public function __construct(string $filepath, SpreadsheetReaderCSVConfig $config) {
    $this->filepath = $filepath;

    if (!is_readable($filepath)) {
      throw new FileNotReadableException($filepath);
    }

    $this->config = $config;
    $this->handle = fopen($filepath, 'rb');

    // Checking the file for byte-order mark to determine encoding.
    $bom16 = bin2hex(fread($this->handle, 2));
    if ($bom16 === 'fffe') {
      $this->encoding = 'UTF-16LE';
      // $this -> Encoding = 'UTF-16';
      $this->bomLength = 2;
    }
    elseif ($bom16 === 'feff') {
      $this->encoding = 'UTF-16BE';
      // $this -> Encoding = 'UTF-16';
      $this->bomLength = 2;
    }

    if (!$this->bomLength) {
      fseek($this->handle, 0);
      $bom32 = bin2hex(fread($this->handle, 4));
      if ($bom32 === '0000feff') {
        // $this -> Encoding = 'UTF-32BE';
        $this->encoding = 'UTF-32';
        $this->bomLength = 4;
      }
      elseif ($bom32 === 'fffe0000') {
        // $this -> Encoding = 'UTF-32LE';
        $this->encoding = 'UTF-32';
        $this->bomLength = 4;
      }
    }

    fseek($this->handle, 0);
    $bom8 = bin2hex(fread($this->handle, 3));
    if ($bom8 === 'efbbbf') {
      $this->encoding = 'UTF-8';
      $this->bomLength = 3;
    }

    // Seeking the place right after BOM as the start of the real content.
    if ($this->bomLength) {
      fseek($this->handle, $this->bomLength);
    }

    $is_empty = feof($this->handle) && (trim(fread($this->handle, 1)) == '');
    if ($is_empty) {
      throw new FileEmptyException($filepath);
    }

    // Checking for the delimiter if it should be determined automatically.
    if (empty($this->config->delimiter)) {
      // Fgetcsv needs single-byte separators.
      $semicolon = ';';
      $tab = "\t";
      $comma = ',';

      // Reading the first row and checking if a specific separator character
      // has more columns than others (it means that most likely that is the
      // delimiter).
      $semicolonCount = count(fgetcsv($this->handle, NULL, $semicolon));
      fseek($this->handle, $this->bomLength);
      $tabCount = count(fgetcsv($this->handle, NULL, $tab));
      fseek($this->handle, $this->bomLength);
      $commaCount = count(fgetcsv($this->handle, NULL, $comma));
      fseek($this->handle, $this->bomLength);

      $delimiter = $semicolon;
      if ($tabCount > $semicolonCount || $commaCount > $semicolonCount) {
        $delimiter = $commaCount > $tabCount ? $comma : $tab;
      }

      $this->config->delimiter = $delimiter;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function sheets(): array {
    return [0 => basename($this->filepath)];
  }

  /**
   * {@inheritDoc}
   */
  public function changeSheet(int $index): bool {
    if ($index === 0) {
      $this->rewind();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function rewind(): void {
    fseek($this->handle, $this->bomLength);
    $this->currentRow = NULL;
    $this->currentRowIndex = 0;
  }

  /**
   * {@inheritDoc}
   */
  public function current(): mixed {
    if ($this->currentRowIndex === 0 && $this->currentRow === NULL) {
      $this->next();
      $this->currentRowIndex--;
    }

    return $this->currentRow;
  }

  /**
   * {@inheritDoc}
   */
  public function next(): void {
    $this->currentRow = [];

    // Finding the place the next line starts for UTF-16 encoded files.
    // Line breaks could be 0x0D 0x00 0x0A 0x00 and PHP could split lines on the
    // first or the second linebreak, leaving unnecessary \0 characters that
    // mess up the output.
    if ($this->encoding === 'UTF-16LE' || $this->encoding === 'UTF-16BE') {
      while (!feof($this->handle)) {
        // While bytes are insignificant whitespace, do nothing.
        $character = ord(fgetc($this->handle));
        if ($character === 10 || $character === 13) {
          continue;
        }

        // If significant bytes are found, go back to the last place before it.
        if ($this->encoding === 'UTF-16LE') {
          fseek($this->handle, (ftell($this->handle) - 1));
        }
        else {
          fseek($this->handle, (ftell($this->handle) - 2));
        }

        break;
      }
    }

    $this->currentRowIndex++;
    $this->currentRow = fgetcsv($this->handle, NULL, $this->config->delimiter, $this->config->enclosure);

    // Converting multibyte unicode strings and trimming enclosure symbols off
    // of them because those aren't recognized in the relevant encodings.
    if ($this->currentRow && $this->encoding !== 'ASCII' && $this->encoding !== 'UTF-8') {
      foreach ($this->currentRow as $key => $value) {
        $this->currentRow[$key] = trim(trim(
          mb_convert_encoding($value, 'UTF-8', $this->encoding),
          $this->config->enclosure
        ));
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function key(): int {
    return $this->currentRowIndex;
  }

  /**
   * {@inheritDoc}
   */
  public function valid(): bool {
    return (!empty($this->currentRow) || !feof($this->handle));
  }

  /**
   * {@inheritDoc}
   */
  public function count(): int {
    return ($this->currentRowIndex + 1);
  }

}
