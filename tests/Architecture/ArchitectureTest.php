<?php

/**
 * @file
 * Contains the architecture tests.
 */

use KoenVanMeijeren\SpreadsheetReader\Reader\SpreadsheetReaderInterface;

arch('src')
  ->expect('KoenVanMeijeren\SpreadsheetReader')
  ->not->toUseStrictTypes();

arch('exceptions')
  ->expect('KoenVanMeijeren\SpreadsheetReader\Exceptions')
  ->toExtend(\Throwable::class)
  ->toHaveSuffix('Exception');

arch('readers')
  ->expect('KoenVanMeijeren\SpreadsheetReader\Reader')
  ->toImplement(SpreadsheetReaderInterface::class)
  ->toExtendNothing()
  ->toHavePrefix('SpreadsheetReader')
  ->toOnlyBeUsedIn('KoenVanMeijeren\SpreadsheetReader');

arch('debug functions are not used in production')
  ->expect(['dd', 'dump'])
  ->not->toBeUsed();

arch('config')
  ->expect('KoenVanMeijeren\SpreadsheetReader\Config')
  ->toExtendNothing();
