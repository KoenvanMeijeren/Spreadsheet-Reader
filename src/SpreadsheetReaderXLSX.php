<?php

namespace KoenVanMeijeren\SpreadsheetReader;

/**
 * Spreadsheet reader for XLSX files.
 *
 * @internal This class is not meant to be used directly. Use SpreadsheetReader.
 */
class SpreadsheetReaderXLSX implements SpreadsheetReaderInterface {
  public const CELL_TYPE_BOOL = 'b';
  public const CELL_TYPE_NUMBER = 'n';
  public const CELL_TYPE_ERROR = 'e';
  public const CELL_TYPE_SHARED_STR = 's';
  public const CELL_TYPE_STR = 'str';
  public const CELL_TYPE_INLINE_STR = 'inlineStr';

  /**
   * Number of shared strings that can be reasonably cached.
   *
   * E,g., that aren't read from file but stored in memory. If the total number
   * of shared strings is higher than this, caching is not used. If this value
   * is null, shared strings are cached regardless of amount. With large shared
   * string caches, there are huge performance gains, however, a lot of memory
   * could be used which can be a problem, especially on shared hosting.
   */
  public const SHARED_STRING_CACHE_LIMIT = 1048576;

  /**
   * Whether the file is valid or not.
   */
  private bool $isValid = FALSE;

  /**
   * Path to the worksheet XML file.
   */
  private ?string $worksheetPath = NULL;

  /**
   * XML reader object for the worksheet XML file.
   */
  private ?\XMLReader $worksheet = NULL;

  /**
   * Path to shared strings XML file.
   */
  private ?string $sharedStringsPath = NULL;

  /**
   * XML reader object for the shared strings XML file.
   */
  private ?\XMLReader $sharedStrings = NULL;

  /**
   * Shared strings cache, if the number of shared strings is low enough.
   */
  private array $sharedStringCache = [];

  /**
   * XML object for the workbook XML file.
   */
  private ?\SimpleXMLElement $workbookXML = NULL;

  /**
   * Temporary directory path.
   */
  private string $tempDir;

  /**
   * Temporary files created by this class.
   */
  private array $tempFiles = [];

  /**
   * The current row in the file.
   */
  private mixed $currentRow = FALSE;

  /**
   * Current row in the file.
   */
  private int $currentRowIndex = 0;

  /**
   * Data about separate sheets in the file.
   */
  private array $sheets = [];

  /**
   * Number of shared strings in the file.
   */
  private int $sharedStringCount = 0;

  /**
   * Index of the last shared string fetched.
   */
  private int $sharedStringIndex = 0;

  /**
   * Value of the last shared string fetched.
   */
  private mixed $lastSharedStringValue = NULL;

  /**
   * Whether the current row is open or not.
   */
  private bool $isRowOpen = FALSE;

  /**
   * Whether the current shared string is open or not.
   */
  private bool $isSSOpen = FALSE;

  /**
   * Whether the current shared string is open or not.
   */
  private bool $isSSForwarded = FALSE;

