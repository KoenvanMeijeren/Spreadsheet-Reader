<?php

declare(strict_types=1);

namespace KoenVanMeijeren\SpreadsheetReader;

/**
 * Provides an interface for SpreadsheetReaderInterface.
 *
 * @package KoenVanMeijeren\SpreadsheetReader;
 */
interface SpreadsheetReaderInterface extends \Iterator, \Countable
{

    /**
     * Retrieves an array with information about sheets in the current file
     *
     * @return array List of sheets (key is sheet index, value is name)
     */
    public function sheets(): array;

    /**
     * Changes the current sheet in the file to another
     *
     * @param int $index Sheet index
     *
     * @return bool True if a sheet was successfully changed, false otherwise.
     */
    public function changeSheet(int $index): bool;

}
