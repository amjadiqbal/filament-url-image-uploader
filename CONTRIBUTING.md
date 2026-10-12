# Contributing

## CI is local

GitHub workflows don't run checks for this repo (Amjad, 2026-10-10: failed runs flood his email). The `Tests`
workflow is disabled and its file (`.github/workflows/tests.yml`) stays only as a reference for what the local run
covers. Don't re-enable or trigger it.

Every PR runs the same checks on the developer's machine and pastes the summary block into its description:

```bash
scripts/ci-local.sh                    # composer install, PHPStan and PHPUnit on PHP 8.3 and 8.4
PHP_VERSIONS=8.3 scripts/ci-local.sh   # one PHP version only
```

It finds `php8.3`/`php8.4` on PATH or Homebrew's `php@8.x` and ends with one `pass`/`FAIL` line per step.

`composer.json` pins `config.platform.php` to 8.3, the package's floor, so `composer update` on a newer PHP can't
lock dependencies that 8.3 can't install.
