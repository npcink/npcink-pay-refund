# WordPress.org 上架准备记录

本文记录 2026-07-08 对 `Npcink Pay Refund` 进行 WordPress.org Plugin Directory 上架准备时形成的判断、材料、验证结果和后续动作。

## 结论

当前项目适合提交到 WordPress.org Plugin Directory，但定位必须明确为“独立后台退款操作台”，而不是 WooCommerce 支付网关。

项目的差异化是：

- 不创建支付方式，不参与 checkout。
- 面向已经存在的支付宝/微信支付订单号，提供后台查询、全额退款、权限控制、退款记录和重复退款锁。
- 可用于非 WooCommerce 或自有支付流程后的退款审计场景。

如果按“WooCommerce 支付网关”定位，会和现有插件高度重叠；按“独立退款与审计工具”定位，重叠明显降低。

## 同类插件观察

通过 WordPress.org 插件目录/API 检索，已发现相近插件主要集中在 WooCommerce 支付网关方向：

- `wpyaa-alipay-wechat-for-woocommerce`：WooCommerce 微信支付、支付宝支付和退款。
- `wenprise-alipay-checkout-for-woocommerce`：WooCommerce 支付宝网关，支持 WooCommerce 订单内退款。
- `wenprise-wechatpay-checkout-for-woocommerce`：WooCommerce 微信支付网关，支持 WooCommerce 订单内退款。

这些插件说明目录里确实已有“支付宝/微信 + 退款”能力，但它们基本绑定 WooCommerce 订单流。当前插件应避免使用“网关”“收款”“WooCommerce 替代品”等容易撞位的表达。

## 已完成的上架材料

### 标准 readme

已新增 `readme.txt`，用于 WordPress.org 插件目录解析。

内容包括：

- 插件摘要和功能描述。
- 明确说明不是 WooCommerce gateway。
- 第三方服务披露。
- 安装说明。
- FAQ。
- 截图说明。
- Changelog。

第三方服务披露覆盖：

- 支付宝开放平台 / Alipay EasySDK。
- 微信支付 API v3 / `wechatpay/wechatpay` SDK。
- 查询与退款时分别会发送哪些订单、金额、退款原因和商户凭据相关数据。
- 商户密钥存储在 WordPress options 中，需限制管理员、备份和数据库导出权限。

### README

已同步更新 `README.md`，补充：

- 项目定位。
- 第三方服务说明。
- 支付宝和微信支付 API 数据传递差异。

### 截图

已生成 WordPress.org 截图资产：

- `assets/screenshot-1.png`：支付宝配置、密钥占位和配置检测。
- `assets/screenshot-2.png`：退款专员权限和退款记录可见范围。
- `assets/screenshot-3.png`：订单退款操作台和近期退款记录。
- `assets/screenshot-4.png`：退款记录搜索和筛选。

截图生成时使用本地临时管理员、临时退款专员和演示退款记录。截图完成后已恢复本地测试站原始配置，并删除临时账号和演示记录。

### 图标和横幅

用户提供的原始图片已整理到 `source-assets/`。该目录用于保存可追溯的上架源素材，可提交到 Git，但不进入插件安装包。

当前源素材：

- `source-assets/icon-source.png`
- `source-assets/banner-source.png`

`sj/` 仅作为本地临时素材目录，不进入安装包，也不提交到 Git。

已从原始素材生成 WordPress.org 规范资产：

- `assets/icon-256x256.png`
- `assets/icon-128x128.png`
- `assets/banner-1544x500.png`
- `assets/banner-772x250.png`

实际尺寸已检查，均与文件名一致。

## 打包策略

`assets/` 是 WordPress.org SVN 顶层资产目录使用的成品材料，不应放进插件安装包。`source-assets/` 是可提交的上架源素材目录，也不应进入安装包。`sj/` 是本地临时素材目录，同样不应进入安装包。

已在 `.distignore` 排除：

- `assets/`
- `source-assets/`
- `sj/`
- `bin/`
- `build/`
- `node_modules/`
- `vite/`
- `admin/sdk/`
- Alipay EasySDK 的 Java/C# 示例和测试目录
- 部分 vendor 测试/配置噪音文件

这样安装包只保留运行时需要的插件代码、文档、语言文件和 Composer 依赖。

## 安装包体积判断

当前发布包：

- 路径：`build/npcink-pay-refund-1.3.0.zip`
- 大小：约 `704K`
- 解压后：约 `3.1M`

体积主要来自 Composer `vendor/`，尤其是：

- `wechatpay/wechatpay`
- `alipaysdk/easysdk`
- `guzzlehttp/*`
- `psr/*`
- `alibabacloud/*`

这些依赖是支付宝/微信退款运行所需。若删除 `vendor/autoload.php` 或相关 SDK，用户从 WordPress 后台安装 zip 后将无法正常使用支付 API。

结论：`704K` 对带官方支付 SDK 的 WordPress 插件是合理体积。可以继续微调 vendor 文档文件，但收益很小，且可能降低依赖授权和审核可读性。当前建议保持现状。

## 验证结果

最终执行：

```bash
composer verify
```

结果：

- `composer validate --strict` 通过。
- `composer audit` 无安全告警。
- PHP lint 通过。
- JavaScript `node --check` 通过。
- 发布包构建通过。
- ZIP 包包含 `vendor/autoload.php`。
- ZIP 包不包含 `assets/`、`source-assets/`、`sj/`、`bin/`、`build/` 等发布资产、源素材或开发目录。
- 本地 WordPress 安装激活 smoke test 通过。
- Plugin Check clean。

## Git 提交范围

本次应提交：

- `.distignore`
- `.gitignore`
- `README.md`
- `readme.txt`
- `docs/WPORG-LISTING-PREP.zh-CN.md`
- `docs/README.zh-CN.md`
- `assets/banner-1544x500.png`
- `assets/banner-772x250.png`
- `assets/icon-256x256.png`
- `assets/icon-128x128.png`
- `assets/screenshot-1.png`
- `assets/screenshot-2.png`
- `assets/screenshot-3.png`
- `assets/screenshot-4.png`
- `source-assets/icon-source.png`
- `source-assets/banner-source.png`

不提交：

- `sj/` 本地临时素材目录。
- `build/` 构建产物。
- `vendor/` 本地依赖目录。

## 下一步建议

1. 将本次提交推送到 GitHub。
2. 如准备提交 WordPress.org，使用 SVN 结构上传：
   - 插件代码放 `trunk/` 和 `tags/1.3.0/`。
   - `assets/` 放 WordPress.org 顶层资产目录。
3. 提交前再次运行 `composer verify`。
4. 用 WordPress.org readme validator 检查 `readme.txt`。
5. 上架后优先验证目录页展示：
   - 插件标题和短描述。
   - banner 是否裁切正常。
   - icon 是否清晰。
   - screenshot 顺序和 `readme.txt` 说明是否匹配。
