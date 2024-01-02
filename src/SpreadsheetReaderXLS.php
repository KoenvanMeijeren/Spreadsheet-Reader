<?php
namespace KoenVanMeijeren\SpreadsheetReader;

use RuntimeException;
use Spreadsheet_Excel_Reader;

/**
 * Class for parsing XLS files
 *
 * @version 1.0
 * @author KoenVanMeijeren
 */
class SpreadsheetReaderXLS implements SpreadsheetReaderInterface
{
    /**
     * @var resource File handle
     */
    private mixed $inputFile = false;

    private int $index = 0;

    private bool $hasError = false;

    /**
     * @var array Sheet information
     */
    private array $sheets = [];
    private array $sheetIndexes = [];

    /**
     * @var int Current sheet index
     */
    private int $currentSheet = 0;

    /**
     * @var array Content of the current row
     */
    private array $currentRow = [];

    /**
     * @var int Row count in the sheet
     */
    private int $rowCount = 0;

    /**
     * @var array Template to use for empty rows. Retrieved rows are merged
     *    with this so that empty cells are added, too
     */
    private array $emptyRow = [];

    /**
     * @param string $filepath Path to file
     * @param array $options Options
     */
    public function __construct(string $filepath, array $options = [])
    {
        if (!is_readable($filepath)) {
            throw new RuntimeException('File not readable (' . $filepath . ')');
        }

        if (!class_exists('Spreadsheet_Excel_Reader')) {
            throw new RuntimeException('Spreadsheet_Excel_Reader class not available');
        }

        $this->inputFile = new Spreadsheet_Excel_Reader($filepath, false, 'UTF-8');

        if (function_exists('mb_convert_encoding')) {
            $this->inputFile->setUTFEncoder('mb');
        }

        if (empty($this->inputFile->sheets)) {
            $this->hasError = true;
            return;
        }

        $this->changeSheet(0);
    }

    public function __destruct()
    {
        unset($this->inputFile);
    }

    /**
     * Retrieves an array with information about sheets in the current file
     *
     * @return array List of sheets (key is sheet index, value is name)
     */
    public function sheets(): array
    {
        if ($this->sheets === []) {
            $this->sheets = array();
            $this->sheetIndexes = array_keys($this->inputFile->sheets);

            foreach ($this->sheetIndexes as $SheetIndex) {
                $this->sheets[] = $this->inputFile->boundsheets[$SheetIndex]['name'];
            }
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
        $sheets = $this->sheets();

        if (isset($this->sheets[$index])) {
            $this->rewind();
            $this->currentSheet = $this->sheetIndexes[$index];

            $columnCount = $this->inputFile->sheets[$this->currentSheet]['numCols'];
            $this->rowCount = $this->inputFile->sheets[$this->currentSheet]['numRows'];

            // For the case when Spreadsheet_Excel_Reader doesn't have the row count set correctly.
            if (!$this->rowCount && count($this->inputFile->sheets[$this->currentSheet]['cells'])) {
                end($this->inputFile->sheets[$this->currentSheet]['cells']);
                $this->rowCount = (int)key($this->inputFile->sheets[$this->currentSheet]['cells']);
            }

            if ($columnCount) {
                $this->emptyRow = array_fill(1, $columnCount, '');
            } else {
                $this->emptyRow = array();
            }
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
        $this->index = 0;
    }

    /**
     * Return the current element.
     * Similar to the current() function for arrays in PHP
     *
     * @return array current element from the collection
     */
    public function current(): array
    {
        if ($this->index === 0) {
            $this->next();
        }

        return $this->currentRow;
    }

    /**
     * Move forward to next element.
     * Similar to the next() function for arrays in PHP
     */
    public function next(): void
    {
        // Internal counter is advanced here instead of the if statement
        //	because apparently it's fully possible that an empty row will not be
        //	present at all
        $this->index++;

        if ($this->hasError) {
            return;
        }

        if (isset($this->inputFile->sheets[$this->currentSheet]['cells'][$this->index])) {
            $this->currentRow = $this->inputFile->sheets[$this->currentSheet]['cells'][$this->index];
            if (!$this->currentRow) {
                return;
            }

            $this->currentRow = $this->currentRow + $this->emptyRow;
            ksort($this->currentRow);

            $this->currentRow = array_values($this->currentRow);
            return;
        }

        $this->currentRow = $this->emptyRow;
    }

    /**
     * Return the identifying key of the current element.
     * Similar to the key() function for arrays in PHP
     */
    public function key(): int
    {
        return $this->index;
    }

    /**
     * Check if there is a current element after calls to rewind() or next().
     * Used to check if we've iterated to the end of the collection
     *
     * @return boolean FALSE if there's nothing more to iterate over
     */
    public function valid(): bool
    {
        if ($this->hasError) {
            return false;
        }
        return ($this->index <= $this->rowCount);
    }

    // !Countable interface method

    /**
     * Ostensibly should return the count of the contained items but this just returns the number
     * of rows read so far. It's not really correct but at least coherent.
     */
    public function count(): int
    {
        if ($this->hasError) {
            return 0;
        }

        return $this->rowCount;
    }
}
