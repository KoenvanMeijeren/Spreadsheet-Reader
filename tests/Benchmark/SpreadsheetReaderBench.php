<?php

namespace Tests\Benchmark;

use KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader;
use PhpBench\Attributes\Assert;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;

/**
 * Benchmarks the spreadsheet reader for CSV files.
 */
final class SpreadsheetReaderBench {

  /**
   * Executes the benchmark.
   */
  #[Revs(50)]
  #[Iterations(5)]
  #[ParamProviders([
    "provideCsvMockFilePaths",
  ])]
  #[Assert("mode(variant.mem.peak) < mode(baseline.mem.peak) +/- 1%")]
  #[Assert("mode(variant.mem.final) < mode(baseline.mem.final) +/- 1%")]
  #[Assert("mode(variant.mem.real) < mode(baseline.mem.real) +/- 1%")]
  public function benchCsvRead(array $params): void {
    $reader = new SpreadsheetReader($params['path']);
    while ($reader->valid()) {
      $reader->next();

      $reader->current();
    }
  }

  /**
   * Provides the mock file paths.
   */
  public function provideCsvMockFilePaths(): \Generator {
    yield ['path' => 'tests/MockData/file_example_CSV_5.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_50.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_500.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_5000.csv'];
  }

  /**
   * Executes the benchmark.
   */
  #[Revs(50)]
  #[Iterations(5)]
  #[ParamProviders([
    "provideXlsxMockFilePaths",
  ])]
  #[Assert("mode(variant.mem.peak) < mode(baseline.mem.peak) +/- 1%")]
  #[Assert("mode(variant.mem.final) < mode(baseline.mem.final) +/- 1%")]
  #[Assert("mode(variant.mem.real) < mode(baseline.mem.real) +/- 1%")]
  public function benchXlsxRead(array $params): void {
    $reader = new SpreadsheetReader($params['path']);
    while ($reader->valid()) {
      $reader->next();

      $reader->current();
    }
  }

  /**
   * Provides the mock file paths.
   */
  public function provideXlsxMockFilePaths(): \Generator {
    yield ['path' => 'tests/MockData/file_example_XLSX_5.xlsx'];
    yield ['path' => 'tests/MockData/file_example_XLSX_50.xlsx'];
    yield ['path' => 'tests/MockData/file_example_XLSX_500.xlsx'];
    yield ['path' => 'tests/MockData/file_example_XLSX_5000.xlsx'];
  }

  /**
   * Executes the benchmark.
   */
  #[Revs(50)]
  #[Iterations(5)]
  #[ParamProviders([
    "provideXlsMockFilePaths",
  ])]
  #[Assert("mode(variant.mem.peak) < mode(baseline.mem.peak) +/- 1%")]
  #[Assert("mode(variant.mem.final) < mode(baseline.mem.final) +/- 1%")]
  #[Assert("mode(variant.mem.real) < mode(baseline.mem.real) +/- 1%")]
  public function benchXlsRead(array $params): void {
    $reader = new SpreadsheetReader($params['path']);
    while ($reader->valid()) {
      $reader->next();

      $reader->current();
    }
  }

  /**
   * Provides the mock file paths.
   */
  public function provideXlsMockFilePaths(): \Generator {
    yield ['path' => 'tests/MockData/file_example_XLS_5.xls'];
    yield ['path' => 'tests/MockData/file_example_XLS_50.xls'];
    yield ['path' => 'tests/MockData/file_example_XLS_500.xls'];
    yield ['path' => 'tests/MockData/file_example_XLS_5000.xls'];
  }

}
