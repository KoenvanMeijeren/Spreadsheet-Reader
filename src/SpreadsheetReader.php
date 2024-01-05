<?php

namespace KoenVanMeijeren\SpreadsheetReader;

use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderCSVConfig;
use KoenVanMeijeren\SpreadsheetReader\Config\SpreadsheetReaderFileType;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileTypeUnsupportedException;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderCSV;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderInterface;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderODS;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderXLS;
use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderXLSX;

/**
 * Main class for spreadsheet reading.
 */
final class SpreadsheetReader implements \SeekableIterator, SpreadsheetReaderInterface {

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
   * @param \KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderInterface|null $reader
   *   Optional reader to use.
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
      SpreadsheetReaderFileType::XLS => new SpreadsheetReaderXLS($filepath),
      SpreadsheetReaderFileType::ODS => new SpreadsheetReaderODS($filepath),
      SpreadsheetReaderFileType::CSV => new SpreadsheetReaderCSV($filepath, new SpreadsheetReaderCSVConfig()),
      default => throw new FileTypeUnsupportedException($mimeType ?? $filepath),
    };
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files).
   */
  public function __destruct() {
    unset($this->reader);
  }

  /**
   * Determines the type of the file and returns it.
   */
  private function getFileType(string $filepath, ?string $originalFilename, ?string $mimeType): SpreadsheetReaderFileType {
    $originalFilename ??= $filepath;
    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

    $fileType = $this->getFileTypeByMimeType($mimeType, $fileExtension);
    if (!$fileType) {
      $fileType = $this->getFileTypeByExtension($fileExtension);
    }

    return $fileType;
  }

  /**
   * Gets the file type by mime type.
   */
  private function getFileTypeByMimeType(?string $mimeType, string $fileExtension): ?SpreadsheetReaderFileType {
    return match ($mimeType) {
      'text/csv', 'text/comma-separated-values', 'text/plain' => SpreadsheetReaderFileType::CSV,
      'application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel',
      'application/x-ms-excel', 'application/x-excel', 'application/x-dos_ms_excel',
      'application/xls', 'application/xlt', 'application/x-xls' => SpreadsheetReaderFileType::XLS,
      'application/vnd.oasis.opendocument.spreadsheet',
      'application/vnd.oasis.opendocument.spreadsheet-template' => SpreadsheetReaderFileType::ODS,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
      'application/xlsx', 'application/xltx' => SpreadsheetReaderFileType::XLSX,
      default => NULL,
    };
  }

  /**
   * Gets the file type by extension.
   */
  private function getFileTypeByExtension(string $fileExtension): SpreadsheetReaderFileType {
    return match ($fileExtension) {
      'xlsx', 'xltx', 'xlsm', 'xltm' => SpreadsheetReaderFileType::XLSX,
      'xls', 'xlt' => SpreadsheetReaderFileType::XLS,
      'ods', 'odt' => SpreadsheetReaderFileType::ODS,
      'csv' => SpreadsheetReaderFileType::CSV,
      default => SpreadsheetReaderFileType::UNSUPPORTED,
    };
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
  public function changeSheet(int $index): void {
    $this->reader->changeSheet($index);
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
    return (int) $this->reader->key();
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
