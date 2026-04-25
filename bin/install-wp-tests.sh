#!/usr/bin/env bash
#
# Prepare the MySQL database used by the functional test suite.
#
# WordPress core (roots/wordpress) and the WordPress test framework
# (wp-phpunit/wp-phpunit) are already installed in vendor/ via composer,
# so this script only has to create the database and write a
# wp-tests-config.php that points the test framework at it.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [skip-db-create]

set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [skip-db-create]" >&2
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
SKIP_DB_CREATE=${5-false}

# Resolve project root regardless of where the script is invoked from.
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_CORE_DIR="$PROJECT_ROOT/vendor/roots/wordpress-no-content"
WP_TESTS_DIR="$PROJECT_ROOT/vendor/wp-phpunit/wp-phpunit"

if [ ! -d "$WP_CORE_DIR" ] || [ ! -d "$WP_TESTS_DIR" ]; then
    echo "Run 'composer install' first — vendor/ is missing the required packages." >&2
    exit 1
fi

# Write wp-tests-config.php at the project root. wp-phpunit's bootstrap looks
# for it via the WP_TESTS_CONFIG_FILE_PATH env var, which we export below.
CONFIG_FILE="$PROJECT_ROOT/wp-tests-config.php"
cat > "$CONFIG_FILE" <<PHP
<?php
define( 'ABSPATH', '$WP_CORE_DIR/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'DB_NAME',     '$DB_NAME' );
define( 'DB_USER',     '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST',     '$DB_HOST' );
define( 'DB_CHARSET',  'utf8' );
define( 'DB_COLLATE',  '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL',  'admin@example.org' );
define( 'WP_TESTS_TITLE',  'Test Site' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'AUTH_KEY',         'test-auth-key' );
define( 'SECURE_AUTH_KEY',  'test-secure-auth-key' );
define( 'LOGGED_IN_KEY',    'test-logged-in-key' );
define( 'NONCE_KEY',        'test-nonce-key' );
define( 'AUTH_SALT',        'test-auth-salt' );
define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt' );
define( 'LOGGED_IN_SALT',   'test-logged-in-salt' );
define( 'NONCE_SALT',       'test-nonce-salt' );
PHP

if [ "$SKIP_DB_CREATE" = "true" ]; then
    exit 0
fi

# Drop+create makes the script safe to run repeatedly.
PARTS=(${DB_HOST//\:/ })
DB_HOSTNAME=${PARTS[0]}
DB_SOCK_OR_PORT=${PARTS[1]-}
EXTRA=""
if [ -n "$DB_HOSTNAME" ]; then
    if [[ "$DB_SOCK_OR_PORT" =~ ^[0-9]+$ ]]; then
        EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
    elif [ -n "$DB_SOCK_OR_PORT" ]; then
        EXTRA=" --socket=$DB_SOCK_OR_PORT"
    else
        EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
    fi
fi

mysqladmin --user="$DB_USER" --password="$DB_PASS" $EXTRA --force drop "$DB_NAME" 2>/dev/null || true
mysqladmin --user="$DB_USER" --password="$DB_PASS" $EXTRA create "$DB_NAME"

echo "Database $DB_NAME ready. wp-tests-config.php written to $CONFIG_FILE."
