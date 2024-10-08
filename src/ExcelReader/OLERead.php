<?php

namespace KoenVanMeijeren\SpreadsheetReader\ExcelReader;

define('IDENTIFIER_OLE', pack("CCCCCCCC", 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1));

/**
 * Provides the OLERead class for reading OLE structured files.
 */
final class OLERead {

  /**
   * The data.
   */
  public string $data = '';

  /**
   * The error.
   */
  public bool $hasError = FALSE;

  /**
   * The number of big block depot blocks.
   */
  private int $numBigBlockDepotBlocks;

  /**
   * The properties.
   */
  private array $props = [];

  /**
   * The sbd start block.
   */
  private int $sbdStartBlock;

  /**
   * The root start block.
   */
  private int $rootStartBlock;

  /**
   * The big blockchain.
   */
  private array $bigBlockChain = [];

  /**
   * The extension block.
   */
  private int $extensionBlock;

  /**
   * The num extension blocks.
   */
  private int $numExtensionBlocks;

  /**
   * The small blockchain.
   */
  private array $smallBlockChain = [];

  /**
   * The current entry.
   */
  private string $entry;

  /**
   * The workbook.
   */
  private int $workbook;

  /**
   * The root entry.
   */
  private int $rootEntry;

  /**
   * Read the header of the OLE file.
   */
  public function read(string $filename): bool {
    if (!$this->isValidFile($filename)) {
      return FALSE;
    }

    $file_contents = file_get_contents($filename);
    if (!$file_contents) {
      return FALSE;
    }

    $this->data = $file_contents;
    if (!$this->isValidData()) {
      $this->hasError = TRUE;
      return FALSE;
    }

    $this->readHeaderInfo();
    $this->readBigBlockDepot();
    $this->readSmallBlockDepot();
    $this->readRootData();
    $this->readPropertySets();

    return TRUE;
  }

