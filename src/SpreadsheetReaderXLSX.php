<?php

namespace KoenVanMeijeren\SpreadsheetReader;

use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Class for parsing XLSX files specifically
 *
 * @version 1.0
 * @author KoenVanMeijeren
 */
class SpreadsheetReaderXLSX implements SpreadsheetReaderInterface
{
    public const CELL_TYPE_BOOL = 'b';
    public const CELL_TYPE_NUMBER = 'n';
    public const CELL_TYPE_ERROR = 'e';
    public const CELL_TYPE_SHARED_STR = 's';
    public const CELL_TYPE_STR = 'str';
    public const CELL_TYPE_INLINE_STR = 'inlineStr';

    /**
     * Number of shared strings that can be reasonably cached, i.e., that aren't read from file but stored in memory.
     *    If the total number of shared strings is higher than this, caching is not used.
     *    If this value is null, shared strings are cached regardless of amount.
     *    With large shared string caches, there are huge performance gains, however, a lot of memory could be used which
     *    can be a problem, especially on shared hosting.
     */
    public const SHARED_STRING_CACHE_LIMIT = 1048576;

    private bool $isValid = false;

    // Worksheet file
    /**
     * @var string Path to the worksheet XML file
     */
    private ?string $worksheetPath = null;
    /**
     * @var XMLReader XML reader object for the worksheet XML file
     */
    private ?XMLReader $worksheet = null;

    // Shared strings file
    /**
     * @var string Path to shared strings XML file
     */
    private ?string $sharedStringsPath = null;
    /**
     * @var XMLReader XML reader object for the shared strings XML file
     */
    private ?XMLReader $sharedStrings = null;
    /**
     * @var array Shared strings cache, if the number of shared strings is low enough
     */
    private array $sharedStringCache = [];

    // Workbook data
    /**
     * @var SimpleXMLElement XML object for the workbook XML file
     */
    private ?SimpleXMLElement $workbookXML = null;

    private string $tempDir = '';
    private array $tempFiles = [];

    private mixed $currentRow = false;

    // Runtime parsing data
    /**
     * @var int Current row in the file
     */
    private int $currentRowIndex = 0;

    /**
     * @var array Data about separate sheets in the file
     */
    private array $sheets = [];

    private int $sharedStringCount = 0;
    private int $SharedStringIndex = 0;
    private mixed $LastSharedStringValue = null;

    private bool $RowOpen = false;

    private bool $SSOpen = false;
    private bool $SSForwarded = false;

    /**
     * @param string Path to file
     * @param array Options:
     *    TempDir => string Temporary directory path
     *    ReturnDateTimeObjects => bool True => dates and times will be returned as PHP DateTime objects, false => as strings
     */
    public function __construct(string $Filepath, array $Options = [])
    {
        if (!is_readable($Filepath)) {
            throw new RuntimeException('File not readable (' . $Filepath . ')');
        }

        $this->tempDir = isset($Options['TempDir']) && is_writable($Options['TempDir']) ?
            $Options['TempDir'] :
            sys_get_temp_dir();

        $this->tempDir = rtrim($this->tempDir, DIRECTORY_SEPARATOR);
        $this->tempDir .= DIRECTORY_SEPARATOR . uniqid('', true) . DIRECTORY_SEPARATOR;

        $zip = new ZipArchive();
        $zip_status = $zip->open($Filepath);

        if ($zip_status !== true) {
            throw new RuntimeException('File not readable (' . $Filepath . ') (Error ' . $zip_status . ')');
        }

        // Getting the general workbook information
        if ($zip->locateName('xl/workbook.xml') !== false) {
            $this->workbookXML = new SimpleXMLElement($zip->getFromName('xl/workbook.xml'));
        }

        // Extracting the XMLs from the XLSX zip file
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $this->sharedStringsPath = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
            $zip->extractTo($this->tempDir, 'xl/sharedStrings.xml');
            $this->tempFiles[] = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';

            if (is_readable($this->sharedStringsPath)) {
                $this->sharedStrings = new XMLReader();
                $this->sharedStrings->open($this->sharedStringsPath);
                $this->prepareSharedStringCache();
            }
        }

