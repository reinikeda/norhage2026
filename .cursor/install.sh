#!/usr/bin/env bash
#
# Cloud Agent install script for the Norhage 2026 WordPress + WooCommerce project.
#
# The repository only tracks the custom plugins and the Astra child theme under
# public/wp-content. WordPress core, WooCommerce, the Astra parent theme, the
# database and wp-config.php are all gitignored, so this script bootstraps a
# complete, runnable WordPress install around the tracked code.
#
# It is designed to be idempotent: re-running it converges on a working install
# and never destroys existing data.
set -euo pipefail

WP_PATH="/workspace/public"
SITE_URL="http://localhost:8080"
DB_NAME="wordpress"
DB_USER="wordpress"
DB_PASS="wordpress"
DB_HOST="127.0.0.1"

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

wp() { command wp --path="$WP_PATH" "$@"; }

# ---------------------------------------------------------------------------
# 1. System packages (guarded so this stays fast when already provisioned,
#    e.g. when booting from a prebuilt environment snapshot).
# ---------------------------------------------------------------------------
ensure_packages() {
  local missing=0
  command -v php >/dev/null 2>&1 || missing=1
  command -v mariadbd >/dev/null 2>&1 || command -v mysqld >/dev/null 2>&1 || missing=1
  command -v wp >/dev/null 2>&1 || missing=1
  if [ "$missing" -eq 0 ]; then
    log "System packages already present; skipping apt install"
    return
  fi

  log "Installing system packages (PHP 8.3, MariaDB, WP-CLI)"
  sudo DEBIAN_FRONTEND=noninteractive apt-get update -y
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    php8.3-cli php8.3-mysql php8.3-gd php8.3-curl php8.3-xml php8.3-mbstring \
    php8.3-zip php8.3-intl php8.3-bcmath php8.3-soap php8.3-imagick \
    mariadb-server mariadb-client curl less

  if ! command -v wp >/dev/null 2>&1; then
    log "Installing WP-CLI"
    curl -sSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    sudo mv /tmp/wp-cli.phar /usr/local/bin/wp
  fi
}

# ---------------------------------------------------------------------------
# 2. Database server + application database.
# ---------------------------------------------------------------------------
ensure_database() {
  log "Starting MariaDB"
  sudo service mariadb start || true
  # Wait for the socket / TCP port to accept connections.
  for _ in $(seq 1 30); do
    if sudo mariadb -e "SELECT 1" >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done

  log "Ensuring database and user exist"
  sudo mariadb <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
}

# ---------------------------------------------------------------------------
# 3. WordPress core + wp-config.php (tracked plugins/theme are left untouched).
# ---------------------------------------------------------------------------
ensure_wordpress_core() {
  if [ ! -f "$WP_PATH/wp-load.php" ]; then
    log "Downloading WordPress core"
    # --skip-content preserves the repo's custom plugins and child theme.
    wp core download --skip-content --force
  else
    log "WordPress core already present"
  fi

  if [ ! -f "$WP_PATH/wp-config.php" ]; then
    log "Creating wp-config.php"
    wp config create \
      --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
      --dbhost="$DB_HOST" --dbcharset=utf8mb4 --skip-check --force
    wp config set WP_DEBUG true --raw --type=constant
    wp config set WP_DEBUG_LOG true --raw --type=constant
    wp config set WP_DEBUG_DISPLAY false --raw --type=constant
  else
    log "wp-config.php already present"
  fi
}

# ---------------------------------------------------------------------------
# 4. Install WordPress (create the tables + admin user) if not yet installed.
# ---------------------------------------------------------------------------
ensure_wordpress_installed() {
  if wp core is-installed >/dev/null 2>&1; then
    log "WordPress already installed"
    return
  fi
  log "Installing WordPress"
  wp core install \
    --url="$SITE_URL" \
    --title="Norhage 2026 Dev" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=dev@norhage.local \
    --skip-email
  wp rewrite structure '/%postname%/' --hard || true
}

# ---------------------------------------------------------------------------
# 5. Dependencies: WooCommerce, the Astra parent theme, and the WP importer.
#    These are gitignored, so (re)install them if absent.
# ---------------------------------------------------------------------------
ensure_dependencies() {
  wp plugin is-installed woocommerce >/dev/null 2>&1 || { log "Installing WooCommerce"; wp plugin install woocommerce; }
  wp theme  is-installed astra        >/dev/null 2>&1 || { log "Installing Astra parent theme"; wp theme install astra; }
  wp plugin is-installed wordpress-importer >/dev/null 2>&1 || { log "Installing WP importer"; wp plugin install wordpress-importer; }
}

# ---------------------------------------------------------------------------
# 6. Activate WooCommerce, the child theme and every custom plugin.
# ---------------------------------------------------------------------------
activate_code() {
  log "Activating WooCommerce + custom theme and plugins"
  wp plugin activate woocommerce wordpress-importer || true
  wp theme activate astra-custom-for-norhage || true

  # Activate whatever custom plugins are present in the repo checkout.
  local custom_plugins=(
    nh-faq nh-filters nh-cutting-toggle nh-home-builder product-labels
    running-ticker-line nh-sale-slider nh-tax-switcher nh-shipping-calculator
  )
  for p in "${custom_plugins[@]}"; do
    if wp plugin is-installed "$p" >/dev/null 2>&1; then
      wp plugin activate "$p" || true
    fi
  done
}

# ---------------------------------------------------------------------------
# 7. Seed a minimal WooCommerce demo dataset the first time only, so the store
#    is browsable and the shipping calculator has something to price.
# ---------------------------------------------------------------------------
seed_demo_data() {
  local product_count
  product_count="$(wp post list --post_type=product --format=count 2>/dev/null || echo 0)"
  if [ "${product_count:-0}" -gt 0 ]; then
    log "Store already has products; skipping demo seed"
    return
  fi

  log "Seeding WooCommerce demo data"
  wp option update woocommerce_store_address "Teollisuustie 1" || true
  wp option update woocommerce_default_country "FI" || true
  wp option update woocommerce_currency "EUR" || true
  wp option update woocommerce_calc_taxes "yes" || true

  local sample="$WP_PATH/wp-content/plugins/woocommerce/sample-data/sample_products.xml"
  if [ -f "$sample" ]; then
    wp import "$sample" --authors=create || true
  fi

  # A flat-rate shipping zone so the shipping-calculator override has a rate to modify.
  if ! wp wc shipping_zone list --user=admin --format=csv 2>/dev/null | grep -qi ',Europe,'; then
    local zone
    zone="$(wp wc shipping_zone create --name="Europe" --user=admin --porcelain 2>/dev/null || echo '')"
    if [ -n "$zone" ]; then
      wp wc shipping_zone_method create "$zone" --method_id=flat_rate --user=admin || true
      wp eval '$s=get_option("woocommerce_flat_rate_1_settings",array()); if(!is_array($s))$s=array(); $s["cost"]="9.90"; update_option("woocommerce_flat_rate_1_settings",$s);' || true
    fi
  fi
}

main() {
  ensure_packages
  ensure_database
  ensure_wordpress_core
  ensure_wordpress_installed
  ensure_dependencies
  activate_code
  seed_demo_data
  log "Install complete. Site URL: $SITE_URL (admin / admin)"
}

main "$@"
