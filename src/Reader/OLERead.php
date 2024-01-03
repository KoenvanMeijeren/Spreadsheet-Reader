<?php

namespace KoenVanMeijeren\SpreadsheetReader\Reader;

/**
 * Provides the OLERead class for reading OLE structured files.
 */
final class OLERead {

  /**
   * The data.
   */
  public mixed $data = '';

  /**
   * The error.
   */
  public int $error = 0;

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
  private ?int $workbook = NULL;

  /**
   * The root entry.
   */
  private ?int $rootEntry = NULL;

  /**
   * Read the header of the OLE file.
   */
  public function read(string $filename): bool {
    // Check if file exist and is readable (Darko Miljanovic)
    if (!is_readable($filename)) {
      $this->error = 1;
      return FALSE;
    }
    $this->data = @file_get_contents($filename);
    if (!$this->data) {
      $this->error = 1;
      return FALSE;
    }
    if (!str_starts_with($this->data, IDENTIFIER_OLE)) {
      $this->error = 1;
      return FALSE;
    }
    $this->numBigBlockDepotBlocks = get_int4d($this->data, NUM_BIG_BLOCK_DEPOT_BLOCKS_POS);
    $this->sbdStartBlock = get_int4d($this->data, SMALL_BLOCK_DEPOT_BLOCK_POS);
    $this->rootStartBlock = get_int4d($this->data, ROOT_START_BLOCK_POS);
    $this->extensionBlock = get_int4d($this->data, EXTENSION_BLOCK_POS);
    $this->numExtensionBlocks = get_int4d($this->data, NUM_EXTENSION_BLOCK_POS);

    $bigBlockDepotBlocks = [];
    $pos = BIG_BLOCK_DEPOT_BLOCKS_POS;
    $bbdBlocks = $this->numBigBlockDepotBlocks;
    if ($this->numExtensionBlocks !== 0) {
      $bbdBlocks = (BIG_BLOCK_SIZE - BIG_BLOCK_DEPOT_BLOCKS_POS) / 4;
    }

    for ($i = 0; $i < $bbdBlocks; $i++) {
      $bigBlockDepotBlocks[$i] = get_int4d($this->data, $pos);
      $pos += 4;
    }

    for ($j = 0; $j < $this->numExtensionBlocks; $j++) {
      $pos = ($this->extensionBlock + 1) * BIG_BLOCK_SIZE;
      $blocksToRead = min($this->numBigBlockDepotBlocks - $bbdBlocks, BIG_BLOCK_SIZE / 4 - 1);

      for ($i = $bbdBlocks; $i < $bbdBlocks + $blocksToRead; $i++) {
        $bigBlockDepotBlocks[$i] = get_int4d($this->data, $pos);
        $pos += 4;
      }

      $bbdBlocks += $blocksToRead;
      if ($bbdBlocks < $this->numBigBlockDepotBlocks) {
        $this->extensionBlock = get_int4d($this->data, $pos);
      }
    }

    // readBigBlockDepot.
    $index = 0;
    $this->bigBlockChain = [];

    for ($i = 0; $i < $this->numBigBlockDepotBlocks; $i++) {
      $pos = ($bigBlockDepotBlocks[$i] + 1) * BIG_BLOCK_SIZE;
      // Echo "pos = $pos";.
      for ($j = 0; $j < BIG_BLOCK_SIZE / 4; $j++) {
        $this->bigBlockChain[$index] = get_int4d($this->data, $pos);
        $pos += 4;
        $index++;
      }
    }

    // readSmallBlockDepot();
    $index = 0;
    $sbdBlock = $this->sbdStartBlock;
    $this->smallBlockChain = [];

    while ($sbdBlock !== -2) {
      $pos = ($sbdBlock + 1) * BIG_BLOCK_SIZE;
      for ($j = 0; $j < BIG_BLOCK_SIZE / 4; $j++) {
        $this->smallBlockChain[$index] = get_int4d($this->data, $pos);
        $pos += 4;
        $index++;
      }
      $sbdBlock = $this->bigBlockChain[$sbdBlock];
    }

    // readData(rootStartBlock)
    $block = $this->rootStartBlock;
    $this->entry = $this->readData($block);
    $this->readPropertySets();

    return TRUE;
  }

  /**
   * Read the data.
   */
  public function readData(string $bl): string {
    $block = $bl;
    $data = '';
    while ($block !== -2) {
      $pos = ($block + 1) * BIG_BLOCK_SIZE;
      $data .= substr($this->data, $pos, BIG_BLOCK_SIZE);
      $block = $this->bigBlockChain[$block];
    }
    return $data;
  }

  /**
   * Read the property sets.
   */
  public function readPropertySets(): void {
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
      $block = $this->props[$this->workbook]['startBlock'];
      while ($block !== -2) {
        $pos = $block * SMALL_BLOCK_SIZE;
        $streamData .= substr($rootdata, $pos, SMALL_BLOCK_SIZE);
        $block = $this->smallBlockChain[$block];
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
    $block = $this->props[$this->workbook]['startBlock'];
    while ($block !== -2) {
      $pos = ($block + 1) * BIG_BLOCK_SIZE;
      $streamData .= substr($this->data, $pos, BIG_BLOCK_SIZE);
      $block = $this->bigBlockChain[$block];
    }

    return $streamData;
  }

}
