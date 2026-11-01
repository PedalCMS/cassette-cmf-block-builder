<?php
/**
 * Minimal classic template for the PHPUnit asset tests.
 *
 * @package bb-test-classic-theme
 */

?><!DOCTYPE html>
<html>
<head><?php wp_head(); ?></head>
<body>
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
wp_footer();
?>
</body>
</html>
