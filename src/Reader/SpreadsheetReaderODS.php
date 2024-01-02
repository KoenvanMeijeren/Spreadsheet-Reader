<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

/**
 * Spreadsheet reader for ODS files.
 *
 * @internal This class is not meant to be used directly. Use SpreadsheetReader.
 */
final class SpreadsheetReaderODS implements SpreadsheetReaderInterface {

  /**
   * Path to temporary content file.
   */
  private string $contentPath = '';

  /**
   * XML reader object.
   */
  private ?\XMLReader $content = NULL;

  /**
   * Data about separate sheets in the file.
   */
  private array $sheets = [];

  /**
   * Current active row.
   */
  private mixed $currentRow = NULL;

  /**
   * Number of the sheet we're currently reading.
   */
  private int $currentSheet = 0;

  /**
   * Index of the current row we're reading.
   */
  private int $currentRowIndex = 0;

  /**
   * Whether the table is open.
   */
  private bool $isTableOpen = FALSE;

  /**
   * Whether the row is open.
   */
  private bool $isRowOpen = FALSE;

  /**
   * Whether the file is valid.
   */
  private bool $isValid = FALSE;

  /**
   * Constructs a new spreadsheet reader for ODS files.
   */
  public function __construct(string $filepath) {
    if (!is_readable($filepath)) {
      throw new \RuntimeException('File not readable (' . $filepath . ')');
    }

    $temporaryDirectoryPath = sys_get_temp_dir();
    $temporaryDirectoryPath = rtrim($temporaryDirectoryPath, DIRECTORY_SEPARATOR);
    $temporaryDirectoryPath .= DIRECTORY_SEPARATOR . uniqid('', TRUE) . DIRECTORY_SEPARATOR;

    $zip = new \ZipArchive();
    $zipStatus = $zip->open($filepath);
    if ($zipStatus !== TRUE) {
      throw new \RuntimeException('File not readable (' . $filepath . ') (Error ' . $zipStatus . ')');
    }

    if ($zip->locateName('content.xml') !== FALSE) {
      $zip->extractTo($temporaryDirectoryPath, 'content.xml');
      $this->contentPath = $temporaryDirectoryPath . 'content.xml';
    }

    $zip->close();

    if ($this->contentPath && is_readable($this->contentPath)) {
      $this->content = new \XMLReader();
      $this->content->open($this->contentPath);
      $this->isValid = TRUE;
    }
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files)
   */
  public function __destruct() {
    if ($this->content instanceof \XMLReader) {
      $this->content->close();
      unset($this->content);
    }

    if (file_exists($this->contentPath)) {
      @unlink($this->contentPath);
      unset($this->contentPath);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sheets(): array {
    if ($this->sheets === [] && $this->isValid) {
      $sheetReader = new \XMLReader();
      $sheetReader->open($this->contentPath);

      while ($sheetReader->read()) {
        if ($sheetReader->name == 'table:table') {
          $this->sheets[] = $sheetReader->getAttribute('table:name');
          $sheetReader->next();
        }
      }

      $sheetReader->close();
    }

    return $this->sheets;
  }

  /**
   * {@inheritdoc}
   */
  public function changeSheet(int $index): bool {
    $sheets = $this->sheets();
    if (isset($sheets[$index])) {
      $this->currentSheet = $index;
      $this->rewind();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    if ($this->currentRowIndex > 0) {
      // If the worksheet was already iterated, the XML file is reopened.
      // Otherwise, it should be at the beginning anyway.
      $this->content->close();
      $this->content->open($this->contentPath);
      $this->isValid = TRUE;

      $this->isTableOpen = FALSE;
      $this->isRowOpen = FALSE;

      $this->currentRow = NULL;
    }

    $this->currentRowIndex = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    if ($this->currentRowIndex === 0 && $this->currentRow === NULL) {
      $this->next();
      $this->currentRowIndex--;
    }

    return $this->currentRow;
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    $this->currentRowIndex++;
    $this->currentRow = [];

    if (!$this->isTableOpen) {
      $tableCounter = 0;
      $shouldSkipRead = FALSE;

      while ($this->isValid = ($shouldSkipRead || $this->content->read())) {
        if ($shouldSkipRead) {
          $shouldSkipRead = FALSE;
        }

        if ($this->content->name == 'table:table' && $this->content->nodeType != \XMLReader::END_ELEMENT) {
          if ($tableCounter == $this->currentSheet) {
            $this->isTableOpen = TRUE;
            break;
          }

          $tableCounter++;
          $this->content->next();
          $shouldSkipRead = TRUE;
        }
      }
    }

    if ($this->isTableOpen && !$this->isRowOpen) {
      while ($this->isValid = $this->content->read()) {
        switch ($this->content->name) {
          case 'table:table':
            $this->isTableOpen = FALSE;
            $this->content->next('office:document-content');
            $this->isValid = FALSE;
            break 2;

          case 'table:table-row':
            if ($this->content->nodeType != \XMLReader::END_ELEMENT) {
              $this->isRowOpen = TRUE;
              break 2;
            }
            break;
        }
      }
    }

    if ($this->isRowOpen) {
      $lastCellContent = '';

      while ($this->isValid = $this->content->read()) {
        switch ($this->content->name) {
          case 'table:table-cell':
            if ($this->content->nodeType == \XMLReader::END_ELEMENT || $this->content->isEmptyElement) {
              if ($this->content->nodeType == \XMLReader::END_ELEMENT) {
                $cellValue = $lastCellContent; // phpcs:ignore
              }
              elseif ($this->content->isEmptyElement) {
                $lastCellContent = '';
                $cellValue = $lastCellContent; // phpcs:ignore
              }

              $this->currentRow[] = $lastCellContent;

              if ($this->content->getAttribute('table:number-columns-repeated') !== NULL) {
                $repeatedColumnCount = $this->content->getAttribute('table:number-columns-repeated');
                // Checking if larger than one because the value is already
                // added to the row once before.
                if ($repeatedColumnCount > 1) {
                  $this->currentRow = array_pad($this->currentRow, (count($this->currentRow) + $repeatedColumnCount - 1), $lastCellContent);
                }
              }
            }
            else {
              $lastCellContent = '';
            }
            break;

          case 'text:p':
            if ($this->content->nodeType != \XMLReader::END_ELEMENT) {
              $lastCellContent = $this->content->readString();
            }
            break;

          case 'table:table-row':
            $this->isRowOpen = FALSE;
            break 2;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->currentRowIndex;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return $this->isValid;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return ($this->currentRowIndex + 1);
  }

}
