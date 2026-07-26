# WordPress.org 1.3.5 审核与首次发布复盘

本文记录 `Npcink Pay Refund` 首次进入 WordPress.org Plugin Directory 的审核整改、发布包验证和 SVN 发布结果。它既是本次的可追溯记录，也是后续目录版本发布的操作边界。

## 发布结果

- 发布版本：`1.3.5`
- WordPress.org slug：`npcink-pay-refund`
- WordPress.org SVN 修订：`r3623533`
- 发布位置：`trunk/` 与 `tags/1.3.5/`
- 目录页：[Npcink Pay Refund](https://wordpress.org/plugins/npcink-pay-refund/)
- 源码提交：`f7a17aa Prepare 1.3.5 WordPress.org package`

发布后已从 SVN 远端回读确认：`trunk/readme.txt` 与 `tags/1.3.5/readme.txt` 都声明 `Contributors: muze233` 和 `Stable tag: 1.3.5`；`docs/`、`REFUND-INTEGRATION-CHECKLIST.md`、`Teafile`、`*.tea` 及 `vendor/alipaysdk/easysdk/tea/` 均未进入目录发布内容。

## 审核整改脉络

### 1. 账号归属与国际化

目录审核要求确认提交者身份，并检查不必要的国际化加载代码。最终约束是：

- `readme.txt` 的 `Contributors` 使用实际 WordPress.org 用户名 `muze233`。
- 账号绑定邮箱的变更应在 WordPress.org 账户侧完成，并由该账户回复审核邮件；源码不保存邮箱或审核邮件内容。
- WordPress.org 托管插件由平台加载翻译，因此移除了手动 `load_plugin_textdomain()`、相关 i18n 类、`set_locale()` 及其 hook。

这类问题不是运行时功能缺陷，却会阻断目录审核。今后应把“目录账号、readme 贡献者、插件 slug”作为同一份发布身份资料核对。

### 2. ZIP 内容比静态检查范围更宽

早期候选包能通过 Plugin Check（PCP），但仍包含不应随插件分发的文件：

- 维护过程文档 `docs/REFUND-INTEGRATION-CHECKLIST.md`；
- EasySDK Tea 源规格目录、`.tea` 文件和 `Teafile`。

因此 1.3.5 在 `.distignore` 中排除了 `docs/`、根 `README.md` 和 `vendor/alipaysdk/easysdk/tea/`，同时保留运行必需的 Composer `vendor/` 与 `vendor/autoload.php`。源码仓库可以保存工程文档；目录安装包只应保存运行、许可和用户所需内容。

关键结论：**PCP 通过是必要信号，不是目录审核通过的替代品。** 每次候选包都必须按最终 ZIP 内容做人工审查。

### 3. 版本与不可变候选包

上传同一个 ZIP 会被 WordPress.org 拒绝为重复文件。每次重新提交应先完成代码与发布包整改，再同步更新以下版本事实：

- `npcink-pay-refund.php` 插件头和版本常量；
- `readme.txt` 的 `Stable tag` 与 changelog；
- ZIP 根目录、文件名及安装包内版本。

不要只重命名旧 ZIP 来规避重复上传；内容变化必须对应一个新的、可验证的版本号。

## 可复用的发布流程

### 源码候选阶段

1. 保持 Git 工作区可识别：先执行 `git status --short --branch`，不要覆盖已有变更。
2. 同步版本号与目录元数据，确认 `Contributors` 是实际目录用户名。
3. 构建唯一候选包：

   ```bash
   composer build:zip
   ```

4. 运行回归门禁：

   ```bash
   composer verify
   ```

   该入口覆盖 Composer 校验与审计、PHP/JavaScript 语法、ZIP 结构、后台烟测和 Plugin Check。它不能替代真实商户退款验证，也不能替代目录包内容审阅。

5. 审阅 ZIP，而非仅审阅源码：确认包含 `npcink-pay-refund.php`、`vendor/autoload.php`，并检查没有开发目录、过程文档或依赖源规格文件。例如：

   ```bash
   unzip -Z1 build/npcink-pay-refund-<version>.zip
   ```

6. 将源码、`.distignore` 与发布说明作为明确文件列表提交并推送；`build/` 仍是可再生制品，不应作为 Git 发布事实。

### WordPress.org SVN 发布阶段

审核通过后，目录发布不是再次上传审核 ZIP，而是提交 WordPress.org 分配的 SVN 仓库：

1. checkout `https://plugins.svn.wordpress.org/npcink-pay-refund/`。
2. 将已审查的 ZIP 解压内容同步到 `trunk/`。
3. 从相同工作副本创建 `tags/<version>/`，确保标签是该 trunk 内容的副本。
4. 一次提交 trunk 和 tag，并使用版本化提交说明。
5. 以远端为准回读 `svn log`、`svn ls tags/`、trunk/tag 的 `readme.txt`；只有得到远端修订号才可称为已发布。
6. 最后访问目录页。目录解析、下载缓存和搜索索引可能稍后刷新，不能把网页缓存延迟误判为 SVN 提交失败。

## 这次发布的操作教训

- **提交命令返回不等于远端成功。** 首发包含较多依赖文件时，SVN 客户端可能在网络响应阶段卡住；不要据此重复提交或立即宣布成功。
- **先检查远端再重试。** 本次第一次提交的客户端未收到清晰结果，但服务端最终创建了 `r3623533`。重试提示文件已存在后，应查询 `svn log` 和远端标签，而不是覆盖或删除远端文件。
- **本地 SVN 锁是客户端状态。** 遇到工作副本锁时可执行 `svn cleanup`；在确认没有仍在运行的 `svn commit` 前，不要清理或启动第二个提交。
- **发布事实有层次。** Git 推送、候选 ZIP、PCP、SVN 修订、公开目录页面和真实支付商户验证分别证明不同事实，不能相互替代。

## 后续版本的最低发布证据

| 事实 | 最小证据 |
| --- | --- |
| 源码可追溯 | 干净或已明确范围的 Git 状态、提交 SHA、已推送分支 |
| 安装包可用 | `composer verify` 成功，ZIP 结构和版本已回读 |
| 目录包合规 | PCP 结果加 ZIP 文件清单人工审阅 |
| WordPress.org 已发布 | `svn log` 的远端修订号、`tags/<version>/`、trunk/tag 元数据一致 |
| 真实退款可用 | 商户操作者按 `REFUND-INTEGRATION-CHECKLIST.md` 完成低风险实测并保存独立记录 |

真实支付宝、微信退款仍是支付安全门禁，不因本次目录审核和发布而自动完成。
