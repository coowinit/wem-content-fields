<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wem-cf-wrap">
	<div class="wem-cf-page-head">
		<div>
			<h1 class="wp-heading-inline">字段组</h1>
			<p class="description">定义某个内容类型具备哪些结构化字段。字段定义保存在 Options，字段值保存在 WordPress Post Meta。</p>
		</div>
		<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=wem-content-fields&action=add' ) ); ?>">新建字段组</a>
	</div>

	<table class="wp-list-table widefat fixed striped table-view-list wem-cf-groups-table">
		<thead>
		<tr>
			<th>名称</th>
			<th>应用于</th>
			<th class="wem-cf-col-count">字段数</th>
			<th class="wem-cf-col-actions">操作</th>
		</tr>
		</thead>
		<tbody>
		<?php if ( empty( $groups ) ) : ?>
			<tr><td colspan="4">暂无字段组。可以先创建一个“产品基本信息”字段组进行测试。</td></tr>
		<?php else : ?>
			<?php foreach ( $groups as $key => $group ) : ?>
				<?php
				$edit_url = admin_url( 'admin.php?page=wem-content-fields&action=edit&group=' . rawurlencode( $key ) );
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=wem_cf_delete_field_group&group=' . rawurlencode( $key ) ),
					'wem_cf_delete_field_group_' . $key
				);
				$post_types = (array) ( $group['post_types'] ?? [] );
				$post_type_labels = [];
				foreach ( $post_types as $post_type_slug ) {
					$object = get_post_type_object( $post_type_slug );
					$post_type_labels[] = $object ? ( $object->labels->name ?? $post_type_slug ) : $post_type_slug . '（当前不可用）';
				}
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $group['title'] ?? $key ); ?></a></strong>
						<div class="wem-cf-machine-key"><?php echo esc_html( $key ); ?></div>
					</td>
					<td><?php echo esc_html( implode( '、', $post_type_labels ) ); ?></td>
					<td><?php echo esc_html( (string) count( (array) ( $group['fields'] ?? [] ) ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>">编辑</a>
						<span aria-hidden="true"> | </span>
						<a class="wem-cf-delete-group" href="<?php echo esc_url( $delete_url ); ?>" data-name="<?php echo esc_attr( $group['title'] ?? $key ); ?>">删除</a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
