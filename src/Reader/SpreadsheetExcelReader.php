<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;

/**
 * @file
 * A class for reading Microsoft Excel (97/2003) Spreadsheets.
 *
 * Version 2.21
 *
 * Enhanced and maintained by Matt Kruse < http://mattkruse.com >
 * Maintained at http://code.google.com/p/php-excel-reader/
 *
 * Format parsing and MUCH more contributed by:
 *    Matt Roxburgh < http://www.roxburgh.me.uk >
 *
 * DOCUMENTATION
 * =============
 *   http://code.google.com/p/php-excel-reader/wiki/Documentation
 *
 * CHANGE LOG
 * ==========
 *   http://code.google.com/p/php-excel-reader/wiki/ChangeHistory
 *
 * DISCUSSION/SUPPORT
 * ==================
 *   http://groups.google.com/group/php-excel-reader-discuss/topics
 *
 * --------------------------------------------------------------------------
 *
 * Originally developed by Vadim Tkachenko under the name PHPExcelReader.
 * (http://sourceforge.net/projects/phpexcelreader)
 * Based on the Java version by Andy Khan (http://www.andykhan.com). Now
 * maintained by David Sanders. Reads only Biff 7 and Biff 8 formats.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt. If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category Spreadsheet
 * @package Spreadsheet_Excel_Reader
 * @license http://www.php.net/license/3_0.txt PHP License 3.0
 * @version CVS: $Id: reader.php 19 2007-03-13 12:42:41Z shangxiao $
 * @link http://pear.php.net/package/Spreadsheet_Excel_Reader
 * @see OLE, Spreadsheet_Excel_Writer
 * --------------------------------------------------------------------------
 */

const NUM_BIG_BLOCK_DEPOT_BLOCKS_POS = 0x2c;
const SMALL_BLOCK_DEPOT_BLOCK_POS = 0x3c;
const ROOT_START_BLOCK_POS = 0x30;
const BIG_BLOCK_SIZE = 0x200;
const SMALL_BLOCK_SIZE = 0x40;
const EXTENSION_BLOCK_POS = 0x44;
const NUM_EXTENSION_BLOCK_POS = 0x48;
const PROPERTY_STORAGE_BLOCK_SIZE = 0x80;
const BIG_BLOCK_DEPOT_BLOCKS_POS = 0x4c;
const SMALL_BLOCK_THRESHOLD = 0x1000;
// Property storage offsets.
const SIZE_OF_NAME_POS = 0x40;
const TYPE_POS = 0x42;
const START_BLOCK_POS = 0x74;
const SIZE_POS = 0x78;

/**
 * Gets the integer value of a 4-byte string.
 */
function get_int4d(string $data, int $pos): int {
  $value = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
  if ($value >= 4294967294) {
    $value = -2;
  }
  return $value;
}

/**
 * Http://uk.php.net/manual/en/function.getdate.php.
 */
function gm_get_date(int|float|NULL $ts = NULL): array {
  $k = ['seconds', 'minutes', 'hours', 'mday', 'wday', 'mon', 'year', 'yday', 'weekday', 'month', 0];
  return (array_combine($k, explode(":", gmdate('s:i:G:j:w:n:Y:z:l:F:U', !$ts ? time() : (int) $ts))));
}

/**
 * Convert a 1900 based date offset into a Unix timestamp.
 */
function v(string $data, int|float $pos): int {
  return ord($data[$pos]) | ord($data[$pos + 1]) << 8;
}

const SPREADSHEET_EXCEL_READER_BIFF8 = 0x600;
const SPREADSHEET_EXCEL_READER_BIFF7 = 0x500;
const SPREADSHEET_EXCEL_READER_WORKBOOKGLOBALS = 0x5;
const SPREADSHEET_EXCEL_READER_WORKSHEET = 0x10;
const SPREADSHEET_EXCEL_READER_TYPE_BOF = 0x809;
const SPREADSHEET_EXCEL_READER_TYPE_EOF = 0x0a;
const SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET = 0x85;
const SPREADSHEET_EXCEL_READER_TYPE_DIMENSION = 0x200;
const SPREADSHEET_EXCEL_READER_TYPE_ROW = 0x208;
const SPREADSHEET_EXCEL_READER_TYPE_DBCELL = 0xd7;
const SPREADSHEET_EXCEL_READER_TYPE_FILEPASS = 0x2f;
const SPREADSHEET_EXCEL_READER_TYPE_NOTE = 0x1c;
const SPREADSHEET_EXCEL_READER_TYPE_TXO = 0x1b6;
const SPREADSHEET_EXCEL_READER_TYPE_RK = 0x7e;
const SPREADSHEET_EXCEL_READER_TYPE_RK2 = 0x27e;
const SPREADSHEET_EXCEL_READER_TYPE_MULRK = 0xbd;
const SPREADSHEET_EXCEL_READER_TYPE_MULBLANK = 0xbe;
const SPREADSHEET_EXCEL_READER_TYPE_INDEX = 0x20b;
const SPREADSHEET_EXCEL_READER_TYPE_SST = 0xfc;
const SPREADSHEET_EXCEL_READER_TYPE_EXTSST = 0xff;
const SPREADSHEET_EXCEL_READER_TYPE_CONTINUE = 0x3c;
const SPREADSHEET_EXCEL_READER_TYPE_LABEL = 0x204;
const SPREADSHEET_EXCEL_READER_TYPE_LABELSST = 0xfd;
const SPREADSHEET_EXCEL_READER_TYPE_NUMBER = 0x203;
const SPREADSHEET_EXCEL_READER_TYPE_NAME = 0x18;
const SPREADSHEET_EXCEL_READER_TYPE_ARRAY = 0x221;
const SPREADSHEET_EXCEL_READER_TYPE_STRING = 0x207;
const SPREADSHEET_EXCEL_READER_TYPE_FORMULA = 0x406;
const SPREADSHEET_EXCEL_READER_TYPE_FORMULA2 = 0x6;
const SPREADSHEET_EXCEL_READER_TYPE_FORMAT = 0x41e;
const SPREADSHEET_EXCEL_READER_TYPE_XF = 0xe0;
const SPREADSHEET_EXCEL_READER_TYPE_BOOLERR = 0x205;
const SPREADSHEET_EXCEL_READER_TYPE_FONT = 0x0031;
const SPREADSHEET_EXCEL_READER_TYPE_PALETTE = 0x0092;
const SPREADSHEET_EXCEL_READER_TYPE_UNKNOWN = 0xffff;
const SPREADSHEET_EXCEL_READER_TYPE_NINETEENFOUR = 0x22;
const SPREADSHEET_EXCEL_READER_TYPE_MERGEDCELLS = 0xE5;
const SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS = 25569;
const SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS1904 = 24107;
const SPREADSHEET_EXCEL_READER_MSINADAY = 86400;
const SPREADSHEET_EXCEL_READER_TYPE_HYPER = 0x01b8;
const SPREADSHEET_EXCEL_READER_TYPE_COLINFO = 0x7d;
const SPREADSHEET_EXCEL_READER_TYPE_DEFCOLWIDTH = 0x55;
const SPREADSHEET_EXCEL_READER_TYPE_STANDARDWIDTH = 0x99;
const SPREADSHEET_EXCEL_READER_DEF_NUM_FORMAT = "%s";

