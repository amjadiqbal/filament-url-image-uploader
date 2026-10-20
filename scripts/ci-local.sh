#!/usr/bin/env bash
# Local CI (CONTRIBUTING.md → "CI is local"): the Tests workflow is disabled, so every PR runs this and pastes the
# summary into its body. For each PHP version (default 8.3 and 8.4) x Laravel major (default 12 and 13) x Filament
# major (default 5 and 4):
#   - Laravel 12 + Filament 5: composer install from the committed lock file, PHPStan (composer analyse), PHPUnit
#   - every other combination: a throwaway clone of HEAD, composer.json narrowed to filament/filament ^N.0 and to the
#     Testbench that matches the Laravel major (^9|^10 for 12, ^11 for 13, since composer.json allows all of them and
#     would otherwise resolve to the newest), composer update, then the same PHPStan and PHPUnit runs (the lock file
#     only pins one Filament major and one Laravel major)
#   scripts/ci-local.sh                                 every combination
#   PHP_VERSIONS=8.3 FILAMENT_VERSIONS=4 LARAVEL_VERSIONS=13 scripts/ci-local.sh
# Finds php8.3 / php8.4 on PATH, or Homebrew's php@8.x. The throwaway-clone jobs test committed code (HEAD) only.
set -uo pipefail
cd "$(dirname "$0")/.."
REPO=$(pwd)

PHP_VERSIONS=${PHP_VERSIONS:-"8.3 8.4"}
FILAMENT_VERSIONS=${FILAMENT_VERSIONS:-"5 4"}
LARAVEL_VERSIONS=${LARAVEL_VERSIONS:-"12 13"}
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
  for l in $LARAVEL_VERSIONS; do
    for f in $FILAMENT_VERSIONS; do
      TAG="PHP $v Laravel $l Filament $f"
      if [ "$l" = "12" ] && [ "$f" = "5" ]; then
        DIR=$REPO
        step "$TAG composer install (lock)" "$PHP" "$COMPOSER" -d "$DIR" install --no-interaction --no-progress --prefer-dist
      else
        DIR="$WORK/f$f-l$l-php$v"
        if [ "$l" = "13" ]; then EXTRA="--dev 'orchestra/testbench:^11.0'"; else EXTRA="--dev 'orchestra/testbench:^9.0|^10.0'"; fi
        git clone --quiet --no-hardlinks "$REPO" "$DIR" && git -C "$DIR" checkout --quiet --detach "$(git rev-parse HEAD)"
        step "$TAG composer update" sh -c "\"$PHP\" \"$COMPOSER\" -d \"$DIR\" require --no-update --no-interaction $EXTRA 'filament/filament:^$f.0' && \"$PHP\" \"$COMPOSER\" -d \"$DIR\" update -W --no-interaction --no-progress --prefer-dist"
      fi
      RESOLVED=$(cd "$DIR" && "$PHP" "$COMPOSER" show filament/filament 2>/dev/null | awk '/^versions/ {print $NF}')
      step "$TAG resolved Filament ${RESOLVED:-nothing}" sh -c "case '$RESOLVED' in v$f.*) exit 0 ;; *) exit 1 ;; esac"
      RESOLVED_L=$(cd "$DIR" && "$PHP" "$COMPOSER" show laravel/framework 2>/dev/null | awk '/^versions/ {print $NF}')
      step "$TAG resolved Laravel ${RESOLVED_L:-nothing}" sh -c "case '$RESOLVED_L' in v$l.*) exit 0 ;; *) exit 1 ;; esac"
      step "$TAG phpstan" "$PHP" "$COMPOSER" -d "$DIR" analyse
      step "$TAG phpunit" "$PHP" "$COMPOSER" -d "$DIR" test
    done
  done
done

printf '\n== Summary (%s, %s)\n' "$(git rev-parse --short HEAD)" "$(date '+%Y-%m-%d %H:%M')"
printf '%s\n' "${RESULTS[@]}"
exit $FAILED
