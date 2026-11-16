<?php
defined( 'ABSPATH' ) or die( 'Not allowed' );

/**
 * Raw one-time import data for the "rivenditore" custom post type.
 * Each row: [ html_link_markup, latitude, longitude, address ]
 * Consumed only by the WP-CLI import command (see class-gieffeplus-cli-import.php).
 *
 * Example row:
 * array("<a href='http://maps.google.com/maps?&z=10&q=41.9028+12.4964&ll=41.9028+12.4964'>NOME RIVENDITORE</a>", "41.9028", "12.4964", "VIA ESEMPIO 1, 00100, ROMA (RM)"),
 */
return array(
);
