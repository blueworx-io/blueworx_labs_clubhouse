<?php
// templates/club-page.php
//
// The document for a club page served from its own real WordPress page rather
// than the clubhouse_page rewrite rule (see class-frontend.php's
// template_for_post()). The rewrite rule still exists and still matches, but
// once the page is a real WordPress page, WordPress finds it by its post id
// before the rule is ever consulted, and this template is what renders it.
//
// The renderer is unchanged: same shape as templates/clubhouse.php, same
// Blueworx_Clubhouse_Frontend::render_body() call, same reasoning about the
// title tag.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php // The title comes from wp_head() via pre_get_document_title — printing one here too gave every page two. ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php echo Blueworx_Clubhouse_Frontend::render_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Page_Renderer escapes all interpolated text. ?>
	<?php wp_footer(); ?>
</body>
</html>
