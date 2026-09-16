# WEM Test Theme

`WEM Test Theme` 是 `wem-content-fields` 的极简手工测试主题，用于验证字段定义、Post Meta 保存和前台真实渲染结果。

> 该主题仅用于开发与验收测试，不建议用于正式生产网站。

## 文件结构

```text
wem-test-theme/
├── index.php
├── functions.php
├── style.css
└── README.md
```

## 主要用途

用于验证 `wem-content-fields` 当前 7 种核心字段：

- 单行文本
- 多行文本
- 微型富文本
- 图片
- PDF 文件
- 下拉框
- 多选框

同时可用于验证：

- Field Group 与 Post Type 的关联
- 字段顺序
- Post Meta 数据读取
- Select / Checkbox 的 Value → Label 映射
- Attachment ID → 图片输出
- Attachment ID → PDF 链接输出
- 富文本 HTML 的实际前台渲染
- 插件停用、重装后 Stored Definitions / Post Meta 是否仍存在

## 前台渲染规则

测试主题按字段定义自动输出实际内容：

| 字段类型 | 前台测试输出 |
|---|---|
| 单行文本 | 直接显示文本 |
| 多行文本 | 显示文本并保留换行 |
| 微型富文本 | 渲染安全 HTML |
| 图片 | 根据 Attachment ID 输出实际图片 |
| PDF 文件 | 根据 Attachment ID 输出可点击 PDF 链接 |
| 下拉框 | 将存储 Value 映射为 Label |
| 多选框 | 将多个 Value 映射为 Label 列表 |

## 使用方法

1. 将 `wem-test-theme` 目录压缩为 ZIP，或直接复制到 `wp-content/themes/`。
2. 在 WordPress 后台启用 **WEM Test Theme**。
3. 启用并配置 `wem-content-fields`。
4. 为某个 Post Type 建立字段组并填写字段值。
5. 打开对应内容详情页，检查各字段是否按实际类型正确渲染。

## 说明

测试主题会直接读取：

```text
wem_cf_field_groups
```

以及标准 WordPress `postmeta`。

因此即使暂时停用 `wem-content-fields`，已保存的 Stored Definitions 和 Post Meta 仍可用于测试数据持久性。这是测试用途的刻意设计，不代表插件停用后仍在运行。

正式主题通常不需要自动枚举全部字段，可以直接使用 WordPress 原生 API 按需要读取：

```php
get_post_meta( get_the_ID(), '_wem_cf_product_number', true );
```

## 版本

当前测试主题版本：**v1.1.0**