  /**
   * Constructs a new object.
   */
  public function __construct(string $filepath) {
    if (!is_readable($filepath)) {
      throw new \RuntimeException('File not readable (' . $filepath . ')');
    }

    $this->tempDir = sys_get_temp_dir();
    $this->tempDir = rtrim($this->tempDir, DIRECTORY_SEPARATOR);
    $this->tempDir .= DIRECTORY_SEPARATOR . uniqid('', TRUE) . DIRECTORY_SEPARATOR;

    $zip = new \ZipArchive();
    $zipStatus = $zip->open($filepath);
    if ($zipStatus !== TRUE) {
      throw new \RuntimeException('File not readable (' . $filepath . ') (Error ' . $zipStatus . ')');
    }

    // Getting the general workbook information.
    if ($zip->locateName('xl/workbook.xml') !== FALSE) {
      $this->workbookXML = new \SimpleXMLElement($zip->getFromName('xl/workbook.xml'));
    }

    // Extracting the XMLs from the XLSX zip file.
    if ($zip->locateName('xl/sharedStrings.xml') !== FALSE) {
      $this->sharedStringsPath = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
      $zip->extractTo($this->tempDir, 'xl/sharedStrings.xml');
      $this->tempFiles[] = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';

      if (is_readable($this->sharedStringsPath)) {
        $this->sharedStrings = new \XMLReader();
        $this->sharedStrings->open($this->sharedStringsPath);
        $this->prepareSharedStringCache();
      }
    }

    // Initializes the sheets.
    $sheets = $this->sheets(); // phpcs:ignore
    foreach (array_keys($this->sheets) as $index) {
      if ($zip->locateName('xl/worksheets/sheet' . $index . '.xml') !== FALSE) {
        $zip->extractTo($this->tempDir, 'xl/worksheets/sheet' . $index . '.xml');
        $this->tempFiles[] = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet' . $index . '.xml';
      }
    }

    $this->changeSheet(0);
    $zip->close();
  }

