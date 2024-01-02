<?php

namespace KoenVanMeijeren\SpreadsheetReader;

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderCSVConfig;
use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderFileType;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderCSV;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderInterface;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderODS;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderXLS;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderXLSX;

/**
 * Main class for spreadsheet reading.
 */
class SpreadsheetReader implements \SeekableIterator, SpreadsheetReaderInterface {

  /**
   * Handler for the file.
   */
  private SpreadsheetReaderInterface $reader;

  /**
   * Constructs the spreadsheet reader.
   *
   * @param string $filepath
   *   Path to file.
   * @param string|null $originalFilename
   *   Filename (in case of an uploaded file), used to determine file type.
   * @param string|null $mimeType
   *   MIME type from an upload, used to determine file type, optional.
   *
   * @throws \KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException
   */
  public function __construct(string $filepath, ?string $originalFilename = NULL, ?string $mimeType = NULL, ?SpreadsheetReaderInterface $reader = NULL) {
    // If a reader is passed, use that one. And skip the rest.
    if ($reader) {
      $this->reader = $reader;
      return;
    }

    if (!is_readable($filepath)) {
      throw new FileNotReadableException($filepath);
    }

    $fileType = $this->getFileType($filepath, $originalFilename, $mimeType);
    $this->reader = match ($fileType) {
      SpreadsheetReaderFileType::XLSX => new SpreadsheetReaderXLSX($filepath),
      SpreadsheetReaderFileType::CSV => new SpreadsheetReaderCSV($filepath, new SpreadsheetReaderCSVConfig()),
      SpreadsheetReaderFileType::XLS => new SpreadsheetReaderXLS($filepath),
      SpreadsheetReaderFileType::ODS => new SpreadsheetReaderODS($filepath),
    };
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files).
   */
  public function __destruct() {
    unset($this->reader, $this->fileType);
  }

  /**
   * Determines the type of the file and sets it.
   */
  private function getFileType(string $filepath, ?string $originalFilename, ?string $mimeType): SpreadsheetReaderFileType {
    if (!$originalFilename) {
      $originalFilename = $filepath;
    }

    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    $fileType = NULL;
    switch ($mimeType) {
      case 'text/csv':
      case 'text/comma-separated-values':
      case 'text/plain':
        $fileType = SpreadsheetReaderFileType::CSV;
        break;

      case 'application/vnd.ms-excel':
      case 'application/msexcel':
      case 'application/x-msexcel':
      case 'application/x-ms-excel':
      case 'application/x-excel':
      case 'application/x-dos_ms_excel':
      case 'application/xls':
      case 'application/xlt':
      case 'application/x-xls':
        // Excel does weird stuff.
        $fileType = SpreadsheetReaderFileType::XLS;
        if (in_array($fileExtension, ['csv', 'tsv', 'txt'], TRUE)) {
          $fileType = SpreadsheetReaderFileType::CSV;
        }
        break;

      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.oasis.opendocument.spreadsheet-template':
        $fileType = SpreadsheetReaderFileType::ODS;
        break;

      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template':
      case 'application/xlsx':
      case 'application/xltx':
        $fileType = SpreadsheetReaderFileType::XLSX;
        break;

      case 'application/xml':
        // Excel 2004 xml format uses this.
        break;
    }

    if (!$fileType) {
      $fileType = match ($fileExtension) {
        'xlsx', 'xltx', 'xlsm', 'xltm' => SpreadsheetReaderFileType::XLSX,
        'xls', 'xlt' => SpreadsheetReaderFileType::XLS,
        'ods', 'odt' => SpreadsheetReaderFileType::ODS,
        default => SpreadsheetReaderFileType::CSV,
      };
    }

    // Pre-checking XLS files, in case they are renamed CSV or XLS  X files.
    if ($fileType === SpreadsheetReaderFileType::XLS) {
      $this->reader = new SpreadsheetReaderXLS($filepath);
      if (!$this->reader->valid()) {
        $this->reader->__destruct();

        $zip = new \ZipArchive();
        $zip_file = $zip->open($filepath);

        $fileType = SpreadsheetReaderFileType::CSV;
        if (is_resource($zip_file)) {
          $fileType = SpreadsheetReaderFileType::XLSX;
        }

        $zip->close();
      }
    }

    return $fileType;
  }

  /**
   * {@inheritdoc}
   */
  public function sheets(): array {
    return $this->reader->sheets();
  }

  /**
   * {@inheritDoc}
   */
  public function changeSheet(int $index): bool {
    return $this->reader->changeSheet($index);
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->reader->rewind();
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    return $this->reader->current();

  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    $this->reader->next();
  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->reader->key();

  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return $this->reader->valid();
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return $this->reader->count();
  }

  /**
   * {@inheritdoc}
   */
  public function seek(int $offset): void {
    $currentIndex = $this->key();

    // Current key is already the one we're looking for. So we can safely stop.
    if ($currentIndex === $offset) {
      return;
    }

    if ($offset < $currentIndex || $offset === 0) {
      $this->rewind();
    }

    while ($this->valid() && ($offset > $this->key())) {
      $this->next();
    }

    if (!$this->valid()) {
      throw new \OutOfBoundsException('SpreadsheetError: Position ' . $offset . ' not found');
    }
  }

}
