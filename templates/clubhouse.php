<?php
// templates/clubhouse.php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( Blueworx_Clubhouse_Frontend::club_name() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php echo Blueworx_Clubhouse_Frontend::render_body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Page_Renderer escapes all interpolated text. ?>
	<?php wp_footer(); ?>
</body>
</html>
