# 项目改造历史与当前状态

本文记录 `Npcink Pay Refund` 在 2026-07-08 前后的主要改造决策、验证方式和发布状态，便于后续维护者或 AI 继续接手。

## 当前状态

- 插件名称：`Npcink Pay Refund`
- 插件 slug：`npcink-pay-refund`
- 主文件：`npcink-pay-refund.php`
- Text Domain：`npcink-pay-refund`
- Composer 包名：`npcink/pay-refund`
- 最低 PHP：`8.1`
- 当前开发版本：`1.3.7`
- GitHub 仓库：`https://github.com/npcink/npcink-pay-refund`
- 最新稳定 GitHub Release：`v1.3.3`
- WordPress.org 当前发布版本：`1.3.5`
- WordPress.org 目录页：`https://wordpress.org/plugins/npcink-pay-refund/`
- WordPress.org SVN 发布修订：`r3623533`

当前本地仓库目录是 `/Users/muze/gitee/npcink-pay-refund`。这是本机路径，不影响插件源码、GitHub remote、发布包或 WordPress 安装目录。若要继续降噪，可把本地目录移动到 `/Users/muze/github/npcink-pay-refund`。

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

## 1.3.1 正式使用前补强

1.3.1 主要面向正式使用前的可靠性、可解释性和真实站点缓存问题：

- 配置页补充支付宝、微信字段来源说明
- 微信证书序列号和商户私钥相邻展示，降低配置误填概率
- 增加退款时间窗口设置，默认 7 天，可配置 1 到 365 天
- 微信退款 `PROCESSING` 状态改为 pending 记录加 10 秒轮询查询
- 微信成功落库必须有 pending 或明确 fallback 上下文，避免空记录
- 再次发起微信退款前先检查 pending 状态，避免锁过期后重复提交
- 微信和支付宝错误日志改为摘要记录，不写完整支付网关响应体
- 卸载时清理微信 pending 和退款锁 option
- 退款记录导出按钮去掉“全部”字样，并说明最多导出最新 5000 条
- 后台 CSS/JS 资源版本改为 `插件版本 + 文件修改时间`，避免真实站点继续加载旧资源

详细开发复盘见 `docs/DEVELOPMENT-SUMMARY-1.3.1.zh-CN.md`。

## 1.3.2 退款结果卡片压缩

1.3.2 主要调整微信退款结果展示：

- 结果卡片最大宽度从 900px 缩到 720px
- 内边距、标题、说明和字段值字号下调，更接近 WordPress 后台工具界面
- 保留左侧状态色条和简洁状态 badge
- “通道状态”不再展示 `SUCCESS`、`PROCESSING` 等英文 API 状态码，改为中文状态文案

详细开发复盘见 `docs/DEVELOPMENT-SUMMARY-1.3.2.zh-CN.md`。

## 1.3.3 WordPress.org 审核与退款安全修正

为满足 WordPress.org 插件目录的审核要求：

- `readme.txt` 的贡献者账号改为实际提交账号 `muze233`
- 移除已由 WordPress.org 自动处理的 `load_plugin_textdomain()` 调用及其无用加载类
- 将最低 PHP 版本统一为 `8.1`，与发布包内 Composer 依赖保持一致
- 微信全额退款使用订单原总额，正确覆盖使用代金券的订单
- 本地退款记录写入失败时保留对账标记和重复退款锁；支付宝可在后续订单查询时只补记本地记录，不会再次调用退款接口
- 移除支付宝签名请求中的无效占位回调地址

支付密钥的保存与读取方式保持不变。

## 1.3.4 结果不明状态与查询优先重试

1.3.4 把“接口报错”进一步拆分为“明确失败”和“提供方结果不明”，重点避免网络中断后误放开重复退款：

- 支付宝通过 EasySDK 通用接口显式传入稳定 `out_request_no`
- 支付宝和微信都在退款提交前保存不含密钥、网关原文的最小请求上下文
- 支付宝结果不明时使用 `alipay.trade.fastpay.refund.query` 查询原退款请求号；只有明确未查询到退款才按原请求号和原参数重试
- 微信结果不明时先按原 `out_refund_no` 查询；只有签名验证后的查询明确返回 `404 RESOURCE_NOT_EXISTS` 才允许重试
- 鉴权失败、网络异常、系统超时、未知状态或响应订单号不匹配时继续保留状态和重复退款保护
- 后台通知汇总支付宝 uncertain、微信 pending 和本地 audit reconciliation 状态，提醒操作者先查询核对
- GitHub Actions 在 PHP 8.1 和 8.4 上执行同一套发布验证

本版本的自动化验证只能证明本地状态机、打包和兼容性基线；真实支付宝、微信商户退款仍须由商户操作者按 `docs/REFUND-INTEGRATION-CHECKLIST.md` 验证，未执行前不得记录为线上退款通过。

1.3.3 到 1.3.6 的对抗式审查方法、退款状态机约束、发布证据分层和后续维护流程，见 `docs/DEVELOPMENT-RETROSPECTIVE-1.3.3-1.3.6.zh-CN.md`。

## 1.3.5 WordPress.org 发布包清理

为符合 WordPress.org 的发布文件要求，安装包不再包含维护文档、AI 过程说明或支付宝 EasySDK 的 Tea 源规格文件。这些文件继续保留在源码仓库，既不参与插件运行，也不影响 Composer 自动加载。

目录审核通过后，1.3.5 已发布到 WordPress.org 的 `trunk/` 和 `tags/1.3.5/`，SVN 修订为 `r3623533`。完整审核整改、发布证据和后续发布流程见 `docs/WORDPRESS-ORG-RELEASE-RETROSPECTIVE-1.3.5.zh-CN.md`。

## 1.3.6 安全依赖更新候选

1.3.5 发布后，`composer audit` 新增了针对 Guzzle 7.15.1 之前版本的 4 条中危安全公告。开发分支将 Guzzle 从 7.13.2 升级到 7.15.1，并同步升级 `guzzlehttp/promises` 到 2.5.1、`guzzlehttp/psr7` 到 2.13.0。

由于 WordPress.org 1.3.5 已是不可变的公开发布事实，修复后的依赖包使用 1.3.6 候选版本，不复用 1.3.5 版本号。真实商户验证、GitHub 合并/tag/Release 和 WordPress.org 1.3.6 发布仍是后续独立门禁。

## 1.3.7 退款边界与发布候选

1.3.7 在 1.3.6 的依赖更新基础上，移除订单号静默改写，补充异常退款状态队列、退款操作人隔离、独立退款 capability、`wp-config.php` 密钥注入和更严格的 WordPress 时区校验。微信退款在 `CLOSED/ABNORMAL` 终态后推进新的商户退款单号，避免后续尝试复用已关闭退款单。

当前精确候选包由 `composer build:zip` 生成，真实 WordPress、Plugin Check 和支付宝/微信商户联调仍是独立发布门禁，不能用本地测试替代。

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

- 当前 `origin`：`https://github.com/npcink/npcink-pay-refund.git`
- Gitee remote 不再使用
- GitHub 仓库为 public
- `main` 已推送到 GitHub
- GitHub 最新稳定 tag 和 Release 仍为 `v1.3.3`
- 1.3.4 退款安全改造、1.3.5 发布包清理与 1.3.6 安全依赖更新当前位于草稿 PR #2，尚未合入 `main`
- WordPress.org 已发布 1.3.5；它与 GitHub `main` / Release 的版本事实必须分别记录，不能相互代替

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
