<?php
/**
 * WEM Test Theme v1.1.0
 *
 * 按 wem-content-fields 的字段定义实际渲染前台数据，
 * 不再输出 JSON、序列化值或 HTML 源码。
 */

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
<?php wp_body_open(); ?>

<main>
	<?php if ( have_posts() ) : ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>

				<?php if ( is_singular() ) : ?>
					<h1><?php the_title(); ?></h1>
				<?php else : ?>
					<h2>
						<a href="<?php the_permalink(); ?>">
							<?php the_title(); ?>
						</a>
					</h2>
				<?php endif; ?>

				<?php the_content(); ?>

				<?php if ( is_singular() ) : ?>
					<?php
					$post_id   = get_the_ID();
					$post_type = get_post_type( $post_id );
					$groups    = wem_test_theme_get_groups_for_post_type( $post_type );
					?>

					<?php if ( $groups ) : ?>
						<?php foreach ( $groups as $group ) : ?>
							<?php
							$fields = isset( $group['fields'] ) && is_array( $group['fields'] )
								? $group['fields']
								: array();
							?>
							<section class="wem-group">
								<h2 class="wem-group__title">
									<?php echo esc_html( $group['title'] ?? '字段组' ); ?>
								</h2>

								<?php if ( ! empty( $group['description'] ) ) : ?>
									<p class="wem-group__description">
										<?php echo esc_html( $group['description'] ); ?>
									</p>
								<?php endif; ?>

								<?php foreach ( $fields as $field ) : ?>
									<?php if ( ! is_array( $field ) ) : ?>
										<?php continue; ?>
									<?php endif; ?>

									<div class="wem-field">
										<h3 class="wem-field__label">
											<?php echo esc_html( $field['label'] ?? $field['key'] ?? '字段' ); ?>
										</h3>

										<div class="wem-field__value">
											<?php wem_test_theme_render_field_value( $field, $post_id ); ?>
										</div>
									</div>
								<?php endforeach; ?>

								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<div class="wem-meta">
										测试信息：
										Post ID <?php echo esc_html( $post_id ); ?> ·
										Post Type <?php echo esc_html( $post_type ); ?>
									</div>
								<?php endif; ?>
							</section>
						<?php endforeach; ?>
					<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
						<section class="wem-group">
							<p class="wem-empty">当前 Post Type 没有关联 WEM 字段组。</p>
						</section>
					<?php endif; ?>

				<?php endif; ?>

			</article>
		<?php endwhile; ?>

	<?php else : ?>

		<p>暂无内容。</p>

	<?php endif; ?>
</main>

<?php wp_footer(); ?>
</body>
</html>
