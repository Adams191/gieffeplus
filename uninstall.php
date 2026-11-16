<?php
// Runs only when the plugin is deleted from wp-admin (never on deactivate).
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

// Remove plugin options.
delete_option('gieffeplus_gmaps_api_key');

// Remove cached transients used by the REST endpoint.
delete_transient('gieffeplus_resellers_cache');

// Note: "rivenditore" posts and their meta are intentionally left in place.
// They are business data (dealer addresses), not plugin configuration, and
// should survive a plugin removal/reinstall.
