# Npcink Pay Refund 开发与发布规范

本文是本项目后续开发、审查和发布的统一依据。它总结 1.0 到 1.3.7 的改造经验，重点记录边界、不变量和证据要求，而不是重复代码注释。

## 1. 产品边界

Npcink Pay Refund 是 WordPress 后台的退款操作工具：查询已有支付宝/微信支付订单，执行全额退款，并记录本地审计信息。

它不是 checkout gateway，不创建支付，不处理支付通知，不修改第三方订单系统状态，也不负责自动退款编排。任何超出后台退款和退款记录范围的需求，都必须先单独评估边界，不能直接塞进本插件。

## 2. 核心不变量

1. 提供方结果不明时，不能释放重复退款保护，也不能更换请求号后盲目重试。
2. 退款成功必须经过官方 SDK 或签名验证路径，并核对订单号、退款请求号和金额。
3. 本地审计写入失败不能推导为提供方退款失败；补记本地记录不得再次调用支付接口。
4. 退款专员只获得 `npcink_refund_orders` capability；管理员可以管理配置。插件不拦截其他 WordPress 后台页面。
5. 密钥不能写入日志、发布包、源码或普通前台请求；`wp-config.php` 常量优先于数据库值，且不能被设置页回写。
6. 退款记录按操作者隔离：管理员查看全部，非管理员只能查看自己的记录。

## 3. 退款状态模型

### 支付宝

- 提交前生成稳定 `out_request_no`，并保存最小请求上下文。
- 成功响应必须由 EasySDK 验签，并核对订单、请求号和金额。
- 网络异常、超时、签名失败、字段不完整或标识不匹配均视为结果不明。
- 结果不明时先查询原请求号；只有提供方明确返回未找到，才允许用原请求号和原参数重试。

### 微信支付

- 提交前保存 pending、原金额、原因、操作者和 `out_refund_no`。
- `PROCESSING` 只表示处理中，继续保留锁并查询状态。
- 只有新鲜且验签成功的 `404 RESOURCE_NOT_EXISTS` 才允许按原参数重试。
- `MCH_NOT_EXISTS`、鉴权失败、签名失败、超时、`SYSTEM_ERROR` 和未知状态都必须保留 pending/锁，转入人工对账。
- `CLOSED/ABNORMAL` 表示当前退款单终态；通过同一 option 的 CAS 原子写入终态凭据与下一次尝试号（活动 pending 不再单独删除），后续请求不能复用旧退款单号。

## 4. WordPress 代码约定

- 所有 AJAX 入口同时检查 nonce 和 capability；配置检测和设置保存继续限制为 `manage_options`。
- 输入使用 `wp_unslash()` 后按字段类型清理，输出统一晚转义。
- SQL 使用 `$wpdb->prepare()`；schema 通过激活函数中的 `dbDelta()` 升级。
- 新增 option 生命周期时同时考虑 `add_option_*` 与 `update_option_*`，不能假设设置一定已有旧值。
- 新增 capability 必须在激活、迁移和卸载路径分别处理；卸载要清理角色级和用户级 capability。
- 不在插件加载阶段主动加载支付 SDK；只有通过权限检查并即将调用支付接口时才加载 vendor。
- 新字段必须兼容现有数据，避免 MySQL 8 严格模式不接受的零日期默认值。

## 5. 测试与证据分层

每次变更至少执行：

```bash
composer test
composer validate --strict
composer audit
composer verify:local
# Full gate: explicit isolated WP_PATH, .npcink-refund-test-site, installed Plugin Check
WP_PATH=/absolute/path/to/isolated-wordpress composer verify
```

证据含义必须分开记录：

- 单元/回归测试：证明状态机和纯逻辑；微信传输测试只替换 HTTP handler，运行真实 SDK 签名和验签，不能替代商户联调。
- PHP 8.1/8.4 CI：证明解释器兼容性。
- 真实 WordPress + MySQL：证明激活、升级、权限、后台烟测和卸载。
- Plugin Check：证明当前目录规则没有报告问题。
- 精确 ZIP 清单和 SHA-256：清单记录每个包内文件的 SHA-256，以及 Git 跟踪/未跟踪且未忽略源码的内容哈希；`source_tree_sha256` 可识别未提交新文件的内容变化。`tests/build-manifest.py` 独立读取 ZIP 对照完整清单。
- 真实商户联调：只证明指定凭据、订单和制品下的真实支付行为。

