# Spreadsheet reader
This package provides a PHP spreadsheet reader that differs from others in that the main goal for it was efficient
data extraction that could handle large (as in really large) files. So far it may not definitely be CPU, time
or I/O-efficient, but at least it won't run out of memory (except maybe for XLS files).

So far, XLSX, ODS and text/CSV file parsing should be memory-efficient. XLS file parsing is done with php-excel-reader
from http://code.google.com/p/php-excel-reader/ which, sadly, has memory issues with bigger spreadsheets, as it reads the
data all at once and keeps it all in memory.

## Installation
```shell
composer require koenvanmeijeren/spreadsheet-reader
```

## Requirements:
* PHP 8.0 or newer
* PHP must have Zip file support (see http://php.net/manual/en/zip.installation.php)

For XLSX-file reading
* PHP must have Simple XML & XML read support

## Usage:

All data is read from the file sequentially, with each row being returned as a numeric array.
This is about the easiest way to read a file:
```php
$reader = new KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader('example.xlsx');
foreach ($reader as $row)
{
	print_r($row);
}
```

However, now also multiple sheet reading is supported for file formats where it is possible. (In case of CSV, it is handled as if
it only has one sheet.)

You can retrieve information about sheets contained in the file by calling the `sheets()` method which returns an array with
sheet indexes as keys and sheet names as values. Then you can change the sheet that's currently being read by passing that index
to the `changeSheet($Index)` method.

Example:

```php
$reader = new KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader('example.xlsx');
$sheets = $reader->sheets();
foreach ($sheets as $index => $name) {
	echo 'Sheet #'.$index.': '.$name;

	$reader->changeSheet($index);
	foreach ($reader as $row)
	{
		print_r($row);
	}
}
```

If a sheet is changed to the same that is currently open, the position in the file still reverts to the beginning, so as to conform
to the same behavior as when changed to a different sheet.

## Testing

From the command line:
```shell
composer run test
```

### Test coverage

Start the docker contains and enter the container:
```shell
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d
docker-compose -f docker-compose.yml -f docker-compose.dev.yml exec php /bin/bash
```

Run the tests with coverage generation:
```shell
composer run pest:coverage
```

### Running Benchmarks

#### Run Benchmark Tests

To execute the benchmark tests, use the following command:
```shell
composer run benchmark
```

This command runs the benchmarks with the default configuration and generates a default report.

#### Run Benchmark Baseline

To establish a baseline for comparison, run the following command:
```shell
composer run benchmark:baseline
```

This command runs the benchmarks and tags the results
as the baseline for future comparisons.

#### Run Benchmark Test Report

To generate an aggregate report comparing the current benchmarks
with the baseline, run:
```shell
composer run benchmark:test
```

This command provides insights into the performance changes
between the baseline and the current state.

### Notes about library performance
*  CSV and text files are read strictly sequentially so performance should be O(n);
*  When, parsing XLS files, all the file content is read into memory so large XLS files can lead to "out of memory" errors;
*  XLSX files use so-called "shared strings" internally to optimize for cases where the same string is repeated multiple times.
	Internally XLSX is an XML text that is parsed sequentially to extract data from it, however, in some cases these shared strings are a problem -
	sometimes Excel may put all, or nearly all the strings from the spreadsheet in the shared string file (which is a separate XML text), and not necessarily in the same
	order. The Worst case scenario is when it is in reverse order — for each string we need to parse the shared string XML from the beginning if we want to avoid keeping the data in memory.
	To that end, the XLSX parser has a cache for shared strings that is used if the total shared string count is not too high. In case you get out of memory errors, you can
	try adjusting the *SHARED_STRING_CACHE_LIMIT* constant in KoenVanMeijeren\SpreadsheetReader\SpreadsheetReader_XLSX to a lower one.

### TODOs:
*  ODS date formats;

### Licensing
All the code in this library is licensed under the MIT license as included in the LICENSE file, however, for now the library
relies on php-excel-reader library for XLS file parsing, which is licensed under the PHP license.
