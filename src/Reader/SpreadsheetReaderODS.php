<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\XMLContentNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\XMLWorkbookNotReadableException;

/**
 * Spreadsheet reader for ODS files.
 *
 * @internal This class is not meant to be used directly. Use SpreadsheetReader.
 */
final class SpreadsheetReaderODS implements SpreadsheetReaderInterface {

  /**
   * Path to temporary content file.
   */
  private string $contentPath;

  /**
   * XML reader object.
   */
  private \XMLReader $content;

  /**
   * Data about separate sheets in the file.
   */
  private array $sheets = [];

  /**
   * Current active row.
   */
  private array $currentRow = [];

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
  private bool $isValid;

  /**
   * Temporary directory path.
   */
  private string $tempDir;

  /**
   * Temporary files created by this class.
   */
  private array $tempFiles = [];

  /**
   * Constructs a new spreadsheet reader for ODS files.
   */
  public function __construct(string $filepath) {
    $temporaryDirectoryPath = sys_get_temp_dir();
    $temporaryDirectoryPath = rtrim($temporaryDirectoryPath, DIRECTORY_SEPARATOR);
    $temporaryDirectoryPath .= DIRECTORY_SEPARATOR . uniqid('', TRUE) . DIRECTORY_SEPARATOR;
    $this->tempDir = $temporaryDirectoryPath;

    $zip = new \ZipArchive();
    $zipStatus = $zip->open($filepath);
    if ($zipStatus !== TRUE) {
      throw new \RuntimeException('File not readable (' . $filepath . ') (Error ' . $zipStatus . ')');
    }

    if ($zip->locateName('content.xml') === FALSE) {
      throw new XMLContentNotReadableException($filepath);
    }

    $zip->extractTo($temporaryDirectoryPath, 'content.xml');
    $this->contentPath = $temporaryDirectoryPath . 'content.xml';
    $this->tempFiles[] = $this->contentPath;

    $zip->close();

    if (!is_readable($this->contentPath)) {
      throw new XMLWorkbookNotReadableException($this->contentPath);
    }

    $xml_reader = \XMLReader::open($this->contentPath);
    if (!$xml_reader) {
      throw new FileNotReadableException($this->contentPath);
    }

    $this->content = $xml_reader;
    $this->isValid = TRUE;
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files)
   */
  public function __destruct() {
    $this->content->close();
    unset($this->content, $this->contentPath);

    foreach ($this->tempFiles as $tempFile) {
      if (!file_exists($tempFile)) {
        continue;
      }

      unlink($tempFile);
    }

    if (file_exists($this->tempDir)) {
      rmdir($this->tempDir);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sheets(): array {
    if ($this->sheets !== [] || !$this->isValid) {
      return $this->sheets;
    }

    $sheetReader = \XMLReader::open($this->contentPath);
    if (!$sheetReader) {
      throw new FileNotReadableException($this->contentPath);
    }

    while ($sheetReader->read()) {
      if ($sheetReader->name === 'table:table') {
        $this->sheets[] = $sheetReader->getAttribute('table:name') ?? 'unknown';
        $sheetReader->next();
      }
    }

    $sheetReader->close();

    return $this->sheets;
  }

  /**
   * {@inheritdoc}
   */
  public function changeSheet(int $index): void {
    $sheets = $this->sheets();
    if (!isset($sheets[$index])) {
      throw new \OutOfBoundsException("SpreadsheetError: Position {$index} not found!");
    }

    $this->currentSheet = $index;
    $this->rewind();
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    if ($this->currentRowIndex < 1) {
      $this->currentRowIndex = 0;
      return;
    }

    // If the worksheet was already iterated, the XML file is reopened.
    // Otherwise, it should be at the beginning anyway.
    $this->content->close();
    $sheetReader = \XMLReader::open($this->contentPath);
    if (!$sheetReader) {
      throw new FileNotReadableException($this->contentPath);
    }

    $this->content = $sheetReader;
    $this->isValid = TRUE;

    $this->isTableOpen = FALSE;
    $this->isRowOpen = FALSE;

    $this->currentRow = [];
    $this->currentRowIndex = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): array {
    if ($this->currentRowIndex === 0 && $this->currentRow === []) {
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

    $this->tryToOpenTable();
    $this->tryToOpenRow();
    $this->tryToReadOpenRow();
  }

  /**
   * Try to open the table.
   */
  private function tryToOpenTable(): void {
    if ($this->isTableOpen) {
      return;
    }

    $tableCounter = 0;
    $shouldSkipRead = FALSE;

    while ($this->isValid = ($shouldSkipRead || $this->content->read())) {
      if ($shouldSkipRead) {
        $shouldSkipRead = FALSE;
      }

      if ($this->content->name === 'table:table' && $this->content->nodeType !== \XMLReader::END_ELEMENT) {
        if ($tableCounter === $this->currentSheet) {
          $this->isTableOpen = TRUE;
          break;
        }

        $tableCounter++;
        $this->content->next();
        $shouldSkipRead = TRUE;
      }
    }
  }

  /**
   * Try to open the row.
   */
  private function tryToOpenRow(): void {
    if (!$this->isTableOpen || $this->isRowOpen) {
      return;
    }

    while ($this->isValid = $this->content->read()) {
      switch ($this->content->name) {
        case 'table:table':
          $this->isTableOpen = FALSE;
          $this->content->next('office:document-content');
          $this->isValid = FALSE;
          break 2;

        case 'table:table-row':
          if ($this->content->nodeType !== \XMLReader::END_ELEMENT) {
            $this->isRowOpen = TRUE;
            break 2;
          }
          break;
      }
    }
  }

  /**
   * Try to read the open row.
   */
  private function tryToReadOpenRow(): void {
    if (!$this->isRowOpen) {
      return;
    }

    $lastCellContent = '';

    while ($this->isValid = $this->content->read()) {
      switch ($this->content->name) {
        case 'table:table-cell':
          if ($this->content->nodeType === \XMLReader::END_ELEMENT || $this->content->isEmptyElement) {
            if ($this->content->isEmptyElement) {
              $lastCellContent = '';
            }

            $this->currentRow[] = $lastCellContent;
          }
          else {
            $lastCellContent = '';
          }
          break;

        case 'text:p':
          if ($this->content->nodeType !== \XMLReader::END_ELEMENT) {
            $lastCellContent = $this->content->readString();
          }
          break;

        case 'table:table-row':
          $this->isRowOpen = FALSE;
          break 2;
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
    // @phpstan-ignore-next-line
    return $this->currentRowIndex + 1;
  }

}