/**
 * Provides the class for reading Microsoft Excel (97/2003) Spreadsheets.
 */
final class SpreadsheetExcelReader {

  /**
   * MK: Added to make data retrieval easier.
   */
  public array $colNames = [];

  /**
   * The column indexes.
   */
  public array $colIndexes = [];

  /**
   * The bound sheets.
   */
  public array $boundSheets = [];

  /**
   * The format records.
   */
  private array $formatRecords = [];

  /**
   * The font records.
   */
  public array $fontRecords = [];

  /**
   * The xf records.
   */
  private array $xfRecords = [];

  /**
   * The col info.
   */
  public array $colInfo = [];

  /**
   * The row info.
   */
  public array $rowInfo = [];

  /**
   * The sst.
   */
  private array $sst = [];

  /**
   * The sheets.
   */
  public array $sheets = [];

  /**
   * The data.
   */
  private mixed $data = NULL;

  /**
   * The OLE reader.
   */
  private OLERead $oleRead;

  /**
   * The default encoding.
   */
  private string $defaultEncoding = "UTF-8";

  /**
   * The default format.
   */
  private string $defaultFormat = SPREADSHEET_EXCEL_READER_DEF_NUM_FORMAT;

  /**
   * The columns format.
   */
  private array $columnsFormat = [];

  /**
   * The row offset.
   */
  private int $rowOffset = 1;

  /**
   * The column offset.
   */
  private int $columnOffset = 1;

  /**
   * List of default date formats used by Excel.
   */
  private array $dateFormats = [
    0xe => "m/d/Y",
    0xf => "M-d-Y",
    0x10 => "d-M",
    0x11 => "M-Y",
    0x12 => "h:i a",
    0x13 => "h:i:s a",
    0x14 => "H:i",
    0x15 => "H:i:s",
    0x16 => "d/m/Y H:i",
    0x2d => "i:s",
    0x2e => "H:i:s",
    0x2f => "i:s.S",
  ];

  /**
   * Default number formats used by Excel.
   */
  private array $numberFormats = [
    0x1 => "0",
    0x2 => "0.00",
    0x3 => "#,##0",
    0x4 => "#,##0.00",
    0x5 => "\$#,##0;(\$#,##0)",
    0x6 => "\$#,##0;[Red](\$#,##0)",
    0x7 => "\$#,##0.00;(\$#,##0.00)",
    0x8 => "\$#,##0.00;[Red](\$#,##0.00)",
    0x9 => "0%",
    0xa => "0.00%",
    0xb => "0.00E+00",
    0x25 => "#,##0;(#,##0)",
    0x26 => "#,##0;[Red](#,##0)",
    0x27 => "#,##0.00;(#,##0.00)",
    0x28 => "#,##0.00;[Red](#,##0.00)",
  // Not exactly.
    0x29 => "#,##0;(#,##0)",
  // Not exactly.
    0x2a => "\$#,##0;(\$#,##0)",
  // Not exactly.
    0x2b => "#,##0.00;(#,##0.00)",
  // Not exactly.
    0x2c => "\$#,##0.00;(\$#,##0.00)",
    0x30 => "##0.0E+0",
  ];

  /**
   * The line styles.
   */
  private array $lineStyles = [
    0x00 => "",
    0x01 => "Thin",
    0x02 => "Medium",
    0x03 => "Dashed",
    0x04 => "Dotted",
    0x05 => "Thick",
    0x06 => "Double",
    0x07 => "Hair",
    0x08 => "Medium dashed",
    0x09 => "Thin dash-dotted",
    0x0A => "Medium dash-dotted",
    0x0B => "Thin dash-dot-dotted",
    0x0C => "Medium dash-dot-dotted",
    0x0D => "Slanted medium dash-dotted",
  ];

  /**
   * The sn.
   */
  private string|int $sn;

  /**
   * Whether to store extended info.
   */
  private bool $shouldStoreExtendedInfo;

  /**
   * The encoder function.
   */
  private string $encoderFunction;

  /**
   * The nineteeen four.
   */
  private bool $nineteenFour;

  /**
   * Constructs a new instance.
   */
  public function __construct(string $file = '', bool $shouldStoreExtendedInfo = TRUE, string $outputEncoding = '') {
    $this->oleRead = new OLERead();

    $this->setUtfEncoder();
    if ($outputEncoding !== '') {
      $this->setOutputEncoding($outputEncoding);
    }

    for ($index = 1; $index < 245; $index++) {
      $name = strtolower(((($index - 1) / 26 >= 1) ? chr((int) (($index - 1) / 26 + 64)) : '') . chr(($index - 1) % 26 + 65));
      $this->colNames[$name] = $index;
      $this->colIndexes[$index] = $name;
    }

    $this->shouldStoreExtendedInfo = $shouldStoreExtendedInfo;
    if ($file !== "") {
      $this->read($file);
    }
  }