  /**
   * Destructor, destroys all that remains (closes and deletes temp files)
   */
  public function __destruct() {
    foreach ($this->tempFiles as $tempFile) {
      @unlink($tempFile);
    }

    // Better safe than sorry - shouldn't try deleting '.' or '/', or '..'.
    if (strlen($this->tempDir) > 2) {
      @rmdir($this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'worksheets');
      @rmdir($this->tempDir . 'xl');
      @rmdir($this->tempDir);
    }

    if ($this->worksheet instanceof \XMLReader) {
      $this->worksheet->close();
      unset($this->worksheet);
    }

    unset($this->worksheetPath);

    if ($this->sharedStrings instanceof \XMLReader) {
      $this->sharedStrings->close();
      unset($this->sharedStrings);
    }

    unset($this->sharedStringsPath);

    if ($this->workbookXML) {
      unset($this->workbookXML);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function sheets(): array {
    if ($this->sheets === []) {
      foreach ($this->workbookXML->sheets->sheet as $sheet) {
        $this->sheets[(string) $sheet['sheetId']] = (string) $sheet['name'];
      }

      ksort($this->sheets);
    }

    return array_values($this->sheets);
  }

  /**
   * {@inheritDoc}
   */
  public function changeSheet(int $index): bool {
    $realSheetIndex = FALSE;
    $sheets = $this->sheets();
    if (isset($sheets[$index])) {
      $sheetIndexes = array_keys($this->sheets);
      $realSheetIndex = $sheetIndexes[$index];
    }

    $tempWorksheetPath = $this->tempDir . 'xl/worksheets/sheet' . $realSheetIndex . '.xml';

    if ($realSheetIndex !== FALSE && is_readable($tempWorksheetPath)) {
      $this->worksheetPath = $tempWorksheetPath;

      $this->rewind();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Create shared string cache.
   *
   * Only done if the number of shared strings is acceptably low or there is no
   * limit on the amount.
   */
  private function prepareSharedStringCache(): void {
    while ($this->sharedStrings->read()) {
      if ($this->sharedStrings->name == 'sst') {
        $this->sharedStringCount = $this->sharedStrings->getAttribute('uniqueCount');
        break;
      }
    }

    if (!$this->sharedStringCount || (self::SHARED_STRING_CACHE_LIMIT < $this->sharedStringCount && self::SHARED_STRING_CACHE_LIMIT !== NULL)) {
      return;
    }

    $cacheIndex = 0;
    $cacheValue = '';
    while ($this->sharedStrings->read()) {
      switch ($this->sharedStrings->name) {
        case 'si':
          if ($this->sharedStrings->nodeType == \XMLReader::END_ELEMENT) {
            $this->sharedStringCache[$cacheIndex] = $cacheValue;
            $cacheIndex++;
            $cacheValue = '';
          }
          break;

        case 't':
          if ($this->sharedStrings->nodeType == \XMLReader::END_ELEMENT) {
            continue 2;
          }

          $cacheValue .= $this->sharedStrings->readString();
          break;
      }
    }

    $this->sharedStrings->close();
  }

  /**
   * Retrieves a shared string value by its index.
   */
  private function getSharedString(int $index): string {
    if ((self::SHARED_STRING_CACHE_LIMIT === NULL || self::SHARED_STRING_CACHE_LIMIT > 0) && !empty($this->sharedStringCache)) {
      return ($this->sharedStringCache[$index] ?? '');
    }

    // If the desired index is before the current, rewind the XML.
    if ($this->sharedStringIndex > $index) {
      $this->isSSOpen = FALSE;
      $this->sharedStrings->close();
      $this->sharedStrings->open($this->sharedStringsPath);
      $this->sharedStringIndex = 0;
      $this->lastSharedStringValue = NULL;
      $this->isSSForwarded = FALSE;
    }

    // Finding the unique string count (if not already read)
    if ($this->sharedStringIndex == 0 && !$this->sharedStringCount) {
      while ($this->sharedStrings->read()) {
        if ($this->sharedStrings->name == 'sst') {
          $this->sharedStringCount = $this->sharedStrings->getAttribute('uniqueCount');
          break;
        }
      }
    }

    // If index of desired string is larger than possible, don't even bother.
    if ($this->sharedStringCount && ($index >= $this->sharedStringCount)) {
      return '';
    }

    // If an index with the same value as the last already fetched is requested
    // (any further traversing the tree would get us further away from the node)
    if (($index == $this->sharedStringIndex) && ($this->lastSharedStringValue !== NULL)) {
      return $this->lastSharedStringValue;
    }

    // Find the correct <si> node with the desired index.
    while ($this->sharedStringIndex <= $index) {
      // SSForwarded is set further to avoid double reading in case nodes are
      // skipped.
      if ($this->isSSForwarded) {
        $this->isSSForwarded = FALSE;
      }
      else {
        $readStatus = $this->sharedStrings->read();
        if (!$readStatus) {
          break;
        }
      }

      if ($this->sharedStrings->name == 'si') {
        if ($this->sharedStrings->nodeType == \XMLReader::END_ELEMENT) {
          $this->isSSOpen = FALSE;
          $this->sharedStringIndex++;
        }
        else {
          $this->isSSOpen = TRUE;

          if ($this->sharedStringIndex < $index) {
            $this->isSSOpen = FALSE;
            $this->sharedStrings->next('si');
            $this->isSSForwarded = TRUE;
            $this->sharedStringIndex++;
            continue;
          }

          break;
        }
      }
    }

    $value = '';

    // Extract the value from the shared string.
    if ($this->isSSOpen && ($this->sharedStringIndex == $index)) {
      while ($this->sharedStrings->read()) {
        switch ($this->sharedStrings->name) {
          case 't':
            if ($this->sharedStrings->nodeType == \XMLReader::END_ELEMENT) {
              continue 2;
            }

            $value .= $this->sharedStrings->readString();
            break;

          case 'si':
            if ($this->sharedStrings->nodeType == \XMLReader::END_ELEMENT) {
              $this->isSSOpen = FALSE;
              $this->isSSForwarded = TRUE;
              break 2;
            }
            break;
        }
      }
    }

    if ($value) {
      $this->lastSharedStringValue = $value;
    }

    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function rewind(): void {
    // Removed the check whether $this -> Index == 0 otherwise ChangeSheet
    // doesn't work properly. If the worksheet was already iterated, the XML
    // file is reopened. Otherwise, it should be at the beginning anyway.
    if ($this->worksheet instanceof \XMLReader) {
      $this->worksheet->close();
    }
    else {
      $this->worksheet = new \XMLReader();
    }

    $this->worksheet->open($this->worksheetPath);

    $this->isValid = TRUE;
    $this->isRowOpen = FALSE;
    $this->currentRow = FALSE;
    $this->currentRowIndex = 0;
  }

  /**
   * {@inheritDoc}
   */
  public function current(): mixed {
    if ($this->currentRowIndex === 0 && $this->currentRow === FALSE) {
      $this->next();
      $this->currentRowIndex--;
    }

    return $this->currentRow;
  }

  /**
   * {@inheritDoc}
   */
  public function next(): void {
    $this->currentRowIndex++;

    $this->currentRow = [];

    if (!$this->isRowOpen) {
      while ($this->isValid = $this->worksheet->read()) {
        if ($this->worksheet->name == 'row') {
          // Getting the row-spanning area (stored as e.g., 1:12)
          // so that the last cells will be present, even if empty.
          $rowSpans = $this->worksheet->getAttribute('spans');
          if ($rowSpans) {
            $rowSpans = explode(':', $rowSpans);
            $currentRowColumnCount = $rowSpans[1];
          }
          else {
            $currentRowColumnCount = 0;
          }

          if ($currentRowColumnCount > 0) {
            $this->currentRow = array_fill(0, $currentRowColumnCount, '');
          }

          $this->isRowOpen = TRUE;
          break;
        }
      }
    }

    // Reading the necessary row, if found.
    if ($this->isRowOpen) {
      // These two are needed to control for empty cells.
      $maxIndex = 0;
      $cellCount = 0;

      $cellHasSharedString = FALSE;

      while ($this->isValid = $this->worksheet->read()) {
        switch ($this->worksheet->name) {
          // Row end found.
          case 'row':
            if ($this->worksheet->nodeType == \XMLReader::END_ELEMENT) {
              $this->isRowOpen = FALSE;
              break 2;
            }
            break;

          // Cell.
          case 'c':
            // If it is a closing tag, skip it.
            if ($this->worksheet->nodeType == \XMLReader::END_ELEMENT) {
              continue 2;
            }

            // Get the index of the cell.
            $index = $this->worksheet->getAttribute('r');
            $letter = preg_replace('{[^[:alpha:]]}S', '', $index);
            $index = self::indexFromColumnLetter($letter);

            $cellHasSharedString = $this->worksheet->getAttribute('t') == self::CELL_TYPE_SHARED_STR;

            $value = $this->worksheet->readString();

            if ($cellHasSharedString) {
              $value = $this->getSharedString((int) $value);
            }

            $this->currentRow[$index] = $value;

            $cellCount++;
            if ($index > $maxIndex) {
              $maxIndex = $index;
            }
            break;

          // Cell value.
          case 'v':
            if ($this->worksheet->nodeType == \XMLReader::END_ELEMENT) {
              continue 2;
            }

            $value = $this->worksheet->readString();

            if ($cellHasSharedString) {
              $value = $this->getSharedString((int) $value);
            }

            $this->currentRow[$index] = $value;
            break;
        }
      }

      // Adding empty cells, if necessary,
      // Only empty cells between and on the left side are added.
      if (($maxIndex + 1) > $cellCount) {
        $this->currentRow = ($this->currentRow + array_fill(0, ($maxIndex + 1), ''));
        ksort($this->currentRow);
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
    return $this->isValid;
  }

  /**
   * {@inheritDoc}
   */
  public function count(): int {
    return $this->currentRowIndex + 1;

  }

  /**
   * Takes the column letter and converts it to a numerical index (0-based)
   *
   * @param string $letter
   *   Letter(s) to convert.
   *
   * @return int
   *   Numeric index (0-based) or boolean false if it cannot be calculated.
   */
  public static function indexFromColumnLetter(string $letter): int {
    $letter = strtoupper($letter);

    $result = 0;
    for ($i = (strlen($letter) - 1), $j = 0; $i >= 0; $i--, $j++) {
      $ord = (ord($letter[$i]) - 64);
      if ($ord > 26) {
        // Something is very, very wrong.
        return FALSE;
      }

      $result += ($ord * (26 ** $j));
    }

    return ($result - 1);
  }

}
