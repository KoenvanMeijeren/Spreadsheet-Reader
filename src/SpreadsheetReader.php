<?php

namespace KoenVanMeijeren\SpreadsheetReader;

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderCSVConfig;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;

/**
 * Main class for spreadsheet reading.
 */
class SpreadsheetReader implements \SeekableIterator, \Countable {
  public const TYPE_XLSX = 'XLSX';
  public const TYPE_XLS = 'XLS';
  public const TYPE_CSV = 'CSV';
  public const TYPE_ODS = 'ODS';

  /**
   * Handler for the file.
   */
  private SpreadsheetReaderInterface $reader;

  /**
   * Type of the contained spreadsheet.
   */
  private ?string $fileType = NULL;

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
  public function __construct(string $filepath, ?string $originalFilename = NULL, ?string $mimeType = NULL) {
    if (!is_readable($filepath)) {
      throw new FileNotReadableException($filepath);
    }

    $this->determineAndSetFileType($filepath, $originalFilename, $mimeType);

    $this->reader = match ($this->fileType) {
      self::TYPE_XLSX => new SpreadsheetReaderXLSX($filepath),
      self::TYPE_CSV => new SpreadsheetReaderCSV($filepath, new SpreadsheetReaderCSVConfig()),
      self::TYPE_XLS => new SpreadsheetReaderXLS($filepath),
      self::TYPE_ODS => new SpreadsheetReaderODS($filepath),
      default => throw new \RuntimeException('No handler available for the given type: ' . $this->fileType),
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
  private function determineAndSetFileType(string $filepath, ?string $originalFilename, ?string $mimeType): void {
    if (!$originalFilename) {
      $originalFilename = $filepath;
    }

    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    switch ($mimeType) {
      case 'text/csv':
      case 'text/comma-separated-values':
      case 'text/plain':
        $this->fileType = self::TYPE_CSV;
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
        $this->fileType = self::TYPE_XLS;
        if (in_array($fileExtension, ['csv', 'tsv', 'txt'], TRUE)) {
          $this->fileType = self::TYPE_CSV;
        }
        break;

      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.oasis.opendocument.spreadsheet-template':
        $this->fileType = self::TYPE_ODS;
        break;

      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template':
      case 'application/xlsx':
      case 'application/xltx':
        $this->fileType = self::TYPE_XLSX;
        break;

      case 'application/xml':
        // Excel 2004 xml format uses this.
        break;
    }

    if (!$this->fileType) {
      $this->fileType = match ($fileExtension) {
        'xlsx', 'xltx', 'xlsm', 'xltm' => self::TYPE_XLSX,
        'xls', 'xlt' => self::TYPE_XLS,
        'ods', 'odt' => self::TYPE_ODS,
        default => self::TYPE_CSV,
      };
    }

    // Pre-checking XLS files, in case they are renamed CSV or XLSX files.
    if ($this->fileType === self::TYPE_XLS) {
      $this->reader = new SpreadsheetReaderXLS($filepath);
      if (!$this->reader->valid()) {
        $this->reader->__destruct();

        $zip = new \ZipArchive();
        $zip_file = $zip->open($filepath);
        if (is_resource($zip_file)) {
          $this->fileType = self::TYPE_XLSX;
          $zip->close();
        }
        else {
          $this->fileType = self::TYPE_CSV;
        }
      }
    }
  }

  /**
   * Gets information about separate sheets in the given file.
   *
   * @return array
   *   Associative array where key is sheet index and value is sheet name.
   */
  public function sheets(): array {
    return $this->reader->sheets();
  }

  /**
   * Changes the current sheet to another from the file.
   *
   * Note that changing the sheet will rewind the file to the beginning, even if
   * the current sheet index is provided.
   *
   * @return bool
   *   True if sheet could be changed to the specified one,
   *   false if not (for example, if incorrect index was provided).
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
    $currentIndex = $this->reader->key();
    if ($currentIndex !== $offset) {
      if ($offset < $currentIndex || $currentIndex === NULL || $offset === 0) {
        $this->rewind();
      }

      while ($this->reader->valid() && ($offset > $this->reader->key())) {
        $this->reader->next();
      }

      if (!$this->reader->valid()) {
        throw new \OutOfBoundsException('SpreadsheetError: Position ' . $offset . ' not found');
      }
    }
  }

}
