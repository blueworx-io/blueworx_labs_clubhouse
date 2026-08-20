<?php
// templates/commerce.php
//
// The whole document for the two pages this plugin dresses: checkout and the
// thank-you page after it.
//
// Without this they render inside the theme's page template, which draws its
// own header above and its own footer below — so the checkout carried the
// club's site footer, 442px of it, beneath its own 48px one, and a matching
// slab of theme header above. The frame is a full-screen checkout in the
// approved design; it cannot be that while something else owns the page.
//
// Same shape as templates/clubhouse.php, and the same reasoning. The one
// difference: this runs WordPress's real loop rather than calling a renderer.
// Commerce_Pages::dress() is a the_content filter guarded on in_the_loop() and
// is_main_query(), so the loop is what makes it fire — and the frame keeps
// being built exactly where it was, with nothing about it changed here.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
	<?php wp_footer(); ?>
</body>
</html>
