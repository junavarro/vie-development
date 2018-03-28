<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package _tk
 */
?>

<?php if (is_front_page()) {
		wd_slider(1);
	}
?>

<?php if (is_front_page()) { ?>
<div id="slogan">
	<p>Science</p>
	<br>
	<p>Technology</p>
	<br>
	<p>Innovation</p>
</div>
<?php } ?>
<div class="main-content">
<?php // substitute the class "container-fluid" below if you want a wider content area ?>
	<div class="container-fluid main-container">
		<div class="row">
			<div id="content" class="main-content-inner col-sm-12 col-md-12 ">
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<header>
						<h1 class="page-title"><?php the_title(); ?></h1>
					</header><!-- .entry-header -->

					<div class="entry-content col-sm-12 col-md-8">
						<div class="entry-content-thumbnail">
							<?php the_post_thumbnail(); ?>
						</div>
						<?php the_content(); ?>
						<?php _tk_link_pages(); ?>
					</div><!-- .entry-content -->
					<?php edit_post_link( __( 'Edit', '_tk' ), '<footer class="entry-meta"><span class="edit-link">', '</span></footer>' ); ?>
				</article><!-- #post-## -->

				<?php do_action( 'before_sidebar' ); ?>
				<?php if ( dynamic_sidebar( 'events-block' ) ) : ?>
				<?php endif; ?>
