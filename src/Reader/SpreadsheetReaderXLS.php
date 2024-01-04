<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;

/**
 * Spreadsheet reader for XLS files.
 *
 * @internal This class is not meant to be used directly. Use SpreadsheetReader.
 */
final class SpreadsheetReaderXLS implements SpreadsheetReaderInterface {

  /**
   * File handle.
   */
  private SpreadsheetExcelReader $reader;

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

    $this->reader = new SpreadsheetExcelReader($filepath, FALSE, 'UTF-8');
    $this->reader->setUtfEncoder('mb');

    if (empty($this->reader->sheets)) {
      $this->hasError = TRUE;
      return;
    }

    $this->changeSheet(0);
  }

  /**
   * Destructs the object.
   */
  public function __destruct() {
    unset($this->reader);
  }

  /**
   * {@inheritdoc}
   */
  public function sheets(): array {
    if ($this->sheets === []) {
      $this->sheets = [];
      $this->sheetIndexes = array_keys($this->reader->sheets);

      foreach ($this->sheetIndexes as $sheetIndex) {
        $this->sheets[] = $this->reader->boundSheets[$sheetIndex]['name'];
      }
    }

    return array_values($this->sheets);
  }

  /**
   * {@inheritdoc}
   */
  public function changeSheet(int $index): void {
    $sheets = $this->sheets();
    if (!isset($sheets[$index])) {
      throw new \OutOfBoundsException("SpreadsheetError: Position {$index} not found!");
    }

    $this->rewind();
    $this->currentSheet = $this->sheetIndexes[$index];

    $columnCount = $this->reader->sheets[$this->currentSheet]['numCols'];
    $this->rowCount = $this->reader->sheets[$this->currentSheet]['numRows'];

    // For the case when the reader doesn't have the row count set correctly.
    if (!$this->rowCount && count($this->reader->sheets[$this->currentSheet]['cells'])) {
      end($this->reader->sheets[$this->currentSheet]['cells']);
      $this->rowCount = (int) key($this->reader->sheets[$this->currentSheet]['cells']);
    }

    $this->emptyRow = [];
    if ($columnCount) {
      $this->emptyRow = array_fill(1, $columnCount, '');
    }
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

    if (isset($this->reader->sheets[$this->currentSheet]['cells'][$this->index])) {
      $this->currentRow = $this->reader->sheets[$this->currentSheet]['cells'][$this->index];
      if (!$this->currentRow) {
        return;
      }

      $this->currentRow += $this->emptyRow;
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
    // @phpstan-ignore-next-line
    return $this->hasError ? 0 : $this->rowCount;
  }

}
