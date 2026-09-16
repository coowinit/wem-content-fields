<?php
/**
 * WEM Test Theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wem_test_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'wem_test_theme_setup' );

function wem_test_theme_enqueue_styles() {
	wp_enqueue_style(
		'wem-test-theme',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'wem_test_theme_enqueue_styles' );

/**
 * 获取当前运行时字段组。
 *
 * 测试主题优先读取 wem-content-fields 的 Stored Definitions，
 * 再经过 runtime filter，以便未来 wem-content-model 注入字段后也能测试。
 */
function wem_test_theme_get_field_groups() {
	$groups = get_option( 'wem_cf_field_groups', array() );

	if ( ! is_array( $groups ) ) {
		$groups = array();
	}

	$groups = apply_filters( 'wem_cf_runtime_field_groups', $groups );

	return is_array( $groups ) ? $groups : array();
}

/**
 * 获取当前 Post Type 对应的字段组。
 */
function wem_test_theme_get_groups_for_post_type( $post_type ) {
	$result = array();

	foreach ( wem_test_theme_get_field_groups() as $group ) {
		if ( ! is_array( $group ) ) {
			continue;
		}

		$post_types = isset( $group['post_types'] ) && is_array( $group['post_types'] )
			? $group['post_types']
			: array();

		if ( in_array( $post_type, $post_types, true ) ) {
			$result[] = $group;
		}
	}

	return $result;
}

/**
 * 将 Select / Checkbox 的存储 value 转换为后台配置的 Label。
 */
function wem_test_theme_option_label( array $field, $value ) {
	$options = isset( $field['settings']['options'] ) && is_array( $field['settings']['options'] )
		? $field['settings']['options']
		: array();

	foreach ( $options as $option ) {
		if ( ! is_array( $option ) ) {
			continue;
		}

		if ( isset( $option['value'] ) && (string) $option['value'] === (string) $value ) {
			return isset( $option['label'] ) ? (string) $option['label'] : (string) $value;
		}
	}

	return (string) $value;
}

/**
 * 渲染单个 WEM 字段的真实前台内容。
 */
function wem_test_theme_render_field_value( array $field, $post_id ) {
	$meta_key = isset( $field['meta_key'] ) ? (string) $field['meta_key'] : '';
	$type     = isset( $field['type'] ) ? (string) $field['type'] : '';

	if ( '' === $meta_key ) {
		echo '<span class="wem-empty">未填写</span>';
		return;
	}

	$exists = metadata_exists( 'post', $post_id, $meta_key );
	$value  = get_post_meta( $post_id, $meta_key, true );

	if ( ! $exists || '' === $value || array() === $value || 0 === $value || '0' === $value ) {
		echo '<span class="wem-empty">未填写</span>';
		return;
	}

	switch ( $type ) {
		case 'editor':
			echo wp_kses_post( $value );
			break;

		case 'textarea':
			echo nl2br( esc_html( (string) $value ) );
			break;

		case 'image':
			$attachment_id = absint( $value );

			if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
				echo wp_get_attachment_image(
					$attachment_id,
					'large',
					false,
					array(
						'class' => 'wem-field__image',
					)
				);
			} else {
				echo '<span class="wem-empty">图片不存在</span>';
			}
			break;

		case 'file':
			$attachment_id = absint( $value );
			$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			$mime          = $attachment_id ? get_post_mime_type( $attachment_id ) : '';

			if ( $url && 'application/pdf' === $mime ) {
				$title = get_the_title( $attachment_id );

				if ( '' === trim( $title ) ) {
					$file  = get_attached_file( $attachment_id );
					$title = $file ? wp_basename( $file ) : '查看 PDF';
				}

				printf(
					'<a class="wem-file" href="%1$s" target="_blank" rel="noopener">📄 %2$s</a>',
					esc_url( $url ),
					esc_html( $title )
				);
			} else {
				echo '<span class="wem-empty">PDF 文件不存在</span>';
			}
			break;

		case 'select':
			echo esc_html( wem_test_theme_option_label( $field, $value ) );
			break;

		case 'checkbox':
			$values = is_array( $value ) ? $value : array();

			if ( ! $values ) {
				echo '<span class="wem-empty">未填写</span>';
				break;
			}

			echo '<ul>';
			foreach ( $values as $item ) {
				echo '<li>' . esc_html( wem_test_theme_option_label( $field, $item ) ) . '</li>';
			}
			echo '</ul>';
			break;

		case 'text':
		default:
			echo esc_html( (string) $value );
			break;
	}
}
