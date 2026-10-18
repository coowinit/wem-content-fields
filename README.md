# WEM Content Fields

`wem-content-fields` 是一款轻量、可迁移的 WordPress 结构化字段管理插件，用于定义“某个 Post Type 有哪些结构化属性”。

插件以 WordPress 原生 API 为核心：字段定义保存到 `wp_options`，实际字段值保存到 `wp_postmeta`，并通过 `register_post_meta()` 接入 WordPress REST API。

当前版本：**v1.1.0**（新增字段组显示范围）  
Schema：**v2（兼容导入 v1）**

## 字段组显示范围

字段组支持配置后台显示范围：

- 默认模式：字段组显示在已关联的内容类型中，保持原有行为。
- 指定页面模式：字段组仅显示在所选 WordPress Page 的编辑界面。
- 显示条件只影响后台字段组的适用范围，不会删除已有文章的字段值。
- JSON Schema v2 使用页面路径引用来迁移指定页面规则；目标站点找不到对应页面时会中止导入，避免意外扩大显示范围。
- Schema v1 导入仍兼容，旧字段组默认按原来的全局关联规则处理。

此功能已纳入 v1.1.0 发布范围。

## 项目定位

```text
wem-content-types
= 定义“有什么内容、如何分类”

wem-content-fields
= 定义“这些内容有什么属性”

wem-content-model
= 后续组合具体企业网站业务模型
```

`wem-content-fields` 与 `wem-content-types` 解耦，可独立用于 WordPress Posts、Pages 或其他公开 CPT。

## 核心原则

- 小核心、清晰边界
- 中文管理员后台
- 字段定义与字段值分离
- 优先使用 WordPress 原生 API
- Field Key / Meta Key 稳定
- 删除字段定义不删除历史内容数据
- 支持 REST API
- 支持 JSON 跨站迁移字段结构
- 不以替代 ACF / Meta Box 为目标

## 功能

### 字段组

- Field Group 创建、编辑、删除
- 一个字段组可以关联一个或多个 Post Type
- 当前不存在的 Post Type 关联不会被自动删除
- 字段支持上移 / 下移排序
- 字段组至少包含一个字段后才能保存

### 7 种核心字段

| 后台类型 | Type | Post Meta 数据 | REST |
|---|---|---|---|
| 单行文本 | `text` | string | string |
| 多行文本 | `textarea` | string | string |
| 微型富文本 | `editor` | 安全 HTML string | string |
| 图片 | `image` | Attachment ID | integer |
| PDF 文件 | `file` | Attachment ID | integer |
| 下拉框 | `select` | Option Value | string |
| 多选框 | `checkbox` | Option Value 数组 | array<string> |

图片和 PDF 均使用 WordPress 原生媒体库 `wp.media`。

- 图片只支持单张图片，并保存 Attachment ID
- PDF 只支持单个 PDF，并保存 Attachment ID
- PDF 在服务端再次验证 MIME 为 `application/pdf`
- Select / Checkbox 采用稳定的 `value + label` 选项结构

## 数据结构

### Field Group Schema

```text
key
title
description
post_types[]
location
fields[]
```

### Field Schema

```text
key
label
type
meta_key
description
show_in_rest
settings
```

### Meta Key

统一使用：

```text
_wem_cf_{field_key}
```

例如：

```text
Field Key
product_number

Meta Key
_wem_cf_product_number
```

Field Key、Meta Key、Field Type 创建后保持稳定。

## 数据存储

### 字段定义

保存到：

```text
wp_options
├── wem_cf_field_groups
└── wem_cf_schema_version
```

Options 使用非自动加载方式保存。

### 字段值

保存到 WordPress 标准：

```text
wp_postmeta
```

例如：

```text
_wem_cf_product_number      → CD-01
_wem_cf_product_main_image  → 69
_wem_cf_product_catalog     → 70
```

插件不建立自定义数据表。

## REST API

字段通过：

```php
register_post_meta()
```

注册到 WordPress Meta Registry。

如果字段开启“允许通过 REST API 访问”，且对应 Post Type 开启 REST，则字段可出现在标准 WordPress REST Response 的 `meta` 中。

例如：

```json
{
  "meta": {
    "_wem_cf_product_number": "CD-01",
    "_wem_cf_product_series": "vista",
    "_wem_cf_product_applications": [
      "commercial",
      "hotel"
    ],
    "_wem_cf_product_main_image": 69,
    "_wem_cf_product_catalog": 70
  }
}
```

插件不会自行创建字段 REST Endpoint。

## 管理权限

v1.0.0 默认只有管理员使用字段结构管理和 WEM Meta Box：

```text
manage_options
```

同时保存文章字段值时仍检查：

```text
edit_post
```

如未来需要扩展权限，可使用：

```php
wem_cf_manage_capability
```

Filter。

## 安全与验证

插件包含：

- Nonce 验证
- Autosave 检查
- Revision 检查
- Capability 检查
- Field 白名单保存
- 字段类型独立 Sanitize
- Select 合法 Value 白名单
- Checkbox 合法 Value 白名单
- Image Attachment 类型验证
- PDF Attachment MIME 验证
- Field Group Key 唯一检查
- Field Key 全局唯一检查
- Meta Key 全局唯一检查
- Option Value 字段内唯一检查

空值默认使用 `delete_post_meta()` 删除对应字段值，而不是写入无意义空记录。

## 删除与重装策略

这是插件的重要数据安全原则：

> 字段配置可以变化，业务内容数据不会被配置操作顺带删除。

因此：

- 删除字段定义不会删除对应 Post Meta
- 删除字段组不会删除字段值
- 停用插件不会删除字段定义或字段值
- 删除插件文件不会主动清理字段定义或字段值
- 重新安装并启用插件后，原字段结构和数据可以重新恢复

