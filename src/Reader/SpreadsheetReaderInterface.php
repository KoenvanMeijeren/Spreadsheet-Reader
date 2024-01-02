<?php

declare(strict_types=1);

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

/**
 * Provides an interface for SpreadsheetReaderInterface.
 *
 * @package KoenVanMeijeren\SpreadsheetReader;
 */
interface SpreadsheetReaderInterface extends \Iterator, \Countable {

  /**
   * Retrieves an array with information about sheets in the current file.
   *
   * @return array
   *   List of sheets (key is sheet index, value is name).
   */
  public function sheets(): array;

  /**
   * Changes the current sheet to another from the file.
   *
   * Note that changing the sheet will rewind the file to the beginning, even if
   * the current sheet index is provided.
   *
   * @return bool
   *   True if sheet could be changed to the specified one,
   *    false if not (for example, if incorrect index was provided).
   */
  public function changeSheet(int $index): bool;

}
