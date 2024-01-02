<?php

namespace KoenVanMeijeren\SpreadsheetReader;

/**
 * Main class for spreadsheet reading.
 */
class SpreadsheetReader implements \SeekableIterator, \Countable {
  public const TYPE_XLSX = 'XLSX';
  public const TYPE_XLS = 'XLS';
  public const TYPE_CSV = 'CSV';
  public const TYPE_ODS = 'ODS';

  /**
   * Options for CSV files.
   */
  private array $options = [
    'Delimiter' => '',
    'Enclosure' => '"',
  ];

  /**
   * Current row in the file.
   */
  private int $index = 0;

  /**
   * Handler for the file.
   */
  private ?SpreadsheetReaderInterface $handler = NULL;

  /**
   * Type of the contained spreadsheet.
   */
  private ?string $type = NULL;

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
      throw new \RuntimeException('KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader: File (' . $filepath . ') not readable');
    }

    $defaultTimezone = date_default_timezone_get();
    if ($defaultTimezone) {
      date_default_timezone_set($defaultTimezone);
    }

    // Checking the other parameters for correctness
    // This should be a check for string, but we're lenient.
    if (!empty($originalFilename) && !is_scalar($originalFilename)) {
      throw new \RuntimeException('KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader: Original file (2nd parameter) path is not a string or a scalar value.');
    }

    if (!empty($mimeType) && !is_scalar($mimeType)) {
      throw new \RuntimeException('KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader: Mime type (3nd parameter) path is not a string or a scalar value.');
    }

    // 1. Determine type
    if (!$originalFilename) {
      $originalFilename = $filepath;
    }

    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    switch ($mimeType) {
      case 'text/csv':
      case 'text/comma-separated-values':
      case 'text/plain':
        $this->type = self::TYPE_CSV;
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
        if (in_array($fileExtension, ['csv', 'tsv', 'txt'], TRUE)) {
          $this->type = self::TYPE_CSV;
        }
        else {
          $this->type = self::TYPE_XLS;
        }
        break;

      case 'application/vnd.oasis.opendocument.spreadsheet':
      case 'application/vnd.oasis.opendocument.spreadsheet-template':
        $this->type = self::TYPE_ODS;
        break;

      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
      case 'application/vnd.openxmlformats-officedocument.spreadsheetml.template':
      case 'application/xlsx':
      case 'application/xltx':
        $this->type = self::TYPE_XLSX;
        break;

      case 'application/xml':
        // Excel 2004 xml format uses this.
        break;
    }

    if (!$this->type) {
      $this->type = match ($fileExtension) {
        'xlsx', 'xltx', 'xlsm', 'xltm' => self::TYPE_XLSX,
                'xls', 'xlt' => self::TYPE_XLS,
                'ods', 'odt' => self::TYPE_ODS,
                default => self::TYPE_CSV,
      };
    }

    // Pre-checking XLS files, in case they are renamed CSV or XLSX files.
    if ($this->type == self::TYPE_XLS) {
      $this->handler = new SpreadsheetReaderXLS($filepath);
      if (!$this->handler->valid()) {
        $this->handler->__destruct();

        $zip = new \ZipArchive();
        $zip_file = $zip->open($filepath);
        if (is_resource($zip_file)) {
          $this->type = self::TYPE_XLSX;
          $zip->close();
        }
        else {
          $this->type = self::TYPE_CSV;
        }
      }
    }

    // 2. Create handler
    switch ($this->type) {
      case self::TYPE_XLSX:
        $this->handler = new SpreadsheetReaderXLSX($filepath);
        break;

      case self::TYPE_CSV:
        $this->handler = new SpreadsheetReaderCSV($filepath, $this->options);
        break;

      case self::TYPE_XLS:
        // Everything already happens above.
        break;

      case self::TYPE_ODS:
        $this->handler = new SpreadsheetReaderODS($filepath, $this->options);
        break;

      default:
        throw new \RuntimeException('No handler available for the given type: ' . $this->type);
    }

  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files).
   */
  public function __destruct() {
    unset($this->options, $this->index, $this->handler, $this->type);
  }

  /**
   * Gets information about separate sheets in the given file.
   *
   * @return array
   *   Associative array where key is sheet index and value is sheet name.
   */
  public function sheets(): array {
    return $this->handler->sheets();
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
    return $this->handler->changeSheet($index);
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->index = 0;
    $this->handler?->rewind();
  }

  /**
   * {@inheritdoc}
   */
  public function current(): mixed {
    return $this->handler?->current();

  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    if (!$this->handler) {
      return;
    }

    $this->index++;
    $this->handler->next();

  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->handler?->key();

  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return $this->handler?->valid();

  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return (int) $this->handler?->count();

  }

  /**
   * {@inheritdoc}
   */
  public function seek(int $offset): void {
    if (!$this->handler) {
      throw new \OutOfBoundsException('KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader: No file opened');
    }

    $currentIndex = $this->handler->key();
    if ($currentIndex !== $offset) {
      if ($offset < $currentIndex || $currentIndex === NULL || $offset === 0) {
        $this->rewind();
      }

      while ($this->handler->valid() && ($offset > $this->handler->key())) {
        $this->handler->next();
      }

      if (!$this->handler->valid()) {
        throw new \OutOfBoundsException('SpreadsheetError: Position ' . $offset . ' not found');
      }
    }

  }

}
