<?php
/**
 * The Header template for our theme
 *
 * Displays all of the <head> section and everything up till <div id="main">
 *
 * @package WordPress
 * @subpackage Twenty_Twelve
 * @since Twenty Twelve 1.0
 */
?><!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) & !(IE 8)]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<title><?php wp_title( '|', true, 'right' ); ?></title>
<?php // Loads HTML5 JavaScript file to add support for HTML5 elements in older IE versions. ?>
<!--[if lt IE 9]>
<script src="<?php echo get_template_directory_uri(); ?>/js/html5.js" type="text/javascript"></script>
<![endif]-->
<link rel='stylesheet' id='admin-bar-css'  href='/wp-content/themes/twentytwelve/stylesheets/foundation.css' type='text/css' media='all' />
<link rel='stylesheet' id='admin-bar-css'  href='/wp-content/themes/twentytwelve/stylesheets/screen.css' type='text/css' media='all' />
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<div class="row header">



<nav class="top-bar" data-topbar="" role="navigation">

  <ul class="title-area">
    <!-- Title Area -->
    <li class="name">
			<div class="main-logo">
			</div>
    </li>
    <!-- Remove the class "menu-icon" to get rid of menu icon. Take out "Menu" to just have icon alone -->
    <li class="toggle-topbar menu-icon"><a href=""><span>Menu</span></a></li>
  </ul>

		<section class="top-bar-section">
    <ul class="left">
      <li class="has-dropdown not-click"><a href="#">Welcome Home</a>
        <ul class="dropdown">
  				<?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_class' => 'nav-menu' ) ); ?>
	        <li class="divider"></li>
          <li class="has-dropdown not-click"><a href="#">Sub-item 4</a>
            <ul class="dropdown"><li class="title back js-generated"><h5><a href="javascript:void(0)">Back</a></h5></li><li class="parent-link show-for-small"><a class="parent-link js-generated" href="#">Sub-item 4</a></li>
              <li><a href="#">Sub-item 2</a></li>
              <li><a href="#">Sub-item 3</a></li>
              <li><a href="#">Sub-item 4</a>
            </li></ul>
          </li>
        </ul>
      </li>
      <li class="divider"></li>
    </ul>
  </section></nav>

	<header class="large-3 columns" role="banner">
		<hgroup>
			<!-- <h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<h2 class="site-description"><?php bloginfo( 'description' ); ?></h2> -->
		</hgroup>

		<!-- <nav id="site-navigation" class="main-navigation" role="navigation">
			<a class="assistive-text" href="#content" title="<?php esc_attr_e( 'Skip to content', 'twentytwelve' ); ?>"><?php _e( 'Skip to content', 'twentytwelve' ); ?></a>

		</nav>-->

		<?php if ( get_header_image() ) : ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php header_image(); ?>" class="header-image" width="<?php echo get_custom_header()->width; ?>" height="<?php echo get_custom_header()->height; ?>" alt="" /></a>
		<?php endif; ?>
	</header>

</div>
<!-- #masthead -->

	<div id="main" class="wrapper">