  /**
   * Determines if the file is a valid OLE file.
   */
  private function isValidFile(string $filename): bool {
    if (!is_readable($filename)) {
      $this->hasError = TRUE;
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Determines if the file is a valid OLE file.
   */
  private function isValidData(): bool {
    if (!str_starts_with($this->data, IDENTIFIER_OLE)) {
      $this->hasError = TRUE;
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Read the header info.
   */
  private function readHeaderInfo(): void {
    $this->numBigBlockDepotBlocks = get_int4d($this->data, NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
    $this->sbdStartBlock = get_int4d($this->data, SMALL_BLOCK_DEPOT_BLOCK_POS);
    $this->rootStartBlock = get_int4d($this->data, ROOT_START_BLOCK_POS);
    $this->extensionBlock = get_int4d($this->data, EXTENSION_BLOCK_POS);
    $this->numExtensionBlocks = get_int4d($this->data, NUM_EXTENSION_BLOCK_POS);
  }

  /**
   * Read the big block depot.
   */
  private function readBigBlockDepot(): void {
    $bigBlockDepotBlocks = [];
    $pos = BIG_BLOCK_DEPOT_BLOCKS_POS;
    $bbdBlocks = $this->numBigBlockDepotBlocks;

    if ($this->numExtensionBlocks !== 0) {
      $bbdBlocks = (BIG_BLOCK_SIZE - BIG_BLOCK_DEPOT_BLOCKS_POS) / 4;
    }

    for ($index = 0; $index < $bbdBlocks; $index++) {
      $bigBlockDepotBlocks[$index] = get_int4d($this->data, $pos);
      $pos += 4;
    }

    for ($numExtensionBlockIndex = 0; $numExtensionBlockIndex < $this->numExtensionBlocks; $numExtensionBlockIndex++) {
      $pos = ($this->extensionBlock + 1) * BIG_BLOCK_SIZE;
      $blocksToRead = min($this->numBigBlockDepotBlocks - $bbdBlocks, BIG_BLOCK_SIZE / 4 - 1);

      for ($bbdBlocksIndex = $bbdBlocks; $bbdBlocksIndex < $bbdBlocks + $blocksToRead; $bbdBlocksIndex++) {
        $bigBlockDepotBlocks[$bbdBlocksIndex] = get_int4d($this->data, $pos);
        $pos += 4;
      }

      $bbdBlocks += $blocksToRead;
      if ($bbdBlocks < $this->numBigBlockDepotBlocks) {
        $this->extensionBlock = get_int4d($this->data, $pos);
      }
    }

    $this->readBigBlockChain($bigBlockDepotBlocks);
  }

  /**
   * Read the big blockchain.
   */
  private function readBigBlockChain(array $bigBlockDepotBlocks): void {
    $index = 0;
    $this->bigBlockChain = [];

    foreach ($bigBlockDepotBlocks as $value) {
      $pos = ($value + 1) * BIG_BLOCK_SIZE;

      for ($bigBlockSize = 0; $bigBlockSize < BIG_BLOCK_SIZE / 4; $bigBlockSize++) {
        $this->bigBlockChain[$index] = get_int4d($this->data, $pos);
        $pos += 4;
        $index++;
      }
    }
  }

  /**
   * Read the small block depot.
   */
  private function readSmallBlockDepot(): void {
    $index = 0;
    $sbdBlock = $this->sbdStartBlock;
    $this->smallBlockChain = [];

    while ($sbdBlock !== -2) {
      $pos = ($sbdBlock + 1) * BIG_BLOCK_SIZE;

      for ($bigBlockSize = 0; $bigBlockSize < BIG_BLOCK_SIZE / 4; $bigBlockSize++) {
        $this->smallBlockChain[$index] = get_int4d($this->data, $pos);
        $pos += 4;
        $index++;
      }

      $sbdBlock = (int) $this->bigBlockChain[$sbdBlock];
    }
  }

  /**
   * Read the root data.
   */
  private function readRootData(): void {
    $block = $this->rootStartBlock;
    $this->entry = $this->readData($block);
  }

  /**
   * Read the data.
   */
  private function readData(int $bl): string {
    $block = $bl;
    $data = '';
    while ($block !== -2) {
      $pos = ($block + 1) * BIG_BLOCK_SIZE;
      $data .= substr($this->data, $pos, BIG_BLOCK_SIZE);
      $block = (int) $this->bigBlockChain[$block];
    }
    return $data;
  }

  /**
   * Read the property sets.
   */
  private function readPropertySets(): void {
    $offset = 0;
    while ($offset < strlen($this->entry)) {
      $d = substr($this->entry, $offset, PROPERTY_STORAGE_BLOCK_SIZE);
      $nameSize = ord($d[SIZE_OF_NAME_POS]) | (ord($d[SIZE_OF_NAME_POS + 1]) << 8);
      $type = ord($d[TYPE_POS]);
      $startBlock = get_int4d($d, START_BLOCK_POS);
      $size = get_int4d($d, SIZE_POS);
      $name = '';
      for ($i = 0; $i < $nameSize; $i++) {
        $name .= $d[$i];
      }
      $name = str_replace("\x00", "", $name);
      $this->props[] = [
        'name' => $name,
        'type' => $type,
        'startBlock' => $startBlock,
        'size' => $size,
      ];
      if ((strtolower($name) === "workbook") || (strtolower($name) === "book")) {
        $this->workbook = count($this->props) - 1;
      }
      if ($name === "Root Entry") {
        $this->rootEntry = count($this->props) - 1;
      }
      $offset += PROPERTY_STORAGE_BLOCK_SIZE;
    }

  }

  /**
   * Get the workbook.
   */
  public function getWorkBook(): string {
    if ($this->props[$this->workbook]['size'] < SMALL_BLOCK_THRESHOLD) {
      $rootdata = $this->readData($this->props[$this->rootEntry]['startBlock']);
      $streamData = '';
      $block = (int) $this->props[$this->workbook]['startBlock'];
      while ($block !== -2) {
        $pos = $block * SMALL_BLOCK_SIZE;
        $streamData .= substr($rootdata, $pos, SMALL_BLOCK_SIZE);
        $block = (int) $this->smallBlockChain[$block];
      }
      return $streamData;
    }

    $numBlocks = $this->props[$this->workbook]['size'] / BIG_BLOCK_SIZE;
    if ($this->props[$this->workbook]['size'] % BIG_BLOCK_SIZE !== 0) {
      $numBlocks++;
    }

    if ($numBlocks === 0) {
      return '';
    }

    $streamData = '';
    $block = (int) $this->props[$this->workbook]['startBlock'];
    while ($block !== -2) {
      $pos = ($block + 1) * BIG_BLOCK_SIZE;
      $streamData .= substr($this->data, $pos, BIG_BLOCK_SIZE);
      $block = (int) $this->bigBlockChain[$block];
    }

    return $streamData;
  }

}