插件没有提供自动清理全部数据的 `uninstall.php`。

## JSON 导入 / 导出

后台：

```text
WEM 字段
├── 字段组
└── 导入导出
```

### 导出

可以选择一个或多个字段组导出。

JSON 顶层结构：

```json
{
  "format": "wem-content-fields",
  "schema_version": 2,
  "plugin_version": "1.0.0",
  "exported_at": "2026-09-16T00:00:00+00:00",
  "field_groups": {}
}
```

导出只包含 Stored Definitions，不包含：

- Posts / Pages / CPT 内容
- 实际 Post Meta 字段值
- 图片 / PDF 文件
- Attachment ID 内容数据
- 用户信息
- 站点 URL
- 数据库信息
- Runtime Filter 注入的字段定义

### 导入

导入采用：

```text
选择 JSON
↓
检查文件
↓
预览
↓
确认导入
↓
写入 wp_options
```

导入不会自动覆盖已有定义。

以下冲突会阻止整次导入：

- Field Group Key 已存在
- Field Key 已存在
- Meta Key 已存在
- Schema 数据不合法
- 指定页面规则无效或无法在目标站点唯一匹配
- Field / Option 定义不合法

如果目标网站当前还没有对应 Post Type，只显示“当前不可用”提醒，不阻止导入。以后该 CPT 注册后，字段组会自动重新生效。

## 跨站迁移建议

典型流程：

```text
网站 A
↓
WEM 字段 → 导出 JSON
↓
网站 B 安装 WEM Content Fields
↓
导入 JSON
↓
恢复 Field Groups / Fields / Options
↓
安装或启用对应 CPT
```

Import / Export 迁移的是字段结构 Schema，不是 WordPress 内容和媒体文件。

## Runtime Definitions

插件区分：

```text
Stored Definitions
wp_options 中保存的字段定义

Runtime Definitions
Stored Definitions + 运行时扩展定义
```

运行时字段组可通过：

```php
wem_cf_runtime_field_groups
```

Filter 注入。

这为未来 `wem-content-model` 提供稳定的组合入口，同时不会直接修改 `wem_cf_field_groups`。

## 主要 Filters

### `wem_cf_manage_capability`

修改插件管理与 WEM Meta Box 所需权限。

### `wem_cf_available_post_types`

调整后台字段组可关联的 Post Type 列表。

### `wem_cf_runtime_field_groups`

向运行时字段定义中追加或调整 Field Groups。

### `wem_cf_add_custom_fields_support`

控制插件是否为已关联字段的 Post Type 自动添加 `custom-fields` support。

### `wem_cf_meta_args`

在调用 `register_post_meta()` 前调整最终 Meta 注册参数。

## 前台使用

插件只负责结构化字段定义和数据保存，不负责前台模板。

普通文本字段可以直接使用：

```php
$product_number = get_post_meta(
    get_the_ID(),
    '_wem_cf_product_number',
    true
);
```

图片字段保存 Attachment ID：

```php
$image_id = (int) get_post_meta(
    get_the_ID(),
    '_wem_cf_product_main_image',
    true
);

echo wp_get_attachment_image( $image_id, 'large' );
```

PDF 字段同样保存 Attachment ID：

```php
$file_id  = (int) get_post_meta(
    get_the_ID(),
    '_wem_cf_product_catalog',
    true
);
$file_url = wp_get_attachment_url( $file_id );
```

Select / Checkbox 建议根据字段定义中的 Options 将稳定 Value 映射为前台 Label。


## 测试主题

GitHub 仓库提供一个极简的 **WEM Test Theme**，用于手工验证字段保存和前台真实渲染。

仓库位置：

```text
tests/manual/wem-test-theme/
```

测试主题支持按字段类型实际输出：

- 单行文本 / 多行文本
- 微型富文本 HTML
- 图片 Attachment
- PDF 文件链接
- Select 的 Label
- Checkbox 的 Label 列表

它还用于验证插件停用、删除、重装后的字段定义与 Post Meta 数据持久性。

> 测试主题不是插件运行所必需，也不建议用于生产环境。

正式插件 Release 包不会包含测试主题；测试主题作为独立 Release 附件和仓库测试资源提供。

## 安装要求

- WordPress 6.4+
- PHP 7.4+

## 当前边界

v1.0.0 暂不包含：

- Taxonomy Meta
- User Meta
- Repeater
- Relationship
- 字段级 Conditional Logic（根据其他字段值动态显示/隐藏）
- Gallery / 多图字段
- 通用文件字段
- Frontend Form
- Template Builder
- AI
- Field Group Enable / Disable
- Field Group 自定义排序
- Import 自动覆盖 / Merge

这些功能不会因为“常见”而自动加入，后续仍按真实业务需求评估。

## 版本

### v1.1.0

- 新增字段组显示范围：全部关联内容类型或指定 WordPress Pages。
- 指定页面模式仅适用于 Pages，并支持选择多个页面。
- REST API Meta 授权遵守字段组的页面显示范围。
- JSON Schema v2 导出页面路径引用，导入时映射到目标站点页面；继续兼容 Schema v1 导入。
- 增加 GitHub Actions PHP 语法检查。

### v1.0.0

首个稳定版本：

- 完成 Field Groups
- 完成 7 种核心字段类型
- 完成 Post Meta / REST API
- 完成 Gutenberg 兼容微型富文本
- 完成 WordPress Media Library 图片 / PDF 字段
- 完成安全校验与数据保护策略
- 完成 JSON Import / Export
- 完成跨站 Schema 迁移
- 完成插件停用、删除、重装与字段定义删除恢复测试

## License

GPL-2.0-or-later
