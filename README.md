# Data Parser

A specialized CLI utility built to aggregate and normalize third-party log data into a standardized CSV format.

## Architectural Decisions

Instead of a basic procedural script, I’ve implemented an ETL-style pipeline to ensure the project is maintainable and ready for production-level datasets.

* **Extraction**: Uses `Symfony/Finder` for recursive discovery. This is more robust than `glob()` and handles nested directories and file permissions gracefully.
* **Transformation Layer**: Transformation logic is decoupled via `LogParserInterface`. By injecting the `LogParserService` into the command, the business logic remains isolated and easy to unit test.
* **Memory Management**: Leveraging `League/Csv` allows for stream-based I/O. This ensures the tool maintains a constant, low memory footprint even when processing millions of rows.
* **Strict Typing**: Built for **PHP 8.4** with `strict_types=1` and typed class constants to catch type-mismatch bugs at compile time.

## Quick Start

### Docker (Recommended)
This is the most reliable way to run the tool without version conflicts.

1. `docker compose build`
2. `docker compose up`

*Note: I’ve used an anonymous volume for `/app/vendor` in the compose file. This prevents your local environment from clashing with the container’s dependencies.*

---

### Native Execution
If you prefer to run it on your host machine:

**Prerequisites:** PHP 8.4+ and Composer.

1. `composer install`
2. `php bin/console app:parse`

## Error Handling & Reliability

* **Fault Tolerance**: The process is wrapped in localized `try-catch` blocks. If one file is corrupt or a header is missing, the tool logs the error and continues to the next file rather than crashing the entire batch.
* **Data Preservation**: Logic is tuned to consolidate tags as per requirements, but I've ensured that unknown tags default to a standard status rather than dropping the record entirely.
* **Logging**: Uses `ConsoleLogger` to provide clear, level-based feedback during execution.
---

## Time Investment
* **Core Architecture & Interface Design**: 1.5 Hours
* **Dockerization & Dependency Management**: 1 Hour
* **Refinement & Documentation**: 0.5 Hours
* **Total**: ~3 Hours
---

**Submission Note:** The `vendor/` folder and `output.csv` are excluded from this repository and will be generated on the first run.