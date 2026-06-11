WP="wp-env run cli wp"

# Shop-Seiten
$WP wc tool run install_pages --user=admin || true

# Store-Basisdaten
$WP option update woocommerce_currency EUR
$WP option update woocommerce_default_country DE:DE-ST
$WP option update woocommerce_store_address "Musterstraße 1"
$WP option update woocommerce_store_city "Halle"
$WP option update woocommerce_store_postcode "06116"

# Dev-Zahlungsmethode aktivieren: Überweisung
$WP option update woocommerce_bacs_settings '{"enabled":"yes","title":"Banküberweisung","description":"Dev-Zahlung per Banküberweisung"}' --format=json

# Onboarding/Wizard möglichst neutralisieren
$WP eval '
update_option("woocommerce_task_list_hidden", "yes");
update_option("woocommerce_task_list_complete", "yes");
update_option("woocommerce_task_list_welcome_modal_dismissed", "yes");
update_option("woocommerce_show_marketplace_suggestions", "no");
update_option("woocommerce_admin_setup_wizard_completed", "yes");
update_option("woocommerce_onboarding_profile", array(
    "skipped" => true,
    "completed" => true,
));
'

# Transients entfernen, falls Wizard gecached wurde
$WP transient delete wc_admin_setup_wizard || true
$WP transient delete --all || true