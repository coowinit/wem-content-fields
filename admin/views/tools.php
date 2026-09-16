<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$inspection = is_array( $preview ) && isset( $preview['inspection'] ) && is_array( $preview['inspection'] )
	? $preview['inspection']
	: null;
?>
<div class="wrap wem-cf-wrap">
	<div class="wem-cf-page-head">
		<div>
			<h1>导入导出</h1>
			<p class="description">迁移字段结构，不迁移文章内容、图片、PDF 或其他 Post Meta 实际值。</p>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wem-content-fields' ) ); ?>">返回字段组</a>
	</div>

	<?php if ( $inspection ) : ?>
		<section class="wem-cf-panel wem-cf-import-preview">
			<div class="wem-cf-panel-head">
				<div>
					<h2>导入预览</h2>
					<p class="description">文件：<?php echo esc_html( (string) ( $preview['filename'] ?? '' ) ); ?></p>
				</div>
				<span class="wem-cf-status-badge <?php echo $inspection['can_import'] ? 'is-ok' : 'is-conflict'; ?>">
					<?php echo $inspection['can_import'] ? '可以导入' : '存在冲突'; ?>
				</span>
			</div>

			<div class="wem-cf-import-summary">
				<div><strong>Schema</strong><span>v<?php echo esc_html( (string) $inspection['schema_version'] ); ?></span></div>
				<div><strong>字段组</strong><span><?php echo esc_html( (string) $inspection['group_count'] ); ?></span></div>
				<div><strong>字段</strong><span><?php echo esc_html( (string) $inspection['field_count'] ); ?></span></div>
				<div><strong>提醒</strong><span><?php echo esc_html( (string) $inspection['warning_count'] ); ?></span></div>
			</div>

			<?php if ( ! empty( $inspection['plugin_version'] ) || ! empty( $inspection['exported_at'] ) ) : ?>
				<p class="description">
					<?php if ( ! empty( $inspection['plugin_version'] ) ) : ?>来源插件版本：<?php echo esc_html( $inspection['plugin_version'] ); ?><?php endif; ?>
					<?php if ( ! empty( $inspection['exported_at'] ) ) : ?> · 导出时间：<?php echo esc_html( $inspection['exported_at'] ); ?><?php endif; ?>
				</p>
			<?php endif; ?>

			<table class="widefat striped wem-cf-import-table">
				<thead>
				<tr>
					<th>字段组</th>
					<th>应用于</th>
					<th>字段数</th>
					<th>状态</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $inspection['rows'] as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['title'] ); ?></strong><div class="wem-cf-machine-key"><?php echo esc_html( $row['key'] ); ?></div></td>
						<td><?php echo esc_html( implode( '、', $row['post_types'] ) ); ?></td>
						<td><?php echo esc_html( (string) $row['field_count'] ); ?></td>
						<td>
							<?php if ( 'conflict' === $row['status'] ) : ?>
								<strong class="wem-cf-text-error">冲突</strong>
								<?php foreach ( $row['conflicts'] as $message ) : ?><div><?php echo esc_html( $message ); ?></div><?php endforeach; ?>
							<?php elseif ( 'warning' === $row['status'] ) : ?>
								<strong class="wem-cf-text-warning">可导入，有提醒</strong>
								<?php foreach ( $row['warnings'] as $message ) : ?><div><?php echo esc_html( $message ); ?></div><?php endforeach; ?>
							<?php else : ?>
								<strong class="wem-cf-text-ok">新增</strong>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $inspection['conflicts'] ) ) : ?>
				<div class="notice notice-error inline"><p><strong>当前不会自动覆盖已有定义。</strong> 请先处理冲突，再重新导入。</p></div>
			<?php endif; ?>

			<div class="wem-cf-tool-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wem-content-fields-tools' ) ); ?>">取消</a>
				<?php if ( $inspection['can_import'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wem_cf_import_confirm">
						<input type="hidden" name="import_token" value="<?php echo esc_attr( $token ); ?>">
						<?php wp_nonce_field( 'wem_cf_import_confirm_' . $token ); ?>
						<button type="submit" class="button button-primary">确认导入</button>
					</form>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<div class="wem-cf-tools-grid">
		<section class="wem-cf-panel">
			<h2>导出字段结构</h2>
			<p class="description">导出 Stored Definitions。运行时由 <code>wem_cf_runtime_field_groups</code> 注入的字段不会被写入 JSON。</p>

			<?php if ( empty( $groups ) ) : ?>
				<p>当前没有可导出的字段组。</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wem_cf_export">
					<?php wp_nonce_field( 'wem_cf_export' ); ?>

					<div class="wem-cf-export-groups">
						<?php foreach ( $groups as $key => $group ) : ?>
							<label>
								<input type="checkbox" name="groups[]" value="<?php echo esc_attr( $key ); ?>" checked>
								<span><strong><?php echo esc_html( $group['title'] ?? $key ); ?></strong><small><?php echo esc_html( $key ); ?> · <?php echo esc_html( (string) count( (array) ( $group['fields'] ?? [] ) ) ); ?> 个字段</small></span>
							</label>
						<?php endforeach; ?>
					</div>

					<p><button type="submit" class="button button-primary">导出 JSON</button></p>
				</form>
			<?php endif; ?>
		</section>

		<section class="wem-cf-panel">
			<h2>导入字段结构</h2>
			<p class="description">先检查文件并预览，不会立即写入数据库。字段组、Field Key 或 Meta Key 与当前站点冲突时不会自动覆盖。</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wem_cf_import_preview">
				<?php wp_nonce_field( 'wem_cf_import_preview' ); ?>
				<p><input type="file" name="wem_cf_import_file" accept="application/json,.json" required></p>
				<p class="description">仅支持 WEM Content Fields Schema v<?php echo esc_html( (string) WEM_CF_SCHEMA_VERSION ); ?> JSON，最大 2 MB。</p>
				<p><button type="submit" class="button button-primary">检查文件</button></p>
			</form>
		</section>
	</div>
</div>