        // Initializes the sheets.
        $sheets = $this->sheets();
        foreach ($this->sheets as $Index => $Name) {
            if ($zip->locateName('xl/worksheets/sheet' . $Index . '.xml') !== false) {
                $zip->extractTo($this->tempDir, 'xl/worksheets/sheet' . $Index . '.xml');
                $this->tempFiles[] = $this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet' . $Index . '.xml';
            }
        }

        $this->changeSheet(0);

        $zip->close();
    }

    /**
     * Destructor, destroys all that remains (closes and deletes temp files)
     */
    public function __destruct()
    {
        foreach ($this->tempFiles as $TempFile) {
            @unlink($TempFile);
        }

        // Better safe than sorry - shouldn't try deleting '.' or '/', or '..'.
        if (strlen($this->tempDir) > 2) {
            @rmdir($this->tempDir . 'xl' . DIRECTORY_SEPARATOR . 'worksheets');
            @rmdir($this->tempDir . 'xl');
            @rmdir($this->tempDir);
        }

        if ($this->worksheet instanceof XMLReader) {
            $this->worksheet->close();
            unset($this->worksheet);
        }
        unset($this->worksheetPath);

        if ($this->sharedStrings instanceof XMLReader) {
            $this->sharedStrings->close();
            unset($this->sharedStrings);
        }
        unset($this->sharedStringsPath);

        if ($this->workbookXML) {
            unset($this->workbookXML);
        }
    }

    /**
     * Retrieves an array with information about sheets in the current file
     *
     * @return array List of sheets (key is sheet index, value is name)
     */
    public function sheets(): array
    {
        if ($this->sheets === []) {
            foreach ($this->workbookXML->sheets->sheet as $Index => $Sheet) {
                $this->sheets[(string)$Sheet['sheetId']] = (string)$Sheet['name'];
            }
            ksort($this->sheets);
        }
        return array_values($this->sheets);
    }

    /**
     * Changes the current sheet in the file to another
     *
     * @param int Sheet index
     *
     * @return bool True if sheet was successfully changed, false otherwise.
     */
    public function changeSheet(int $index): bool
    {
        $RealSheetIndex = false;
        $Sheets = $this->sheets();
        if (isset($Sheets[$index])) {
            $SheetIndexes = array_keys($this->sheets);
            $RealSheetIndex = $SheetIndexes[$index];
        }

        $TempWorksheetPath = $this->tempDir . 'xl/worksheets/sheet' . $RealSheetIndex . '.xml';

        if ($RealSheetIndex !== false && is_readable($TempWorksheetPath)) {
            $this->worksheetPath = $TempWorksheetPath;

            $this->rewind();
            return true;
        }

        return false;
    }

    /**
     * Creating shared string cache if the number of shared strings is acceptably low (or there is no limit on the amount
     */
    private function prepareSharedStringCache(): void
    {
        while ($this->sharedStrings->read()) {
            if ($this->sharedStrings->name == 'sst') {
                $this->sharedStringCount = $this->sharedStrings->getAttribute('uniqueCount');
                break;
            }
        }

        if (!$this->sharedStringCount || (self::SHARED_STRING_CACHE_LIMIT < $this->sharedStringCount && self::SHARED_STRING_CACHE_LIMIT !== null)) {
            return;
        }

        $CacheIndex = 0;
        $CacheValue = '';
        while ($this->sharedStrings->read()) {
            switch ($this->sharedStrings->name) {
                case 'si':
                    if ($this->sharedStrings->nodeType == XMLReader::END_ELEMENT) {
                        $this->sharedStringCache[$CacheIndex] = $CacheValue;
                        $CacheIndex++;
                        $CacheValue = '';
                    }
                    break;
                case 't':
                    if ($this->sharedStrings->nodeType == XMLReader::END_ELEMENT) {
                        continue 2;
                    }
                    $CacheValue .= $this->sharedStrings->readString();
                    break;
            }
        }

        $this->sharedStrings->close();
    }

    /**
     * Retrieves a shared string value by its index
     */
    private function getSharedString(int $Index): string
    {
        if ((self::SHARED_STRING_CACHE_LIMIT === null || self::SHARED_STRING_CACHE_LIMIT > 0) && !empty($this->sharedStringCache)) {
            return $this->sharedStringCache[$Index] ?? '';
        }

        // If the desired index is before the current, rewind the XML
        if ($this->SharedStringIndex > $Index) {
            $this->SSOpen = false;
            $this->sharedStrings->close();
            $this->sharedStrings->open($this->sharedStringsPath);
            $this->SharedStringIndex = 0;
            $this->LastSharedStringValue = null;
            $this->SSForwarded = false;
        }

        // Finding the unique string count (if not already read)
        if ($this->SharedStringIndex == 0 && !$this->sharedStringCount) {
            while ($this->sharedStrings->read()) {
                if ($this->sharedStrings->name == 'sst') {
                    $this->sharedStringCount = $this->sharedStrings->getAttribute('uniqueCount');
                    break;
                }
            }
        }

        // If index of the desired string is larger than possible, don't even bother.
        if ($this->sharedStringCount && ($Index >= $this->sharedStringCount)) {
            return '';
        }

        // If an index with the same value as the last already fetched is requested
        // (any further traversing the tree would get us further away from the node)
        if (($Index == $this->SharedStringIndex) && ($this->LastSharedStringValue !== null)) {
            return $this->LastSharedStringValue;
        }

        // Find the correct <si> node with the desired index
        while ($this->SharedStringIndex <= $Index) {
            // SSForwarded is set further to avoid double reading in case nodes are skipped.
            if ($this->SSForwarded) {
                $this->SSForwarded = false;
            } else {
                $ReadStatus = $this->sharedStrings->read();
                if (!$ReadStatus) {
                    break;
                }
            }

            if ($this->sharedStrings->name == 'si') {
                if ($this->sharedStrings->nodeType == XMLReader::END_ELEMENT) {
                    $this->SSOpen = false;
                    $this->SharedStringIndex++;
                } else {
                    $this->SSOpen = true;

                    if ($this->SharedStringIndex < $Index) {
                        $this->SSOpen = false;
                        $this->sharedStrings->next('si');
                        $this->SSForwarded = true;
                        $this->SharedStringIndex++;
                        continue;
                    }

                    break;
                }
            }
        }

        $Value = '';

        // Extract the value from the shared string
        if ($this->SSOpen && ($this->SharedStringIndex == $Index)) {
            while ($this->sharedStrings->read()) {

                switch ($this->sharedStrings->name) {
                    case 't':
                        if ($this->sharedStrings->nodeType == XMLReader::END_ELEMENT) {
                            continue 2;
                        }
                        $Value .= $this->sharedStrings->readString();
                        break;
                    case 'si':
                        if ($this->sharedStrings->nodeType == XMLReader::END_ELEMENT) {
                            $this->SSOpen = false;
                            $this->SSForwarded = true;
                            break 2;
                        }
                        break;
                }
            }
        }

        if ($Value) {
            $this->LastSharedStringValue = $Value;
        }

        return $Value;
    }

    // !Iterator interface methods

    /**
     * Rewind the Iterator to the first element.
     * Similar to the reset() function for arrays in PHP
     */
    public function rewind(): void
    {
        // Removed the check whether $this -> Index == 0 otherwise ChangeSheet doesn't work properly

        // If the worksheet was already iterated, the XML file is reopened.
        // Otherwise, it should be at the beginning anyway
        if ($this->worksheet instanceof XMLReader) {
            $this->worksheet->close();
        } else {
            $this->worksheet = new XMLReader();
        }

        $this->worksheet->open($this->worksheetPath);

        $this->isValid = true;
        $this->RowOpen = false;
        $this->currentRow = false;
        $this->currentRowIndex = 0;
    }

    /**
     * Return the current element.
     * Similar to the current() function for arrays in PHP
     *
     * @return mixed current element from the collection
     */
    public function current(): mixed
    {
        if ($this->currentRowIndex === 0 && $this->currentRow === false) {
            $this->next();
            $this->currentRowIndex--;
        }
        return $this->currentRow;
    }

    /**
     * Move forward to the next element.
     * Similar to the next() function for arrays in PHP
     */
    public function next(): void
    {
        $this->currentRowIndex++;

        $this->currentRow = array();

        if (!$this->RowOpen) {
            while ($this->isValid = $this->worksheet->read()) {
                if ($this->worksheet->name == 'row') {
                    // Getting the row-spanning area (stored as e.g., 1:12)
                    // so that the last cells will be present, even if empty
                    $RowSpans = $this->worksheet->getAttribute('spans');
                    if ($RowSpans) {
                        $RowSpans = explode(':', $RowSpans);
                        $CurrentRowColumnCount = $RowSpans[1];
                    } else {
                        $CurrentRowColumnCount = 0;
                    }

                    if ($CurrentRowColumnCount > 0) {
                        $this->currentRow = array_fill(0, $CurrentRowColumnCount, '');
                    }

                    $this->RowOpen = true;
                    break;
                }
            }
        }

        // Reading the necessary row, if found
        if ($this->RowOpen) {
            // These two are needed to control for empty cells
            $MaxIndex = 0;
            $CellCount = 0;

            $CellHasSharedString = false;

            while ($this->isValid = $this->worksheet->read()) {
                switch ($this->worksheet->name) {
                    // Row end found.
                    case 'row':
                        if ($this->worksheet->nodeType == XMLReader::END_ELEMENT) {
                            $this->RowOpen = false;
                            break 2;
                        }
                        break;
                    // Cell
                    case 'c':
                        // If it is a closing tag, skip it
                        if ($this->worksheet->nodeType == XMLReader::END_ELEMENT) {
                            continue 2;
                        }

                        // Get the index of the cell
                        $Index = $this->worksheet->getAttribute('r');
                        $Letter = preg_replace('{[^[:alpha:]]}S', '', $Index);
                        $Index = self::indexFromColumnLetter($Letter);

                        $CellHasSharedString = $this->worksheet->getAttribute('t') == self::CELL_TYPE_SHARED_STR;

                        $Value = $this->worksheet->readString();

                        if ($CellHasSharedString) {
                            $Value = $this->getSharedString((int) $Value);
                        }

                        $this->currentRow[$Index] = $Value;

                        $CellCount++;
                        if ($Index > $MaxIndex) {
                            $MaxIndex = $Index;
                        }

                        break;
                    // Cell value
                    case 'v':
                        if ($this->worksheet->nodeType == XMLReader::END_ELEMENT) {
                            continue 2;
                        }

                        $Value = $this->worksheet->readString();

                        if ($CellHasSharedString) {
                            $Value = $this->getSharedString((int) $Value);
                        }

                        $this->currentRow[$Index] = $Value;
                        break;
                }
            }

            // Adding empty cells, if necessary,
            // Only empty cells between and on the left side are added
            if ($MaxIndex + 1 > $CellCount) {
                $this->currentRow = $this->currentRow + array_fill(0, $MaxIndex + 1, '');
                ksort($this->currentRow);
            }
        }
    }

    /**
     * Return the identifying key of the current element.
     * Similar to the key() function for arrays in PHP
     */
    public function key(): int
    {
        return $this->currentRowIndex;
    }

    /**
     * Check if there is a current element after calls to rewind() or next().
     * Used to check if we've iterated to the end of the collection
     *
     * @return boolean FALSE if there's nothing more to iterate over
     */
    public function valid(): bool
    {
        return $this->isValid;
    }

    // !Countable interface method

    /**
     * Ostensibly should return the count of the contained items but this just returns the number
     * of rows read so far. It's not really correct but at least coherent.
     */
    public function count(): int
    {
        return $this->currentRowIndex + 1;
    }

    /**
     * Takes the column letter and converts it to a numerical index (0-based)
     *
     * @param string Letter(s) to convert
     *
     * @return mixed Numeric index (0-based) or boolean false if it cannot be calculated
     */
    public static function indexFromColumnLetter($Letter): int
    {
        $Letter = strtoupper($Letter);

        $Result = 0;
        for ($i = strlen($Letter) - 1, $j = 0; $i >= 0; $i--, $j++) {
            $Ord = ord($Letter[$i]) - 64;
            if ($Ord > 26) {
                // Something is very, very wrong
                return false;
            }
            $Result += $Ord * (26 ** $j);
        }
        return $Result - 1;
    }
}
