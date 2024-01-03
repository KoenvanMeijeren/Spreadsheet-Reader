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
  #[Revs(100)]
  #[Iterations(5)]
  #[ParamProviders([
    "provideMockFilePaths",
  ])]
  #[Assert("mode(variant.mem.peak) < mode(baseline.mem.peak) +/- 1%")]
  #[Assert("mode(variant.mem.final) < mode(baseline.mem.final) +/- 1%")]
  #[Assert("mode(variant.mem.real) < mode(baseline.mem.real) +/- 1%")]
  public function benchConsume(array $params): void {
    $reader = new SpreadsheetReader($params['path']);
    while ($reader->valid()) {
      $reader->next();

      $reader->current();
    }
  }

  /**
   * Provides the mock file paths.
   */
  public function provideMockFilePaths(): \Generator {
    yield ['path' => 'tests/MockData/file_example_CSV_5.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_50.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_500.csv'];
    yield ['path' => 'tests/MockData/file_example_CSV_5000.csv'];
  }

}
