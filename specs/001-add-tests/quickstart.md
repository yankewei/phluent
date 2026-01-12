# Quickstart: Add Automated Tests

## Prerequisites

- PHP 8.5
- Composer

## Run Tests Locally

1. Install dependencies:

   ```bash
   composer install
   ```

2. Run the test suite (command to be added in implementation):

   ```bash
   composer test
   ```

## CI Expectations

- Tests must be deterministic and pass without manual setup.
- Full suite should complete within 10 minutes.

## Validation Notes

- 2026-01-12: `composer test` passes locally with 8 tests.