  /**
   * Sets the encoding method.
   *
   * $encoder = 'iconv' or 'mb'
   * Set iconv if you would like use 'iconv' for encode UTF-16LE to your
   * encoding set mb if you would like use 'mb_convert_encoding' for encode
   * UTF-16LE to your encoding.
   */
  public function setUtfEncoder(string $encoder = 'iconv'): void {
    $this->encoderFunction = '';
    if ($encoder === 'iconv') {
      $this->encoderFunction = function_exists('iconv') ? 'iconv' : '';
    }
    elseif ($encoder === 'mb') {
      $this->encoderFunction = function_exists('mb_convert_encoding') ? 'mb_convert_encoding' : '';
    }
  }

  /**
   * Custom hex handling.
   */
  private function encodeDigitWithHex(int $digit): string {
    if ($digit < 16) {
      return "0" . dechex($digit);
    }
    return dechex($digit);
  }

  /**
   * Dumps the hex contents of the string.
   */
  private function dumpHexData(string $data, int $pos, int $length): string {
    $info = "";
    for ($index = 0; $index <= $length; $index++) {
      $info .= ($index === 0 ? "" : " ") . $this->encodeDigitWithHex(ord($data[$pos + $index])) . (ord($data[$pos + $index]) > 31 ? "[" . $data[$pos + $index] . "]" : '');
    }
    return $info;
  }

  /**
   * Read a 16-bit string from the current position.
   */
  private function read16BitString(string $data, int $start): string {
    $len = 0;
    while (ord($data[$start + $len]) + ord($data[$start + $len + 1]) > 0) {
      $len++;
    }
    return substr($data, $start, $len);
  }

  /**
   * ADDED by Matt Kruse for better formatting.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  private function formatValue(int|string|float $format, int|float $num, int|string|float $f): array {
    // 49==TEXT format
    // http://code.google.com/p/php-excel-reader/issues/detail?id=7
    if ((!$f && $format === "%s") || ($f === 49) || ($format === "GENERAL")) {
      return ['string' => $num, 'formatColor' => NULL];
    }

    // Custom pattern can be POSITIVE;NEGATIVE;ZERO
    // The "text" option as 4th parameter is not handled.
    $parts = explode(";", (string) $format);
    $pattern = $parts[0] ?? '';
    // Negative pattern.
    if (count($parts) > 2 && $num === 0) {
      $pattern = $parts[2] ?? '';
    }
    // Zero pattern.
    if (count($parts) > 1 && $num < 0) {
      $pattern = $parts[1] ?? '';
      $num = (int) abs($num);
    }

    $color = "";
    $matches = [];
    $color_regex = "/^\[(BLACK|BLUE|CYAN|GREEN|MAGENTA|RED|WHITE|YELLOW)\]/i";
    if (preg_match($color_regex, $pattern, $matches)) {
      $color = strtolower($matches[1]);
      $pattern = (string) preg_replace($color_regex, "", $pattern);
    }

    // In Excel formats, "_" is used to add spacing, which we can't do in HTML.
    $pattern = (string) preg_replace("/_./", "", $pattern);

    // Some non-number characters are escaped with \, which we don't need.
    $pattern = (string) preg_replace("/\\\/", "", $pattern);

    // Some non-number strings are quoted, so we'll get rid of the quotes.
    $pattern = (string) preg_replace("/\"/", "", $pattern);

    // TEMPORARY - Convert # to 0.
    $pattern = (string) preg_replace("/\#/", "0", $pattern);

    // Find out if we need comma formatting.
    $has_commas = str_contains($pattern, ",");
    if ($has_commas) {
      $pattern = (string) preg_replace("/,/", "", $pattern);
    }

    // Handle Percentages.
    if (preg_match("/\d(\%)([^\%]|$)/", $pattern, $matches)) {
      $num *= 100;
      $pattern = (string) preg_replace("/(\d)(\%)([^\%]|$)/", "$1%$3", $pattern);
    }

    // Handle the number itself.
    $number_regex = "/(\d+)(\.?)(\d*)/";
    if (preg_match($number_regex, $pattern, $matches)) {
      $right = $matches[3];
      if ($has_commas) {
        $formatted = number_format($num, strlen($right));
      }
      else {
        $sprintf_pattern = "%1." . strlen($right) . "f";
        $formatted = sprintf($sprintf_pattern, $num);
      }
      $pattern = (string) preg_replace($number_regex, $formatted, $pattern);
    }

    return [
      'string' => $pattern,
      'formatColor' => $color,
    ];
  }

  /**
   * Set the encoding method.
   */
  private function setOutputEncoding(string $encoding): void {
    $this->defaultEncoding = $encoding;
  }

  /**
   * Read the spreadsheet file using OLE, then parse.
   */
  private function read(string $filename): void {
    $res = $this->oleRead->read($filename);

    // Oops, something goes wrong (Darko Miljanovic).
    if (($res === FALSE) && $this->oleRead->error === 1) {
      throw new FileNotReadableException($filename);
    }

    $this->data = $this->oleRead->getWorkBook();
    $this->parse();
  }

