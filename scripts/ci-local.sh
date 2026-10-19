#!/usr/bin/env bash
# Local CI (CONTRIBUTING.md → "CI is local"): the Tests workflow is disabled, so every PR runs this and pastes the
# summary into its body. For each PHP version (default 8.3 and 8.4):
#   - Filament 5: composer install from the committed lock file, PHPStan (composer analyse), PHPUnit (composer test)
#   - Filament 4: a throwaway clone of HEAD, composer.json narrowed to filament/filament ^4.0, composer update,
#                 then the same PHPStan and PHPUnit runs (the lock file only pins one Filament major)
#   scripts/ci-local.sh                                 both PHP versions, both Filament majors
#   PHP_VERSIONS=8.3 FILAMENT_VERSIONS=4 scripts/ci-local.sh
# Finds php8.3 / php8.4 on PATH, or Homebrew's php@8.x. The Filament 4 job tests committed code (HEAD) only.
set -uo pipefail
cd "$(dirname "$0")/.."
REPO=$(pwd)

PHP_VERSIONS=${PHP_VERSIONS:-"8.3 8.4"}
FILAMENT_VERSIONS=${FILAMENT_VERSIONS:-"5 4"}
RESULTS=(); FAILED=0
step() { local name=$1; shift; printf '\n== %s\n' "$name"; if "$@"; then RESULTS+=("pass  $name"); else RESULTS+=("FAIL  $name"); FAILED=1; fi; }

php_bin() {
  local v=$1 p
  for p in "$(command -v "php$v" 2>/dev/null)" "$(brew --prefix 2>/dev/null)/opt/php@$v/bin/php"; do
    [ -n "$p" ] && [ -x "$p" ] && "$p" -r 'exit(PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION === $argv[1] ? 0 : 1);' "$v" && { echo "$p"; return; }
  done
  return 1
}

COMPOSER=$(command -v composer)
SHIM=$(mktemp -d); WORK=$(mktemp -d); trap 'rm -rf "$SHIM" "$WORK"' EXIT
BASE_PATH=$PATH
for v in $PHP_VERSIONS; do
  PHP=$(php_bin "$v") || { RESULTS+=("FAIL  PHP $v not found"); FAILED=1; continue; }
  # vendor/bin scripts start with "#!/usr/bin/env php": put this version first on PATH so they don't run on the default PHP.
  ln -sf "$PHP" "$SHIM/php"; export PATH="$SHIM:$BASE_PATH"
  step "PHP $v active" sh -c "php -r 'exit(PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION === \"$v\" ? 0 : 1);'"
  for f in $FILAMENT_VERSIONS; do
    if [ "$f" = "5" ]; then
      DIR=$REPO
      step "PHP $v Filament 5 composer install (lock)" "$PHP" "$COMPOSER" -d "$DIR" install --no-interaction --no-progress --prefer-dist
    else
      DIR="$WORK/f$f-php$v"
      git clone --quiet --no-hardlinks "$REPO" "$DIR" && git -C "$DIR" checkout --quiet --detach "$(git rev-parse HEAD)"
      step "PHP $v Filament $f composer update" sh -c "\"$PHP\" \"$COMPOSER\" -d \"$DIR\" require --no-update --no-interaction 'filament/filament:^$f.0' && \"$PHP\" \"$COMPOSER\" -d \"$DIR\" update -W --no-interaction --no-progress --prefer-dist"
    fi
    RESOLVED=$(cd "$DIR" && "$PHP" "$COMPOSER" show filament/filament 2>/dev/null | awk '/^versions/ {print $NF}')
    step "PHP $v Filament $f resolved ${RESOLVED:-nothing}" sh -c "case '$RESOLVED' in v$f.*) exit 0 ;; *) exit 1 ;; esac"
    step "PHP $v Filament $f phpstan" "$PHP" "$COMPOSER" -d "$DIR" analyse
    step "PHP $v Filament $f phpunit" "$PHP" "$COMPOSER" -d "$DIR" test
  done
done

printf '\n== Summary (%s, %s)\n' "$(git rev-parse --short HEAD)" "$(date '+%Y-%m-%d %H:%M')"
printf '%s\n' "${RESULTS[@]}"
exit $FAILED
