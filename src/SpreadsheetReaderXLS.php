<?php

namespace KoenVanMeijeren\SpreadsheetReader;

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;

/**
 * Class for parsing XLS files.
 */
class SpreadsheetReaderXLS implements SpreadsheetReaderInterface {

  /**
   * File handle.
   */
  private mixed $inputFile;

  /**
   * Current row index.
   */
  private int $index = 0;

  /**
   * Whether the file has an error.
   */
  private bool $hasError = FALSE;

  /**
   * Sheet information.
   */
  private array $sheets = [];

  /**
   * Sheet indexes.
   */
  private array $sheetIndexes = [];

  /**
   * Current sheet index.
   */
  private int $currentSheet = 0;

  /**
   * Content of the current row.
   */
  private array $currentRow = [];

  /**
   * Row count in the sheet.
   */
  private int $rowCount = 0;

  /**
   * Template to use for empty rows.
   *
   * Retrieved rows are merged with this so that empty cells are added, too.
   */
  private array $emptyRow = [];

  /**
   * Constructs a new object.
   */
  public function __construct(string $filepath) {
    if (!is_readable($filepath)) {
      throw new FileNotReadableException($filepath);
    }

    if (!class_exists('Spreadsheet_Excel_Reader')) {
      throw new \RuntimeException('Spreadsheet_Excel_Reader class not available');
    }

    $this->inputFile = new \Spreadsheet_Excel_Reader($filepath, FALSE, 'UTF-8');

    if (function_exists('mb_convert_encoding')) {
      $this->inputFile->setUTFEncoder('mb');
    }

    if (empty($this->inputFile->sheets)) {
      $this->hasError = TRUE;
      return;
    }

    $this->changeSheet(0);
  }

  /**
   * Destructs the object.
   */
  public function __destruct() {
    unset($this->inputFile);
  }

  /**
   * {@inheritdoc}
   */
  public function sheets(): array {
    if ($this->sheets === []) {
      $this->sheets = [];
      $this->sheetIndexes = array_keys($this->inputFile->sheets);

      foreach ($this->sheetIndexes as $sheetIndex) {
        $this->sheets[] = $this->inputFile->boundsheets[$sheetIndex]['name'];
      }
    }

    return $this->sheets;
  }

  /**
   * {@inheritdoc}
   */
  public function changeSheet(int $index): bool {
    $sheets = $this->sheets(); // phpcs:ignore

    if (isset($this->sheets[$index])) {
      $this->rewind();
      $this->currentSheet = $this->sheetIndexes[$index];

      $columnCount = $this->inputFile->sheets[$this->currentSheet]['numCols'];
      $this->rowCount = $this->inputFile->sheets[$this->currentSheet]['numRows'];

      // For the case when the reader doesn't have the row count set correctly.
      if (!$this->rowCount && count($this->inputFile->sheets[$this->currentSheet]['cells'])) {
        end($this->inputFile->sheets[$this->currentSheet]['cells']);
        $this->rowCount = (int) key($this->inputFile->sheets[$this->currentSheet]['cells']);
      }

      $this->emptyRow = [];
      if ($columnCount) {
        $this->emptyRow = array_fill(1, $columnCount, '');
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->index = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): array {
    if ($this->index === 0) {
      $this->next();
    }

    return $this->currentRow;
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    // Internal counter is advanced here instead of if because apparently
    // it's fully possible that an empty row will not be present at all.
    $this->index++;

    if ($this->hasError) {
      return;
    }

    if (isset($this->inputFile->sheets[$this->currentSheet]['cells'][$this->index])) {
      $this->currentRow = $this->inputFile->sheets[$this->currentSheet]['cells'][$this->index];
      if (!$this->currentRow) {
        return;
      }

      $this->currentRow = ($this->currentRow + $this->emptyRow);
      ksort($this->currentRow);

      $this->currentRow = array_values($this->currentRow);
      return;
    }

    $this->currentRow = $this->emptyRow;
  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    if ($this->hasError) {
      return FALSE;
    }

    return ($this->index <= $this->rowCount);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return $this->hasError ? 0 : $this->rowCount;
  }

}