  /**
   * Parse a workbook.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  private function parse(): bool {
    $pos = 0;
    $data = $this->data;

    $length = v($data, $pos + 2);
    $version = v($data, $pos + 4);
    $substreamType = v($data, $pos + 6);

    if (($version !== SPREADSHEET_EXCEL_READER_BIFF8) &&
    ($version !== SPREADSHEET_EXCEL_READER_BIFF7)) {
      return FALSE;
    }

    if ($substreamType !== SPREADSHEET_EXCEL_READER_WORKBOOKGLOBALS) {
      return FALSE;
    }

    $pos += $length + 4;

    $code = v($data, $pos);
    $length = v($data, $pos + 2);

    $formattingRuns = 0;
    $extendedRunLength = 0;
    while ($code !== SPREADSHEET_EXCEL_READER_TYPE_EOF) {
      switch ($code) {
        case SPREADSHEET_EXCEL_READER_TYPE_SST:
          $spos = $pos + 4;
          $limitpos = $spos + $length;
          $uniqueStrings = $this->getInt4d($data, $spos + 4);
          $spos += 8;
          for ($i = 0; $i < $uniqueStrings; $i++) {
            // Read in the number of characters.
            if ($spos === $limitpos) {
              $opcode = v($data, $spos);
              $conlength = v($data, $spos + 2);
              if ($opcode !== 0x3c) {
                return FALSE;
              }
              $spos += 4;
              $limitpos = $spos + $conlength;
            }
            $numChars = ord($data[$spos]) | (ord($data[$spos + 1]) << 8);
            $spos += 2;
            $optionFlags = ord($data[$spos]);
            $spos++;
            $asciiEncoding = (($optionFlags & 0x01) === 0);
            $extendedString = (($optionFlags & 0x04) !== 0);

            // See if string contains formatting information.
            $richString = (($optionFlags & 0x08) !== 0);

            if ($richString) {
              // Read in the crun.
              $formattingRuns = v($data, $spos);
              $spos += 2;
            }

            if ($extendedString) {
              // Read in cchExtRst.
              $extendedRunLength = $this->getInt4d($data, $spos);
              $spos += 4;
            }

            $len = ($asciiEncoding) ? $numChars : $numChars * 2;
            if ($spos + $len < $limitpos) {
              // @phpstan-ignore-next-line
              $retstr = substr($data, $spos, $len);
              $spos += $len;
            }
            else {
              // Found countinue.
              // @phpstan-ignore-next-line
              $retstr = substr($data, $spos, $limitpos - $spos);
              $bytesRead = $limitpos - $spos;
              $charsLeft = $numChars - (($asciiEncoding) ? $bytesRead : ($bytesRead / 2));
              $spos = $limitpos;

              while ($charsLeft > 0) {
                $opcode = v($data, $spos);
                $conlength = v($data, $spos + 2);
                if ($opcode !== 0x3c) {
                  return FALSE;
                }
                $spos += 4;
                $limitpos = $spos + $conlength;
                $option = ord($data[$spos]);
                $spos += 1;
                if ($asciiEncoding && ($option === 0)) {
                  // min($charsLeft, $conlength);.
                  $len = (int) min($charsLeft, $limitpos - $spos);
                  $retstr .= substr($data, (int) $spos, $len);
                  $charsLeft -= $len;
                  $asciiEncoding = TRUE;
                }
                elseif (!$asciiEncoding && ($option !== 0)) {
                  // min($charsLeft, $conlength);.
                  $len = (int) min($charsLeft * 2, $limitpos - $spos);
                  $retstr .= substr($data, (int) $spos, $len);
                  $charsLeft -= $len / 2;
                  $asciiEncoding = FALSE;
                }
                elseif (!$asciiEncoding && ($option === 0)) {
                  // Bummer - the string starts off as Unicode, but after the
                  // continuation it is in straightforward ASCII encoding.
                  // min($charsLeft, $conlength);.
                  $len = min($charsLeft, $limitpos - $spos);
                  for ($j = 0; $j < $len; $j++) {
                    $retstr .= $data[$spos + $j] . chr(0);
                  }
                  $charsLeft -= $len;
                  $asciiEncoding = FALSE;
                }
                else {
                  $newstr = '';
                  for ($j = 0, $jMax = strlen($retstr); $j < $jMax; $j++) {
                    $newstr = $retstr[$j] . chr(0);
                  }
                  $retstr = $newstr;
                  // min($charsLeft, $conlength);.
                  $len = (int) min($charsLeft * 2, $limitpos - $spos);
                  $retstr .= substr($data, (int) $spos, $len);
                  $charsLeft -= $len / 2;
                  $asciiEncoding = FALSE;
                }
                $spos += $len;
              }
            }
            $retstr = ($asciiEncoding) ? $retstr : $this->encodeUtf16($retstr);

            if ($richString) {
              $spos += 4 * $formattingRuns;
            }

            // For extended strings, skip over the extended string data.
            if ($extendedString) {
              $spos += $extendedRunLength;
            }
            $this->sst[] = $retstr;
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_FILEPASS:
          return FALSE;

        case SPREADSHEET_EXCEL_READER_TYPE_NAME:
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_FORMAT:
          $indexCode = v($data, $pos + 4);
          if ($version === SPREADSHEET_EXCEL_READER_BIFF8) {
            $numchars = v($data, $pos + 6);
            if (ord($data[$pos + 8]) === 0) {
              $formatString = substr($data, $pos + 9, $numchars);
            }
            else {
              $formatString = substr($data, $pos + 9, $numchars * 2);
            }
          }
          else {
            $numchars = ord($data[$pos + 6]);
            $formatString = substr($data, $pos + 7, $numchars * 2);
          }
          $this->formatRecords[$indexCode] = $formatString;
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_FONT:
          $height = v($data, $pos + 4);
          $option = v($data, $pos + 6);
          $color = v($data, $pos + 8);
          $weight = v($data, $pos + 10);
          $under = ord($data[$pos + 14]);
          // Font name.
          $numchars = ord($data[$pos + 18]);
          if ((ord($data[$pos + 19]) & 1) === 0) {
            $font = substr($data, $pos + 20, $numchars);
          }
          else {
            $font = substr($data, $pos + 20, $numchars * 2);
            $font = $this->encodeUtf16($font);
          }
          $this->fontRecords[] = [
            'height' => $height / 20,
            'italic' => (bool) ($option & 2),
            'color' => $color,
            'under' => !($under === 0),
            'bold' => ($weight === 700),
            'font' => $font,
            'raw' => $this->dumpHexData($data, $pos + 3, $length),
          ];
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_XF:
          $fontIndexCode = (ord($data[$pos + 4]) | ord($data[$pos + 5]) << 8) - 1;
          $fontIndexCode = max(0, $fontIndexCode);
          $indexCode = ord($data[$pos + 6]) | ord($data[$pos + 7]) << 8;
          $alignbit = ord($data[$pos + 10]) & 3;
          $bgi = (ord($data[$pos + 22]) | ord($data[$pos + 23]) << 8) & 0x3FFF;
          $bgcolor = ($bgi & 0x7F);
          // $bgcolor = ($bgi & 0x3f80) >> 7;
          $align = "";
          if ($alignbit === 3) {
            $align = "right";
          }
          if ($alignbit === 2) {
            $align = "center";
          }

          $fillPattern = (ord($data[$pos + 21]) & 0xFC) >> 2;

          $xf = [];
          $xf['bgColor'] = $bgcolor;
          if ($fillPattern === 0) {
            $xf['bgColor'] = "";
          }

          $xf['formatIndex'] = $indexCode;
          $xf['align'] = $align;
          $xf['fontIndex'] = $fontIndexCode;
          $xf['fillPattern'] = $fillPattern;

          $border = ord($data[$pos + 14]) | (ord($data[$pos + 15]) << 8) | (ord($data[$pos + 16]) << 16) | (ord($data[$pos + 17]) << 24);
          $xf['borderLeft'] = $this->lineStyles[($border & 0xF)];
          $xf['borderRight'] = $this->lineStyles[($border & 0xF0) >> 4];
          $xf['borderTop'] = $this->lineStyles[($border & 0xF00) >> 8];
          $xf['borderBottom'] = $this->lineStyles[($border & 0xF000) >> 12];

          $xf['borderLeftColor'] = ($border & 0x7F0000) >> 16;
          $xf['borderRightColor'] = ($border & 0x3F800000) >> 23;
          $border = (ord($data[$pos + 18]) | ord($data[$pos + 19]) << 8);

          $xf['borderTopColor'] = ($border & 0x7F);
          $xf['borderBottomColor'] = ($border & 0x3F80) >> 7;

          if (array_key_exists($indexCode, $this->dateFormats)) {
            $xf['type'] = 'date';
            $xf['format'] = $this->dateFormats[$indexCode];
            if ($align === '') {
              $xf['align'] = 'right';
            }
          }
          elseif (array_key_exists($indexCode, $this->numberFormats)) {
            $xf['type'] = 'number';
            $xf['format'] = $this->numberFormats[$indexCode];
            if ($align === '') {
              $xf['align'] = 'right';
            }
          }
          else {
            $isdate = FALSE;
            $formatstr = '';
            if ($indexCode > 0) {
              if (isset($this->formatRecords[$indexCode])) {
                $formatstr = $this->formatRecords[$indexCode];
              }
              if ($formatstr !== "") {
                $tmp = preg_replace("/\;.*/", "", $formatstr);
                $tmp = preg_replace("/^\[[^\]]*\]/", "", $tmp);
                // Found day and time format.
                // @phpstan-ignore-next-line
                if (preg_match("/[^hmsday\/\-:\s\\\,AMP]/i", $tmp) === 0) {
                  $isdate = TRUE;
                  $formatstr = $tmp;
                  // @phpstan-ignore-next-line
                  $formatstr = str_replace(['AM/PM', 'mmmm', 'mmm'], ['a', 'F', 'M'], $formatstr);
                  // m/mm are used for both minutes and months - oh SNAP!
                  // This mess tries to fix for that.
                  // 'm' === minutes only if following h/hh or preceding s/ss.
                  $formatstr = preg_replace("/(h:?)mm?/", "$1i", $formatstr);
                  // @phpstan-ignore-next-line
                  $formatstr = preg_replace("/mm?(:?s)/", "i$1", $formatstr);
                  // A single 'm' = n in PHP.
                  // @phpstan-ignore-next-line
                  $formatstr = preg_replace("/(^|[^m])m([^m]|$)/", '$1n$2', $formatstr);
                  // @phpstan-ignore-next-line
                  $formatstr = preg_replace("/(^|[^m])m([^m]|$)/", '$1n$2', $formatstr);
                  // Else it's months.
                  // @phpstan-ignore-next-line
                  $formatstr = str_replace('mm', 'm', $formatstr);
                  // Convert single 'd' to 'j'.
                  $formatstr = preg_replace("/(^|[^d])d([^d]|$)/", '$1j$2', $formatstr);
                  $formatstr = str_replace(
                    ['dddd', 'ddd', 'dd', 'yyyy', 'yy', 'hh', 'h'],
                    ['l', 'D', 'd', 'Y', 'y', 'H', 'g'],
                    // @phpstan-ignore-next-line
                    $formatstr
                  );
                  $formatstr = preg_replace("/ss?/", 's', $formatstr);
                }
              }
            }
            if ($isdate) {
              $xf['type'] = 'date';
              $xf['format'] = $formatstr;
              if ($align === '') {
                $xf['align'] = 'right';
              }
            }
            else {
              // If the format string has a 0 or # in it, we'll assume it's a
              // number.
              if (preg_match("/[0#]/", $formatstr)) {
                $xf['type'] = 'number';
                if ($align === '') {
                  $xf['align'] = 'right';
                }
              }
              else {
                $xf['type'] = 'other';
              }
              $xf['format'] = $formatstr;
              $xf['code'] = $indexCode;
            }
          }
          $this->xfRecords[] = $xf;
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_NINETEENFOUR:
          $this->nineteenFour = (ord($data[$pos + 4]) === 1);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET:
          $rec_offset = $this->getInt4d($data, $pos + 4);
          $rec_length = ord($data[$pos + 10]);

          $rec_name = substr($data, $pos + 11, $rec_length);
          if ($version === SPREADSHEET_EXCEL_READER_BIFF8) {
            $chartype = ord($data[$pos + 11]);
            if ($chartype === 0) {
              $rec_name = substr($data, $pos + 12, $rec_length);
            }
            else {
              $rec_name = $this->encodeUtf16(substr($data, $pos + 12, $rec_length * 2));
            }
          }
          $this->boundSheets[] = ['name' => $rec_name, 'offset' => $rec_offset];
          break;
      }

      $pos += $length + 4;
      $code = ord($data[$pos]) | ord($data[$pos + 1]) << 8;
      $length = ord($data[$pos + 2]) | ord($data[$pos + 3]) << 8;
    }

    foreach ($this->boundSheets as $key => $val) {
      $this->sn = $key;
      $this->parseSheet($val['offset']);
    }

    return TRUE;
  }

  /**
   * Parse a worksheet.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  private function parseSheet(int $spos): int {
    $cont = TRUE;
    $data = $this->data;
    // Read BOF.
    $length = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;

    $version = ord($data[$spos + 4]) | ord($data[$spos + 5]) << 8;
    $substreamType = ord($data[$spos + 6]) | ord($data[$spos + 7]) << 8;

    if (($version !== SPREADSHEET_EXCEL_READER_BIFF8) && ($version !== SPREADSHEET_EXCEL_READER_BIFF7)) {
      return -1;
    }

    if ($substreamType !== SPREADSHEET_EXCEL_READER_WORKSHEET) {
      return -2;
    }

    $spos += $length + 4;
    $previousCol = 0;
    $previousRow = 0;
    while ($cont) {
      $lowcode = ord($data[$spos]);
      if ($lowcode === SPREADSHEET_EXCEL_READER_TYPE_EOF) {
        break;
      }
      $code = $lowcode | ord($data[$spos + 1]) << 8;
      $length = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
      $spos += 4;
      $this->sheets[$this->sn]['maxrow'] = $this->rowOffset - 1;
      $this->sheets[$this->sn]['maxcol'] = $this->columnOffset - 1;
      switch ($code) {
        case SPREADSHEET_EXCEL_READER_TYPE_DIMENSION:
          if (!isset($this->sheets[$this->sn]['numRows'])) {
            if (($length === 10) || ($version === SPREADSHEET_EXCEL_READER_BIFF7)) {
              $this->sheets[$this->sn]['numRows'] = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
              $this->sheets[$this->sn]['numCols'] = ord($data[$spos + 6]) | ord($data[$spos + 7]) << 8;
            }
            else {
              $this->sheets[$this->sn]['numRows'] = ord($data[$spos + 4]) | ord($data[$spos + 5]) << 8;
              $this->sheets[$this->sn]['numCols'] = ord($data[$spos + 10]) | ord($data[$spos + 11]) << 8;
            }
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_MERGEDCELLS:
          $cellRanges = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          for ($i = 0; $i < $cellRanges; $i++) {
            $fr = ord($data[$spos + 8 * $i + 2]) | ord($data[$spos + 8 * $i + 3]) << 8;
            $lr = ord($data[$spos + 8 * $i + 4]) | ord($data[$spos + 8 * $i + 5]) << 8;
            $fc = ord($data[$spos + 8 * $i + 6]) | ord($data[$spos + 8 * $i + 7]) << 8;
            $lc = ord($data[$spos + 8 * $i + 8]) | ord($data[$spos + 8 * $i + 9]) << 8;
            if ($lr - $fr > 0) {
              $this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['rowspan'] = $lr - $fr + 1;
            }
            if ($lc - $fc > 0) {
              $this->sheets[$this->sn]['cellsInfo'][$fr + 1][$fc + 1]['colspan'] = $lc - $fc + 1;
            }
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_RK:
        case SPREADSHEET_EXCEL_READER_TYPE_RK2:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $rknum = $this->getInt4d($data, $spos + 6);
          $numValue = $this->getIEEE754($rknum);
          $info = $this->getCellDetails($spos, $numValue, $column);
          $this->addCell($row, $column, $info['string'], $info);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_LABELSST:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $xfindex = ord($data[$spos + 4]) | ord($data[$spos + 5]) << 8;
          $index = $this->getInt4d($data, $spos + 6);
          $this->addCell($row, $column, $this->sst[$index], ['xfIndex' => $xfindex]);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_MULRK:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $colFirst = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $colLast = ord($data[$spos + $length - 2]) | ord($data[$spos + $length - 1]) << 8;
          $columns = $colLast - $colFirst + 1;
          $tmppos = $spos + 4;
          for ($i = 0; $i < $columns; $i++) {
            $numValue = $this->getIEEE754($this->getInt4d($data, $tmppos + 2));
            $info = $this->getCellDetails($tmppos - 4, $numValue, $colFirst + $i + 1);
            $tmppos += 6;
            $this->addCell($row, $colFirst + $i, $info['string'], $info);
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_NUMBER:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          // It machine dependent.
          $tmp = unpack("ddouble", substr($data, $spos + 6, 8));
          if ($this->isDate($spos)) {
            $numValue = $tmp['double'] ?? 0;
          }
          else {
            $numValue = $this->createNumber($spos);
          }
          $info = $this->getCellDetails($spos, $numValue, $column);
          $this->addCell($row, $column, $info['string'], $info);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_FORMULA:
        case SPREADSHEET_EXCEL_READER_TYPE_FORMULA2:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          if ((ord($data[$spos + 6]) === 0) && (ord($data[$spos + 12]) === 255) && (ord($data[$spos + 13]) === 255)) {
            // String formula. Result follows in a STRING record
            // This row/col are stored to be referenced in that record
            // http://code.google.com/p/php-excel-reader/issues/detail?id=4
            $previousRow = $row;
            $previousCol = $column;
          }
          elseif ((ord($data[$spos + 6]) === 1) && (ord($data[$spos + 12]) === 255) && (ord($data[$spos + 13]) === 255)) {
            // Boolean formula. Result is in +2; 0=false,1=true
            // http://code.google.com/p/php-excel-reader/issues/detail?id=4
            if (ord($this->data[$spos + 8]) === 1) {
              $this->addCell($row, $column, "TRUE");
            }
            else {
              $this->addCell($row, $column, "FALSE");
            }
          }
          elseif ((ord($data[$spos + 6]) === 3) && (ord($data[$spos + 12]) === 255) && (ord($data[$spos + 13]) === 255)) {
            // Formula result is a null string.
            $this->addCell($row, $column, '');
          }
          else {
            // Result is a number, so first 14 bytes are just like a
            // _NUMBER record. It is machine dependent.
            $tmp = unpack("ddouble", substr($data, $spos + 6, 8));
            if ($this->isDate($spos)) {
              $numValue = $tmp['double'] ?? 0;
            }
            else {
              $numValue = $this->createNumber($spos);
            }
            $info = $this->getCellDetails($spos, $numValue, $column);
            $this->addCell($row, $column, $info['string'], $info);
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_BOOLERR:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $string = ord($data[$spos + 6]);
          $this->addCell($row, $column, $string);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_STRING:
          // http://code.google.com/p/php-excel-reader/issues/detail?id=4
          if ($version === SPREADSHEET_EXCEL_READER_BIFF8) {
            // Unicode 16 string, like an SST record.
            $xpos = $spos;
            $numChars = ord($data[$xpos]) | (ord($data[$xpos + 1]) << 8);
            $xpos += 2;
            $optionFlags = ord($data[$xpos]);
            $xpos++;
            $asciiEncoding = (($optionFlags & 0x01) === 0);
            $extendedString = (($optionFlags & 0x04) !== 0);
            // See if string contains formatting information.
            $richString = (($optionFlags & 0x08) !== 0);
            if ($richString) {
              $xpos += 2;
            }
            if ($extendedString) {
              $xpos += 4;
            }
            $len = ($asciiEncoding) ? $numChars : $numChars * 2;
            $retstr = substr($data, $xpos, $len);
            $retstr = ($asciiEncoding) ? $retstr : $this->encodeUtf16($retstr);
          }
          // @phpstan-ignore-next-line
          elseif ($version === SPREADSHEET_EXCEL_READER_BIFF7) {
            // Simple byte string.
            $xpos = $spos;
            $numChars = ord($data[$xpos]) | (ord($data[$xpos + 1]) << 8);
            $xpos += 2;
            $retstr = substr($data, $xpos, $numChars);
          }
          $this->addCell($previousRow, $previousCol, $retstr);
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_ROW:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $rowInfo = ord($data[$spos + 6]) | ((ord($data[$spos + 7]) << 8) & 0x7FFF);
          if (($rowInfo & 0x8000) > 0) {
            $rowHeight = -1;
          }
          else {
            $rowHeight = $rowInfo & 0x7FFF;
          }
          $rowHidden = (ord($data[$spos + 12]) & 0x20) >> 5;
          $this->rowInfo[$this->sn][$row + 1] = ['height' => $rowHeight / 20, 'hidden' => $rowHidden];
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_MULBLANK:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $cols = ($length / 2) - 3;
          for ($c = 0; $c < $cols; $c++) {
            $xfindex = ord($data[$spos + 4 + ($c * 2)]) | ord($data[$spos + 5 + ($c * 2)]) << 8;
            $this->addCell($row, $column + $c, "", ['xfIndex' => $xfindex]);
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_LABEL:
          $row = ord($data[$spos]) | ord($data[$spos + 1]) << 8;
          $column = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $this->addCell($row, $column, substr($data, $spos + 8, ord($data[$spos + 6]) | ord($data[$spos + 7]) << 8));
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_EOF:
          $cont = FALSE;
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_HYPER:
          // Only handle hyperlinks to a URL.
          $row = ord($this->data[$spos]) | ord($this->data[$spos + 1]) << 8;
          $row2 = ord($this->data[$spos + 2]) | ord($this->data[$spos + 3]) << 8;
          $column = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
          $column2 = ord($this->data[$spos + 6]) | ord($this->data[$spos + 7]) << 8;
          $linkdata = [];
          $flags = ord($this->data[$spos + 28]);
          $udesc = "";
          $ulink = "";
          $uloc = 32;
          $linkdata['flags'] = $flags;
          // Is a type we understand.
          if (($flags & 1) > 0) {
            // Is there a description ?
            // has a description.
            if (($flags & 0x14) === 0x14) {
              $uloc += 4;
              $descLen = ord($this->data[$spos + 32]) | ord($this->data[$spos + 33]) << 8;
              $udesc = substr($this->data, $spos + $uloc, $descLen * 2);
              $uloc += 2 * $descLen;
            }
            $ulink = $this->read16BitString($this->data, $spos + $uloc + 20);
            if ($udesc === "") {
              $udesc = $ulink;
            }
          }
          $linkdata['desc'] = $udesc;
          $linkdata['link'] = $this->encodeUtf16($ulink);
          for ($r = $row; $r <= $row2; $r++) {
            for ($c = $column; $c <= $column2; $c++) {
              $this->sheets[$this->sn]['cellsInfo'][$r + 1][$c + 1]['hyperlink'] = $linkdata;
            }
          }
          break;

        case SPREADSHEET_EXCEL_READER_TYPE_COLINFO:
          $colfrom = ord($data[$spos + 0]) | ord($data[$spos + 1]) << 8;
          $colto = ord($data[$spos + 2]) | ord($data[$spos + 3]) << 8;
          $cw = ord($data[$spos + 4]) | ord($data[$spos + 5]) << 8;
          $cxf = ord($data[$spos + 6]) | ord($data[$spos + 7]) << 8;
          $co = ord($data[$spos + 8]);
          for ($coli = $colfrom; $coli <= $colto; $coli++) {
            $this->colInfo[$this->sn][$coli + 1] = [
              'width' => $cw,
              'xf' => $cxf,
              'hidden' => ($co & 0x01),
              'collapsed' => ($co & 0x1000) >> 12,
            ];
          }
          break;

        default:
          break;
      }
      $spos += $length;
    }

    if (!isset($this->sheets[$this->sn]['numRows'])) {
      $this->sheets[$this->sn]['numRows'] = $this->sheets[$this->sn]['maxrow'];
    }
    if (!isset($this->sheets[$this->sn]['numCols'])) {
      $this->sheets[$this->sn]['numCols'] = $this->sheets[$this->sn]['maxcol'];
    }

    return 0;
  }

  /**
   * Determine if a cell contains a date.
   */
  private function isDate(int $spos): bool {
    $xfindex = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
    return ($this->xfRecords[$xfindex]['type'] === 'date');
  }

  /**
   * Get the details for a particular cell.
   */
  private function getCellDetails(int $spos, int|float $numValue, int $column): array {
    $xfindex = ord($this->data[$spos + 4]) | ord($this->data[$spos + 5]) << 8;
    $xfrecord = $this->xfRecords[$xfindex];
    $type = $xfrecord['type'];

    $format = $xfrecord['format'];
    $formatIndex = $xfrecord['formatIndex'];
    $fontIndex = $xfrecord['fontIndex'];
    $formatColor = "";

    if (isset($this->columnsFormat[$column + 1])) {
      $format = $this->columnsFormat[$column + 1];
    }

    if ($type === 'date') {
      // See http://groups.google.com/group/php-excel-reader-discuss/browse_frm/thread/9c3f9790d12d8e10/f2045c2369ac79de
      $rectype = 'date';
      // Convert numeric value into a date.
      $utcDays = floor($numValue - ($this->nineteenFour ? SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS1904 : SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS));
      $utcValue = ($utcDays) * SPREADSHEET_EXCEL_READER_MSINADAY;
      $dateinfo = gm_get_date($utcValue);

      $raw = $numValue;
      // The .0000001 is to fix for php/excel fractional diffs.
      $fractionalDay = $numValue - floor($numValue) + .0000001;

      $totalseconds = floor(SPREADSHEET_EXCEL_READER_MSINADAY * $fractionalDay);
      $secs = $totalseconds % 60;
      $totalseconds -= $secs;
      $hours = (int) floor($totalseconds / (60 * 60));
      $mins = (int) floor($totalseconds / 60) % 60;
      $string = date($format, (int) mktime($hours, $mins, $secs, $dateinfo["mon"], $dateinfo["mday"], $dateinfo["year"]));
    }
    elseif ($type === 'number') {
      $rectype = 'number';
      $formatted = $this->formatValue($format, $numValue, $formatIndex);
      $string = $formatted['string'];
      $formatColor = $formatted['formatColor'];
      $raw = $numValue;
    }
    else {
      if ($format === "") {
        $format = $this->defaultFormat;
      }
      $rectype = 'unknown';
      $formatted = $this->formatValue($format, $numValue, $formatIndex);
      $string = $formatted['string'];
      $formatColor = $formatted['formatColor'];
      $raw = $numValue;
    }

    return [
      'string' => $string,
      'raw' => $raw,
      'rectype' => $rectype,
      'format' => $format,
      'formatIndex' => $formatIndex,
      'fontIndex' => $fontIndex,
      'formatColor' => $formatColor,
      'xfIndex' => $xfindex,
    ];
  }

  /**
   * Get the details for a particular cell.
   */
  private function createNumber(int $stringPosition): float|int {
    $rknumhigh = $this->getInt4d($this->data, $stringPosition + 10);
    $rknumlow = $this->getInt4d($this->data, $stringPosition + 6);
    $sign = ($rknumhigh & 0x80000000) >> 31;
    $exp = ($rknumhigh & 0x7ff00000) >> 20;
    $mantissa = (0x100000 | ($rknumhigh & 0x000fffff));
    $mantissalow1 = ($rknumlow & 0x80000000) >> 31;
    $mantissalow2 = ($rknumlow & 0x7fffffff);
    $value = $mantissa / (2 ** (20 - ($exp - 1023)));
    if ($mantissalow1 !== 0) {
      $value += 1 / (2 ** (21 - ($exp - 1023)));
    }
    $value += $mantissalow2 / (2 ** (52 - ($exp - 1023)));
    if ($sign) {
      $value = -1 * $value;
    }
    return $value;
  }

  /**
   * Get the value for a particular cell.
   */
  private function addCell(int $row, int $col, string|int|float $string, ?array $info = NULL): void {
    $this->sheets[$this->sn]['maxrow'] = max($this->sheets[$this->sn]['maxrow'], $row + $this->rowOffset);
    $this->sheets[$this->sn]['maxcol'] = max($this->sheets[$this->sn]['maxcol'], $col + $this->columnOffset);
    $this->sheets[$this->sn]['cells'][$row + $this->rowOffset][$col + $this->columnOffset] = $string;
    if ($this->shouldStoreExtendedInfo && $info !== NULL && $info !== []) {
      foreach ($info as $key => $val) {
        $this->sheets[$this->sn]['cellsInfo'][$row + $this->rowOffset][$col + $this->columnOffset][$key] = $val;
      }
    }
  }

  /**
   * Get the value for a particular cell.
   */
  private function getIEEE754(int $rknum): int|float { // phpcs:ignore
    if (($rknum & 0x02) !== 0) {
      $value = $rknum >> 2;
    }
    else {
      // mmp
      // I got my info on IEEE754 encoding from
      // http://research.microsoft.com/~hollasch/cgindex/coding/ieeefloat.html
      // The RK format calls for using only the most significant 30 bits of the
      // 64 bit floating point value. The other 34 bits are assumed to be 0
      // So, we use the upper 30 bits of $rknum as follows...
      $sign = ($rknum & 0x80000000) >> 31;
      $exp = ($rknum & 0x7ff00000) >> 20;
      $mantissa = (0x100000 | ($rknum & 0x000ffffc));
      $value = $mantissa / (2 ** (20 - ($exp - 1023)));
      if ($sign) {
        $value = -1 * $value;
      }
      // End of changes by mmp.
    }
    if (($rknum & 0x01) !== 0) {
      $value /= 100;
    }
    return $value;
  }

  /**
   * Convert a string from UTF-16LE to a specific encoding.
   */
  private function encodeUtf16(string $string): string {
    $result = $string;
    if ($this->defaultEncoding) {
      switch ($this->encoderFunction) {
        case 'iconv':
          $result = (string) iconv('UTF-16LE', $this->defaultEncoding, $string);
          break;

        case 'mb_convert_encoding':
          $result = (string) mb_convert_encoding($string, $this->defaultEncoding, 'UTF-16LE');
          break;
      }
    }
    return $result;
  }

  /**
   * Convert a number into a column name.
   */
  private function getInt4d(string $data, int|float $pos): int {
    $value = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
    if ($value >= 4294967294) {
      $value = -2;
    }
    return $value;
  }

}
