<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderCSVConfig;
use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderFileType;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\ChangeSheetIsNotSupportedException;
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
    $this->config = $config;

    if (!is_readable($filepath)) {
      throw new FileNotReadableException($filepath);
    }

    $handle = fopen($filepath, 'rb');
    if (!$handle) {
      throw new FileNotReadableException($filepath);
    }

    $this->handle = $handle;

    // Checking the file for byte-order mark to determine encoding.
    $this->determineFileEncoding();

    // Seeking the place right after BOM as the start of the real content.
    if ($this->bomLength) {
      fseek($this->handle, $this->bomLength);
    }

    $is_empty = feof($this->handle) && (trim((string) fread($this->handle, 1)) === '');
    if ($is_empty) {
      throw new FileEmptyException($filepath);
    }

    $this->determineDelimiterIfNeeded();
  }

  /**
   * Tries to determine the encoding of the file.
   */
  private function determineFileEncoding(): void {
    $bom16 = bin2hex((string) fread($this->handle, 2));
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
      $bom32 = bin2hex((string) fread($this->handle, 4));
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
    $bom8 = bin2hex((string) fread($this->handle, 3));
    if ($bom8 === 'efbbbf') {
      $this->encoding = 'UTF-8';
      $this->bomLength = 3;
    }
  }

  /**
   * Tries to determine the delimiter if it should be determined automatically.
   */
  private function determineDelimiterIfNeeded(): void {
    if (!empty($this->config->delimiter)) {
      return;
    }

    // Fgetcsv needs single-byte separators.
    $semicolon = ';';
    $tab = "\t";
    $comma = ',';

    // Reading the first row and checking if a specific separator character
    // has more columns than others (it means that most likely that is the
    // delimiter).
    $semicolonCount = count((array) fgetcsv($this->handle, NULL, $semicolon));
    fseek($this->handle, $this->bomLength);
    $tabCount = count((array) fgetcsv($this->handle, NULL, $tab));
    fseek($this->handle, $this->bomLength);
    $commaCount = count((array) fgetcsv($this->handle, NULL, $comma));
    fseek($this->handle, $this->bomLength);

    $delimiter = $semicolon;
    if ($tabCount > $semicolonCount || $commaCount > $semicolonCount) {
      $delimiter = $commaCount > $tabCount ? $comma : $tab;
    }

    $this->config->delimiter = $delimiter;
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files)
   */
  public function __destruct() {
    fclose($this->handle);
    unset($this->handle);
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
  public function changeSheet(int $index): void {
    throw new ChangeSheetIsNotSupportedException(SpreadsheetReaderFileType::CSV->value);
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
    $this->handleUtf16Encoding();

    $this->currentRowIndex++;
    $this->currentRow = fgetcsv($this->handle, NULL, $this->config->delimiter, $this->config->enclosure);

    $this->convertAndTrimMultibyteStrings();
  }

  /**
   * Handles UTF-16 encoding.
   */
  private function handleUtf16Encoding(): void {
    if (!$this->isUtf16Encoding()) {
      return;
    }

    while (!feof($this->handle)) {
      $character = ord((string) fgetc($this->handle));

      if ($character !== 10 && $character !== 13) {
        fseek($this->handle, ((int) ftell($this->handle)) - ($this->encoding === 'UTF-16LE' ? 1 : 2));
        break;
      }
    }
  }

  /**
   * Checks if the encoding is UTF-16.
   */
  private function isUtf16Encoding(): bool {
    return $this->encoding === 'UTF-16LE' || $this->encoding === 'UTF-16BE';
  }

  /**
   * Converts and trims multibyte strings.
   */
  private function convertAndTrimMultibyteStrings(): void {
    if ($this->currentRow && !$this->isUtf8OrAsciiEncoding()) {
      foreach ($this->currentRow as $key => $value) {
        $this->currentRow[$key] = $this->convertAndTrimValue($value);
      }
    }
  }

  /**
   * Checks if the encoding is UTF-8 or ASCII.
   */
  private function isUtf8OrAsciiEncoding(): bool {
    return $this->encoding === 'ASCII' || $this->encoding === 'UTF-8';
  }

  /**
   * Converts and trims the value.
   */
  private function convertAndTrimValue(string $value): string {
    return trim(trim(
      mb_convert_encoding($value, 'UTF-8', $this->encoding),
      $this->config->enclosure
    ));
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
    // @phpstan-ignore-next-line
    return ($this->currentRowIndex + 1);
  }

}
