<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'restorationchurch');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '..z3ywxdeV{I.BAv--WVj=,]%m$Me;42E_uMa)d8jOGRY+=*&nF /g8[<fy)S#fH');
define('SECURE_AUTH_KEY',  'k(ND|nn+*5j98t7UHo*<[&d)#5U_R!}G4FTK.}KLO?JY4H1B<D=cNVtjSK$.$8Eg');
define('LOGGED_IN_KEY',    'CCRX8r5i DjH^mgDU{}3v!<A}@P%ex/J_;fqAP!mK*Ly9zF1Jz+m#f{:2;UiK1F/');
define('NONCE_KEY',        'JpKGK_5d2R1U+Hsg~a[WSlLgB$:G=th3Q)E:x`M%fMA4A>S*yup9pI1QcIrlrtI&');
define('AUTH_SALT',        'tt9}`jfUV5H:+.Ai!RbV(k=9wR,4E<w{AWj8)@[QQYWdpDg[8iqVO[P>1n .D54)');
define('SECURE_AUTH_SALT', 'WFh|!,$|sQ8_^B36C?g#4[/H*5P,H8SPGON1Yq64La8=P&u9bKQ}.83!^wko1c:O');
define('LOGGED_IN_SALT',   'B:v3r.,lIy6W!T,|wf(1D<dOB}%z$<ImH1j0is$6?+6y-@k81fWyjJhR(^`akfc:');
define('NONCE_SALT',       '>i~(^y_/euU6pfGgb ]PvUg]j;xVye/_ooh9Q!( V*vl3:y*Q[)>IvhE5f:=<jqG');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'rs_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