烟测必须使用带 `.npcink-refund-test-site` 标记的隔离站点；断言失败要抛异常，恢复配置和密钥后再返回失败；测试用户使用随机名称，不修改已有用户角色。

任何一层证据都不能替代另一层。尤其不能用本地测试或 Plugin Check 推导支付退款成功。

### 部署数据库预检与旧状态核对

```bash
# 可针对指定部署目标执行；没有网关调用，不修改插件 options/退款记录。
wp eval-file /absolute/path/to/npcink-pay-refund/bin/check-database-locks.php --path=/explicit/wordpress
```

已有站点可再运行 `wp eval-file /absolute/path/to/npcink-pay-refund/bin/check-installed-site.php --path=/explicit/wordpress`，检查实际已激活目录的页面、schema、分页和权限拒绝；该脚本不创建测试用户、不写配置、不发支付请求。它不替代隔离集成测试。

预检需要 `GET_LOCK` 和 `CREATE TEMPORARY TABLE` 权限；临时表/锁在 finally 中清理。它验证可写节点、独立连接排他、释放和临时写入后的同连接读取，共 12 项。若目标缺少临时表权限，需由数据库维护者在相同路由下执行验证，不能将失败解释为已经证明连接模型可用。读写拆分、连接代理、持久连接复用须额外核对路由策略；一次通过不保证后续连接不切换。

升级前由管理员遍历“待处理/待对账”队列的全部游标页，分别登记渠道、原请求号、金额、所有者和商户平台状态；缺失字段的旧状态不猜测补值、不删除锁或修改 attempt 来“解锁”。先查询原请求号，保存脱敏对账证据，确认远端结果后使用本地补记流程。数据库锁与远端退款不是一个事务：即使发送前持锁，发送后连接中断仍必须按原请求号恢复，不能换号重试。

## 6. 发布流程

1. 在独立分支检查 `git status --short --branch`，确认没有未授权改动。
2. 先补失败路径测试，再修改实现。
3. 同步插件头、运行时版本、`readme.txt`、README、POT 和 changelog。
4. 运行完整 `composer verify`，并在真实 WordPress 环境执行生命周期和 Plugin Check。
5. 使用 `composer build:zip` 生成唯一版本化 ZIP，回读文件清单并记录 SHA-256。
6. 由商户操作者在明确授权的测试环境按 `REFUND-INTEGRATION-CHECKLIST.md` 完成支付宝/微信联调，记录同一候选 ZIP 的结果；未完成时保留 pending，不签署发布结论。
7. 合并到 `main`，创建同版本 Git tag 和 GitHub Release，Release 附上已验证的精确 ZIP、校验值和清单；再次构建会产生新的候选身份，不能沿用旧包联调记录。
8. 将同一构建目录同步到 WordPress.org SVN `trunk/` 和 `tags/<version>/`，回读远端版本头和排除项。

版本号代表不可变制品。依赖安全修复、运行行为变化或发布包变化都必须使用新版本，不能静默替换已发布 ZIP。

## 7. 变更审查清单

- 是否改变了 checkout、支付通知或第三方订单状态边界？
- 结果不明时是否仍保持锁和原请求上下文？
- 是否可能重复调用退款接口？
- 响应是否经过验签并核对订单、请求号和金额？
- 本地写库失败是否与支付调用彻底分离？
- 新 option、字段、capability 是否覆盖首次保存、升级和卸载？
- 是否把密钥或完整网关响应写入日志、文档或发布包？
- 是否更新测试、版本元数据、POT、联调清单和发布说明？
- 是否针对最终 ZIP，而不是源码工作区，完成了检查？

## 8. 后续演进

如未来需要部分退款、自动修改业务订单状态、异步任务或多商户配置，应先写新的设计记录和数据迁移方案，再扩展当前插件。不要在现有退款状态机中隐式加入这些职责。
