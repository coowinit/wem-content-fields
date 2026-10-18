<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$render_settings = static function ( array $field, int $index, array $schema ): void {
	if ( empty( $schema ) ) {
		echo '<div class="wem-cf-field-wide wem-cf-no-settings"><p class="description">该字段类型没有额外设置。</p></div>';
		return;
	}

	foreach ( $schema as $setting_key => $setting ) {
		if ( ! is_array( $setting ) ) {
			continue;
		}

		$type  = (string) ( $setting['type'] ?? 'text' );
		$label = (string) ( $setting['label'] ?? $setting_key );
		$help  = (string) ( $setting['help'] ?? '' );
		$value = $field['settings'][ $setting_key ] ?? '';
		$name  = 'group[fields][' . $index . '][settings][' . $setting_key . ']';

		if ( 'options' === $type ) {
			$options = is_array( $value ) ? array_values( $value ) : [];
			echo '<div class="wem-cf-field-wide wem-cf-options-setting">';
			echo '<div class="wem-cf-setting-title"><strong>' . esc_html( $label ) . '</strong></div>';
			if ( '' !== $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '<div class="wem-cf-options" data-next-option-index="' . esc_attr( (string) count( $options ) ) . '">';
			foreach ( $options as $option_index => $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}
				$option_value = (string) ( $option['value'] ?? '' );
				$option_label = (string) ( $option['label'] ?? '' );
				echo '<div class="wem-cf-option-row">';
				echo '<div class="wem-cf-option-value"><span>选项值</span><code>' . esc_html( $option_value ) . '</code><span class="wem-cf-lock">🔒</span>';
				echo '<input type="hidden" name="' . esc_attr( $name . '[' . $option_index . '][value]' ) . '" value="' . esc_attr( $option_value ) . '">';
				echo '<input type="hidden" name="' . esc_attr( $name . '[' . $option_index . '][_original_value]' ) . '" value="' . esc_attr( $option_value ) . '"></div>';
				echo '<label>显示名称<input type="text" name="' . esc_attr( $name . '[' . $option_index . '][label]' ) . '" value="' . esc_attr( $option_label ) . '" required></label>';
				echo '<button type="button" class="button-link-delete wem-cf-remove-option">删除</button>';
				echo '</div>';
			}
			echo '</div>';
			echo '<button type="button" class="button wem-cf-add-option" data-setting-key="' . esc_attr( $setting_key ) . '">+ 添加选项</button>';
			echo '</div>';
			continue;
		}

		echo '<label class="wem-cf-field-wide">' . esc_html( $label );
		if ( 'textarea' === $type ) {
			echo '<textarea rows="2" name="' . esc_attr( $name ) . '"' . ( '' !== $help ? ' placeholder="' . esc_attr( $help ) . '"' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
		} else {
			echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . ( '' !== $help ? ' placeholder="' . esc_attr( $help ) . '"' : '' ) . '>';
		}
		if ( '' !== $help ) {
			echo '<small>' . esc_html( $help ) . '</small>';
		}
		echo '</label>';
	}
};
?>
<div class="wrap wem-cf-wrap">
	<div class="wem-cf-page-head">
		<div>
			<h1><?php echo $is_new ? '新建字段组' : '编辑字段组'; ?></h1>
			<p class="description">后台名称和说明使用中文；字段组标识、字段标识使用稳定的英文机器标识。</p>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wem-content-fields' ) ); ?>">返回字段组</a>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wem-cf-group-form">
		<input type="hidden" name="action" value="wem_cf_save_field_group">
		<input type="hidden" name="editing_key" value="<?php echo esc_attr( $is_new ? '' : $config['key'] ); ?>">
		<?php wp_nonce_field( 'wem_cf_save_field_group' ); ?>

		<section class="wem-cf-panel">
			<h2>基本信息</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wem-cf-title">字段组名称 <span class="required">*</span></label></th>
					<td>
						<input class="regular-text" type="text" id="wem-cf-title" name="group[title]" value="<?php echo esc_attr( $config['title'] ); ?>" required>
						<p class="description">例如：产品基本信息、产品规格、下载资料。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wem-cf-key">字段组标识 <span class="required">*</span></label></th>
					<td>
						<?php if ( $is_new ) : ?>
							<input class="regular-text code" type="text" id="wem-cf-key" name="group[key]" value="<?php echo esc_attr( $config['key'] ); ?>" placeholder="product_basic" required>
						<?php else : ?>
							<code><?php echo esc_html( $config['key'] ); ?></code> <span class="wem-cf-lock">🔒 创建后不可修改</span>
							<input type="hidden" name="group[key]" value="<?php echo esc_attr( $config['key'] ); ?>">
						<?php endif; ?>
						<p class="description">仅允许小写英文、数字和下划线，例如 <code>product_basic</code>。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wem-cf-description">说明</label></th>
					<td><textarea class="large-text" rows="3" id="wem-cf-description" name="group[description]" placeholder="例如：用于管理产品页面的主要结构化信息。"><?php echo esc_textarea( $config['description'] ); ?></textarea></td>
				</tr>
			</table>
		</section>

		<section class="wem-cf-panel">
			<h2>应用到内容类型</h2>
			<p class="description">当前不存在但已经保存的内容类型会保留为“当前不可用”，以后重新注册后会自动恢复关联。</p>
			<div class="wem-cf-post-types">
				<?php foreach ( $available_post_types as $slug => $label ) : ?>
					<label><input type="checkbox" name="group[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $config['post_types'], true ) ); ?>> <?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code></label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="wem-cf-panel">
			<h2>显示条件</h2>
			<p class="description">设置该字段组在哪些页面的编辑界面中显示。此设置不改变前台页面布局，也不会删除已有字段数据。</p>
			<?php $location = isset( $config['location'] ) && is_array( $config['location'] ) ? $config['location'] : [ 'mode' => 'all', 'page_ids' => [] ]; ?>
			<fieldset class="wem-cf-location-options">
				<label><input type="radio" name="group[location][mode]" value="all" <?php checked( ( $location['mode'] ?? 'all' ), 'all' ); ?>> 全部关联内容类型</label>
				<label><input type="radio" name="group[location][mode]" value="specific_pages" <?php checked( ( $location['mode'] ?? 'all' ), 'specific_pages' ); ?>> 仅在指定页面显示</label>
			</fieldset>
			<div class="wem-cf-location-pages">
				<label for="wem-cf-location-page-ids"><strong>指定页面</strong></label>
				<select id="wem-cf-location-page-ids" name="group[location][page_ids][]" multiple size="6" class="large-text">
					<?php foreach ( $available_pages as $page ) : ?>
						<option value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php selected( in_array( (int) $page->ID, array_map( 'absint', (array) ( $location['page_ids'] ?? [] ) ), true ) ); ?>><?php echo esc_html( get_page_hierarchy( [ $page->ID => $page->post_title ] ) ? $page->post_title : $page->post_title ); ?> (ID: <?php echo esc_html( (string) $page->ID ); ?>)</option>
					<?php endforeach; ?>
				</select>
				<p class="description">按住 Ctrl（Windows）或 Command（Mac）可选择多个页面。选择“仅在指定页面显示”时，至少选择一个页面。</p>
			</div>
		</section>

		<section class="wem-cf-panel">
			<div class="wem-cf-panel-head">
				<div>
					<h2>字段组中的字段</h2>
					<p class="description">一个字段组可以包含多个字段。例如“产品基本信息”中可以添加“产品型号”“产品尺寸”“产品优势”等字段。字段顺序按这里的顺序显示在内容编辑页。</p>
				</div>
				<button type="button" class="button button-secondary" id="wem-cf-add-field">+ 添加字段</button>
			</div>

			<div id="wem-cf-fields" data-next-index="<?php echo esc_attr( (string) count( (array) $config['fields'] ) ); ?>">
				<?php foreach ( (array) $config['fields'] as $index => $field ) : ?>
					<?php
					$type_label = $field_type_labels[ $field['type'] ] ?? $field['type'];
					$type_schema = $field_settings[ $field['type'] ] ?? [];
					?>
					<div class="wem-cf-field-card" data-index="<?php echo esc_attr( (string) $index ); ?>">
						<div class="wem-cf-field-summary">
							<div>
								<strong class="wem-cf-summary-label"><?php echo esc_html( $field['label'] ); ?></strong>
								<span class="wem-cf-type-badge"><?php echo esc_html( $type_label ); ?></span>
								<div class="wem-cf-machine-key"><?php echo esc_html( $field['key'] ); ?></div>
							</div>
							<div class="wem-cf-field-actions">
								<button type="button" class="button-link wem-cf-move-up" aria-label="上移">↑</button>
								<button type="button" class="button-link wem-cf-move-down" aria-label="下移">↓</button>
								<button type="button" class="button-link wem-cf-toggle-field">编辑</button>
								<button type="button" class="button-link-delete wem-cf-remove-field">删除</button>
							</div>
						</div>
						<div class="wem-cf-field-editor" hidden>
							<input type="hidden" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ); ?>">
							<input type="hidden" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][_original_key]" value="<?php echo esc_attr( $field['key'] ); ?>">
							<input type="hidden" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][type]" value="<?php echo esc_attr( $field['type'] ); ?>">
							<div class="wem-cf-field-grid">
								<label>字段名称 <span class="required">*</span><input type="text" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" required></label>
								<label>字段标识 <code><?php echo esc_html( $field['key'] ); ?></code><span class="wem-cf-lock">🔒</span></label>
								<label>字段类型 <strong><?php echo esc_html( $type_label ); ?></strong><span class="wem-cf-lock">🔒</span></label>
								<label>Meta Key <code><?php echo esc_html( $field['meta_key'] ); ?></code><span class="wem-cf-lock">🔒</span></label>
								<label class="wem-cf-field-wide">说明<textarea rows="2" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][description]"><?php echo esc_textarea( $field['description'] ); ?></textarea></label>
								<?php $render_settings( $field, (int) $index, $type_schema ); ?>
								<label class="wem-cf-field-wide wem-cf-rest"><input type="checkbox" name="group[fields][<?php echo esc_attr( (string) $index ); ?>][show_in_rest]" value="1" <?php checked( ! empty( $field['show_in_rest'] ) ); ?>> 允许通过 REST API 访问</label>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="wem-cf-save-bar">
			<?php submit_button( $is_new ? '创建字段组' : '保存字段组', 'primary', 'submit', false ); ?>
		</div>
	</form>

	<template id="wem-cf-field-template">
		<div class="wem-cf-field-card is-open" data-index="__INDEX__">
			<div class="wem-cf-field-summary">
				<div>
					<strong class="wem-cf-summary-label">新字段</strong>
					<span class="wem-cf-type-badge">单行文本</span>
					<div class="wem-cf-machine-key wem-cf-summary-key">尚未设置标识</div>
				</div>
				<div class="wem-cf-field-actions">
					<button type="button" class="button-link wem-cf-move-up" aria-label="上移">↑</button>
					<button type="button" class="button-link wem-cf-move-down" aria-label="下移">↓</button>
					<button type="button" class="button-link wem-cf-toggle-field">收起</button>
					<button type="button" class="button-link-delete wem-cf-remove-field">删除</button>
				</div>
			</div>
			<div class="wem-cf-field-editor">
				<div class="wem-cf-field-grid">
					<label>字段名称 <span class="required">*</span><input type="text" name="group[fields][__INDEX__][label]" class="wem-cf-new-label" required></label>
					<label>字段标识 <span class="required">*</span><input type="text" name="group[fields][__INDEX__][key]" class="wem-cf-new-key code" placeholder="product_model" required><small>仅允许小写英文、数字和下划线；创建后锁定。</small></label>
					<label>字段类型<select name="group[fields][__INDEX__][type]" class="wem-cf-new-type"><?php foreach ( $field_type_labels as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><small>创建后不可修改。</small></label>
					<label>Meta Key <code class="wem-cf-meta-preview">_wem_cf_</code><small>系统自动生成，不需要填写。</small></label>
					<label class="wem-cf-field-wide">说明<textarea rows="2" name="group[fields][__INDEX__][description]" placeholder="告诉内容编辑者这个字段用于填写什么。"></textarea></label>
					<div class="wem-cf-field-wide wem-cf-dynamic-settings"></div>
					<label class="wem-cf-field-wide wem-cf-rest"><input type="checkbox" name="group[fields][__INDEX__][show_in_rest]" value="1" checked> 允许通过 REST API 访问</label>
				</div>
			</div>
		</div>
	</template>
</div>
