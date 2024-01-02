<?php
namespace KoenVanMeijeren\SpreadsheetReader;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Class for parsing ODS files
 *
 * @version 1.0
 * @author KoenVanMeijeren
 */
class SpreadsheetReaderODS implements SpreadsheetReaderInterface
{
    /**
     * @var string Path to temporary content file
     */
    private string $contentPath = '';
    /**
     * @var XMLReader XML reader object
     */
    private ?XMLReader $content = null;

    /**
     * @var array Data about separate sheets in the file
     */
    private array $sheets = [];

    private mixed $currentRow = null;

    /**
     * @var int Number of the sheet we're currently reading
     */
    private int $currentSheet = 0;

    private int $currentRowIndex = 0;

    private bool $isTableOpen = false;
    private bool $isRowOpen = false;
    private bool $isValid = false;

    private string $TempDir = '';

    /**
     * Constructs a new spreadsheet reader for ODS files.
     *
     * @param string $filepath Path to file
     * @param array $options Options:
     *    TempDir => string Temporary directory path
     *    ReturnDateTimeObjects => bool True => dates and times will be returned as PHP DateTime objects, false => as strings
     */
    public function __construct(string $filepath, array $options = [])
    {
        if (!is_readable($filepath)) {
            throw new RuntimeException('File not readable (' . $filepath . ')');
        }

        $this->TempDir = isset($options['TempDir']) && is_writable($options['TempDir']) ?
            $options['TempDir'] :
            sys_get_temp_dir();

        $this->TempDir = rtrim($this->TempDir, DIRECTORY_SEPARATOR);
        $this->TempDir .= DIRECTORY_SEPARATOR . uniqid('', true) . DIRECTORY_SEPARATOR;

        $zip = new ZipArchive();
        $zip_status = $zip->open($filepath);

        if ($zip_status !== true) {
            throw new RuntimeException('File not readable (' . $filepath . ') (Error ' . $zip_status . ')');
        }

        if ($zip->locateName('content.xml') !== false) {
            $zip->extractTo($this->TempDir, 'content.xml');
            $this->contentPath = $this->TempDir . 'content.xml';
        }

        $zip->close();

        if ($this->contentPath && is_readable($this->contentPath)) {
            $this->content = new XMLReader();
            $this->content->open($this->contentPath);
            $this->isValid = true;
        }
    }

    /**
     * Destructor, destroys all that remains (closes and deletes temp files)
     */
    public function __destruct()
    {
        if ($this->content instanceof XMLReader) {
            $this->content->close();
            unset($this->content);
        }
        if (file_exists($this->contentPath)) {
            @unlink($this->contentPath);
            unset($this->contentPath);
        }
    }

    /**
     * Retrieves an array with information about sheets in the current file
     *
     * @return array List of sheets (key is sheet index, value is name)
     */
    public function sheets(): array
    {
        if ($this->sheets === [] && $this->isValid) {
            $sheetReader = new XMLReader();
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
     * Changes the current sheet in the file to another
     *
     * @param int Sheet index
     *
     * @return bool True if sheet was successfully changed, false otherwise.
     */
    public function changeSheet(int $index): bool
    {
        $Sheets = $this->sheets();
        if (isset($Sheets[$index])) {
            $this->currentSheet = $index;
            $this->rewind();

            return true;
        }

        return false;
    }

    // !Iterator interface methods

    /**
     * Rewind the Iterator to the first element.
     * Similar to the reset() function for arrays in PHP
     */
    public function rewind(): void
    {
        if ($this->currentRowIndex > 0) {
            // If the worksheet was already iterated, the XML file is reopened.
            // Otherwise, it should be at the beginning anyway
            $this->content->close();
            $this->content->open($this->contentPath);
            $this->isValid = true;

            $this->isTableOpen = false;
            $this->isRowOpen = false;

            $this->currentRow = null;
        }

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
        if ($this->currentRowIndex === 0 && is_null($this->currentRow)) {
            $this->next();
            $this->currentRowIndex--;
        }
        return $this->currentRow;
    }

    /**
     * Move forward to next element.
     * Similar to the next() function for arrays in PHP
     */
    public function next(): void
    {
        $this->currentRowIndex++;

        $this->currentRow = array();

        if (!$this->isTableOpen) {
            $TableCounter = 0;
            $SkipRead = false;

            while ($this->isValid = ($SkipRead || $this->content->read())) {
                if ($SkipRead) {
                    $SkipRead = false;
                }

                if ($this->content->name == 'table:table' && $this->content->nodeType != XMLReader::END_ELEMENT) {
                    if ($TableCounter == $this->currentSheet) {
                        $this->isTableOpen = true;
                        break;
                    }

                    $TableCounter++;
                    $this->content->next();
                    $SkipRead = true;
                }
            }
        }

        if ($this->isTableOpen && !$this->isRowOpen) {
            while ($this->isValid = $this->content->read()) {
                switch ($this->content->name) {
                    case 'table:table':
                        $this->isTableOpen = false;
                        $this->content->next('office:document-content');
                        $this->isValid = false;
                        break 2;
                    case 'table:table-row':
                        if ($this->content->nodeType != XMLReader::END_ELEMENT) {
                            $this->isRowOpen = true;
                            break 2;
                        }
                        break;
                }
            }
        }

        if ($this->isRowOpen) {
            $LastCellContent = '';

            while ($this->isValid = $this->content->read()) {
                switch ($this->content->name) {
                    case 'table:table-cell':
                        if ($this->content->nodeType == XMLReader::END_ELEMENT || $this->content->isEmptyElement) {
                            if ($this->content->nodeType == XMLReader::END_ELEMENT) {
                                $CellValue = $LastCellContent;
                            } elseif ($this->content->isEmptyElement) {
                                $LastCellContent = '';
                                $CellValue = $LastCellContent;
                            }

                            $this->currentRow[] = $LastCellContent;

                            if ($this->content->getAttribute('table:number-columns-repeated') !== null) {
                                $RepeatedColumnCount = $this->content->getAttribute('table:number-columns-repeated');
                                // Checking if larger than one because the value is already added to the row once before
                                if ($RepeatedColumnCount > 1) {
                                    $this->currentRow = array_pad($this->currentRow, count($this->currentRow) + $RepeatedColumnCount - 1, $LastCellContent);
                                }
                            }
                        } else {
                            $LastCellContent = '';
                        }
                        break;
                    case 'text:p':
                        if ($this->content->nodeType != XMLReader::END_ELEMENT) {
                            $LastCellContent = $this->content->readString();
                        }
                        break;
                    case 'table:table-row':
                        $this->isRowOpen = false;
                        break 2;
                }
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
}
