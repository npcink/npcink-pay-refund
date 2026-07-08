# 项目改造历史与当前状态

本文记录 `Npcink Pay Refund` 在 2026-07-08 前后的主要改造决策、验证方式和发布状态，便于后续维护者或 AI 继续接手。

## 当前状态

- 插件名称：`Npcink Pay Refund`
- 插件 slug：`npcink-pay-refund`
- 主文件：`npcink-pay-refund.php`
- Text Domain：`npcink-pay-refund`
- Composer 包名：`npcink/pay-refund`
- 当前版本：`1.3.0`
- GitHub 仓库：`https://github.com/muze-page/npcink-pay-refund`
- GitHub Release：`https://github.com/muze-page/npcink-pay-refund/releases/tag/v1.3.0`
- 发布包：`build/npcink-pay-refund-1.3.0.zip`

当前本地仓库目录仍是 `/Users/muze/gitee/magick-refund`。这是本机路径遗留，不影响插件源码、GitHub remote、发布包或 WordPress 安装目录。若要继续降噪，可把本地目录移动到 `/Users/muze/github/npcink-pay-refund`。

## 命名改造

项目已从早期的 `Mare / magick-refund` 命名迁移到 `Npcink Pay Refund`：

- 对外名称改为 `Npcink Pay Refund`
- 内部类名前缀改为 `Npcink_Pay_Refund`
- 文件名前缀改为 `npcink-pay-refund`
- Option、AJAX action、数据库表等运行时标识改为 `npcink_pay_refund_*`
- 退款记录表改为 `{$wpdb->prefix}npcink_pay_refund_order`
- 后台资源改为 `admin/js/npcink-pay-refund-*.js` 和 `admin/css/npcink-pay-refund-admin.css`

项目还在开发中，没有用户和历史兼容包袱，因此本次没有做旧 option、旧表名、旧插件入口的迁移兼容。后续如果已经有真实用户，再改运行时标识时必须先设计迁移策略。

## 后台设置与权限

设置页已经从 Vite 资源迁移为 WordPress 原生后台界面，入口在：

- `插件 -> 退款配置`
- 页面 slug：`plugins.php?page=npcink_pay_refund_config`

设置页按 tab 分组：

- 支付宝
- 微信
- 退款权限
- 数据与卸载

退款专员策略：

- 管理员默认拥有退款权限，不需要添加为退款专员
- 只有作者及以上权限、且不是管理员的用户可被添加为退款专员
- 退款专员通过搜索添加，列表只展示已选中的退款专员
- 退款专员登录后台后，只允许访问配置中放行的管理页面

退款操作入口在：

- `仪表盘 -> 订单退款`
- 页面 slug：`index.php?page=npcink_pay_refund_query`

## 支付配置策略

支付宝：

- 需要 APP ID、应用私钥、支付宝公钥
- 空配置会在本地 SDK 初始化前被阻断，避免继续发起无效请求
- 配置检测会展示 SDK、字段和初始化状态

微信：

- 保存设置时不强制填写微信支付公钥 ID / 平台证书序列号，也不强制填写微信支付公钥 / 平台证书
- 但执行微信订单查询或退款前，官方 `wechatpay/wechatpay` SDK 仍需要可用于验签的公钥 ID / 平台证书序列号和公钥 / 平台证书
- 因此 UI 文案和配置检测明确区分了“保存时允许留空”和“执行 API 前必须配置”

支付密钥保存策略：

- 密钥类字段单独保存到 `npcink_pay_refund_secrets`
- 常规配置保存到 `npcink_pay_refund_config`
- 密钥选项设置为非 autoload
- 设置页留空密钥字段会保留已有密钥值

## 安全与性能改造

已完成的主要安全与性能改造：

- 退款查询、退款提交、配置检测、记录下载均增加 nonce 与权限校验
- AJAX 错误统一返回 JSON，避免后台 500 泄漏给操作者
- 支付 SDK 尽量延迟到权限校验后初始化
- 退款提交增加订单级重复锁，避免重复点击或并发触发重复退款
- 退款记录导出限制单次最多 5000 条
- CSV 导出处理公式注入风险
- 退款记录表增加唯一键和时间索引
- 设置和密钥 option 设置为非 autoload，减少前台自动加载压力
- 卸载时按配置删除退款记录表和插件设置

## 发布与验证

发布包必须通过 Composer 构建：

```bash
composer build:zip
```

不要直接安装源码包，因为微信支付 SDK 等 Composer 依赖不会自动存在。正式安装包必须包含：

- `npcink-pay-refund/npcink-pay-refund.php`
- `npcink-pay-refund/vendor/autoload.php`

当前回归入口：

```bash
composer verify
```

`composer verify` 会执行：

- `composer validate --strict`
- `composer audit`
- PHP lint
- JavaScript `node --check`
- 构建 ZIP
- 检查 ZIP 必须包含 `vendor/autoload.php`
- 检查 ZIP 不包含 `vite/`、`bin/`、`build/`、旧 SDK 目录
- 安装 ZIP 到本地测试站
- 执行 `bin/smoke-admin.php` 后台烟测
- 执行 Plugin Check

后台烟测覆盖：

- 配置页 tab 和关键控件存在
- 订单退款页关键控件存在
- 管理员默认可退款
- 作者退款专员可退款
- 订阅者不能退款
- 管理员和订阅者不能被保存为退款专员
- 空支付宝配置不能初始化 SDK
- 空微信配置不能初始化客户端

## 本地测试站

当前本地测试站：

- URL：`http://test.local`
- WordPress 路径：`/Users/muze/Local Sites/test/app/public`
- 安装后的插件目录：`wp-content/plugins/npcink-pay-refund`

注意：本地测试站安装的是发布 ZIP 解压后的插件拷贝，不是指向当前仓库的 symlink。每次运行 `composer verify` 都会重新安装当前构建出的 ZIP。

旧的本地测试残留已清理：

- `wp-content/plugins/magick-refund` symlink 已删除
- `active_plugins` 中的 `magick-refund/mare.php` 已删除
- `build/magick-refund*` 旧构建产物已删除

## GitHub 发布状态

项目已从 Gitee 切到 GitHub：

- 当前 `origin`：`https://github.com/muze-page/npcink-pay-refund.git`
- Gitee remote 不再使用
- GitHub 仓库为 public
- `main` 已推送到 GitHub
- `v1.3.0` tag 已推送
- `v1.3.0` Release 已上传 `npcink-pay-refund-1.3.0.zip`

历史 tag `v1.0.8`、`v1.1.1`、`v1.1.2` 也已随 tags 推送到 GitHub。旧提交历史中仍可能出现旧项目名，这是正常 Git 历史，不建议 rewrite。

## 后续建议

短期建议：

- 把本地仓库目录从 `/Users/muze/gitee/magick-refund` 移到 `/Users/muze/github/npcink-pay-refund`
- 继续用 `composer verify` 作为每次变更后的最小回归门槛
- 首次真实商户联调按 `docs/REFUND-INTEGRATION-CHECKLIST.md` 执行并记录结果

发布前建议：

- 补充 GitHub README 截图和后台入口说明
- 增加真实支付宝、微信沙箱或低风险订单联调记录
- 如开始有真实用户，再引入显式 schema/version migration，不要直接改 option/table/action 名称
