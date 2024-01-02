<?php
namespace KoenVanMeijeren\SpreadsheetReader;

use KoenVanMeijeren\SpreadsheetReader\Exceptions\FileNotReadableException;
use RuntimeException;

/**
 * Class for parsing CSV files
 *
 * @version 1.0
 * @author KoenVanMeijeren
 */
class SpreadsheetReaderCSV implements SpreadsheetReaderInterface
{
    /**
     * Options array, pre-populated with the default values.
     */
    private array $Options = [
        'Delimiter' => ';',
        'Enclosure' => '"',
    ];

    private string $Encoding = 'UTF-8';
    private int $BOMLength = 0;

    /**
     * @var resource File handle
     */
    private $Handle;

    private string $Filepath;

    private int $currentRowIndex = 0;

    private mixed $currentRow = null;

    /**
     * Constructs a new spreadsheet reader for CSV files.
     *
     * @param string $filepath Path to file
     * @param array $options Options:
     *    Enclosure => string CSV enclosure
     *    Separator => string CSV separator
     *
     * @throws FileNotReadableException
     */
    public function __construct(string $filepath, array $options = [])
    {
        $this->Filepath = $filepath;

        if (!is_readable($filepath)) {
            throw new FileNotReadableException($filepath);
        }

        $this->Options = array_merge($this->Options, $options);
        $this->Handle = fopen($filepath, 'rb');

        // Checking the file for byte-order mark to determine encoding
        $BOM16 = bin2hex(fread($this->Handle, 2));
        if ($BOM16 == 'fffe') {
            $this->Encoding = 'UTF-16LE';
            //$this -> Encoding = 'UTF-16';
            $this->BOMLength = 2;
        } elseif ($BOM16 == 'feff') {
            $this->Encoding = 'UTF-16BE';
            //$this -> Encoding = 'UTF-16';
            $this->BOMLength = 2;
        }

        if (!$this->BOMLength) {
            fseek($this->Handle, 0);
            $BOM32 = bin2hex(fread($this->Handle, 4));
            if ($BOM32 == '0000feff') {
                //$this -> Encoding = 'UTF-32BE';
                $this->Encoding = 'UTF-32';
                $this->BOMLength = 4;
            } elseif ($BOM32 == 'fffe0000') {
                //$this -> Encoding = 'UTF-32LE';
                $this->Encoding = 'UTF-32';
                $this->BOMLength = 4;
            }
        }

        fseek($this->Handle, 0);
        $BOM8 = bin2hex(fread($this->Handle, 3));
        if ($BOM8 == 'efbbbf') {
            $this->Encoding = 'UTF-8';
            $this->BOMLength = 3;
        }

        // Seeking the place right after BOM as the start of the real content
        if ($this->BOMLength) {
            fseek($this->Handle, $this->BOMLength);
        }

        // Checking for the delimiter if it should be determined automatically
        if (!$this->Options['Delimiter']) {
            // fgetcsv needs single-byte separators
            $Semicolon = ';';
            $Tab = "\t";
            $Comma = ',';

            // Reading the first row and checking if a specific separator character
            // has more columns than others (it means that most likely that is the delimiter).
            $SemicolonCount = count(fgetcsv($this->Handle, null, $Semicolon));
            fseek($this->Handle, $this->BOMLength);
            $TabCount = count(fgetcsv($this->Handle, null, $Tab));
            fseek($this->Handle, $this->BOMLength);
            $CommaCount = count(fgetcsv($this->Handle, null, $Comma));
            fseek($this->Handle, $this->BOMLength);

            $Delimiter = $Semicolon;
            if ($TabCount > $SemicolonCount || $CommaCount > $SemicolonCount) {
                $Delimiter = $CommaCount > $TabCount ? $Comma : $Tab;
            }

            $this->Options['Delimiter'] = $Delimiter;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Because CSV doesn't have any, it's just a single entry.
     */
    public function sheets(): array
    {
        return array(0 => basename($this->Filepath));
    }

    /**
     * {@inheritdoc}
     */
    public function changeSheet(int $index): bool
    {
        if ($index === 0) {
            $this->rewind();
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        fseek($this->Handle, $this->BOMLength);
        $this->currentRow = null;
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

    public function next(): void
    {
        $this->currentRow = array();

        // Finding the place the next line starts for UTF-16 encoded files
        // Line breaks could be 0x0D 0x00 0x0A 0x00 and PHP could split lines on the
        //	first or the second linebreak, leaving unnecessary \0 characters that mess up
        //	the output.
        if ($this->Encoding === 'UTF-16LE' || $this->Encoding === 'UTF-16BE') {
            while (!feof($this->Handle)) {
                // While bytes are insignificant whitespace, do nothing
                $character = ord(fgetc($this->Handle));
                if ($character === 10 || $character === 13) {
                    continue;
                }

                // When significant bytes are found, step back to the last place before them
                if ($this->Encoding === 'UTF-16LE') {
                    fseek($this->Handle, ftell($this->Handle) - 1);
                } else {
                    fseek($this->Handle, ftell($this->Handle) - 2);
                }
                break;
            }
        }

        $this->currentRowIndex++;
        $this->currentRow = fgetcsv($this->Handle, null, $this->Options['Delimiter'], $this->Options['Enclosure']);

        // Converting multibyte unicode strings
        // and trimming enclosure symbols off of them because those aren't recognized
        // in the relevant encodings.
        if ($this->currentRow && $this->Encoding !== 'ASCII' && $this->Encoding !== 'UTF-8') {
            foreach ($this->currentRow as $Key => $Value) {
                $this->currentRow[$Key] = trim(trim(
                    mb_convert_encoding($Value, 'UTF-8', $this->Encoding),
                    $this->Options['Enclosure']
                ));
            }

        }
    }

    public function key(): int
    {
        return $this->currentRowIndex;
    }

    public function valid(): bool
    {
        return ($this->currentRow || !feof($this->Handle));
    }

    public function count(): int
    {
        return $this->currentRowIndex + 1;
    }
}
