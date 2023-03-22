<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

 define( 'FS_METHOD', 'direct' );

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'khoahoc' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'c@)i/B0yy.QmL`v 4-h8f`7SPXp6sfk0F#?{ ,=Xqd<C3XApt(JSy*S!{5NuWX+c' );
define( 'SECURE_AUTH_KEY',  '<|9B<mD^,zTN[t,{VLo!6$tn{h-2j^*t,uI%%k>KRSw3hAD7O?h^593`4/WAEBYt' );
define( 'LOGGED_IN_KEY',    ';>EWy!Qb(Y9d_R#g&60OjTE?^:>R{} R(*2D|Q<,3Gsb3~,~cNZrGR3g<tE[Cn8R' );
define( 'NONCE_KEY',        '(q,;aCr@ Hf..57/hun/cH!O]+PBsEKG#{+-]]|Q&HG OldeCdlFd^2WwT7AK>z>' );
define( 'AUTH_SALT',        '2c@/3~wf*x$Q` V2N9TNNp2h{k5Qz~<Ise8BQNCn0:]n%fam>3N.gB{ha1r<x-8.' );
define( 'SECURE_AUTH_SALT', 'dKnVP8z?$fz8B,-HCm}:5s*8O`RDbPW+)nuIU`>aY8F94SM Bj3@_243yxmV#t`O' );
define( 'LOGGED_IN_SALT',   'c>=N95?.2|]HJmX{P%2r;p>jD`?1V5[JD$`lR^4ZwF:9_d6gF_FI$6j/fF{*T+oa' );
define( 'NONCE_SALT',       '&L5]xhQ(]p=YOh|s{c<~ c,=.L-r1&=@1|KfJJ5+|s6;1c0Z1 ~z_J>A/SGU^M3!' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
