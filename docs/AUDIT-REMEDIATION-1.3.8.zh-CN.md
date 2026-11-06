# 1.3.8 候选版：22 项审查问题整改记录

> 本文保留 1.3.8 整改阶段的历史证据和当时待验证状态。后续整改已纳入 1.3.9，使用者确认两渠道真实退款通过，且已正式发布。当前关闭范围与仍未验证的特殊场景见 [收尾与复盘](CLOSEOUT-AND-RETROSPECTIVE-1.3.9.zh-CN.md)，发布事实见 [1.3.9 发布记录](RELEASE-1.3.9.zh-CN.md)；下文的“候选未发布”不代表当前状态。

日期：2026-11-06。分支：`codex/refund-audit-remediation`。整改在审查分支推进；后续已按用户指定在 Local magick-ai 安装候选包，详见 [目标站验证记录](LOCAL-TARGET-VALIDATION-1.3.8.zh-CN.md)。远端执行证据见 `build/validation/remote-ci.json`。候选包未发布，未执行真实退款。

本表中的“已修复”表示给定反例下的代码行为已经修复，不能推导为真实商户已验证。07、11、20 的商户协议/字段行为以及 22 的历史缺失材料仍存在证据边界。

| 编号 | 原严重程度 | 原问题 | 当前处理 | 文档 / 实现 / 验证 |
|---|---|---|---|---|
| 01 | 高 | 常量密钥与支付配置不一致 | 已修复。统一配置读取，设置保存不回写常量。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:59) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/class-npcink-pay-refund-admin.php:106) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:174) |
| 02 | 高 | 微信旧响应及部分终态转换破坏上下文 | 已修复。请求号以持久化上下文为准；CAS 同项保存终态和下一尝试号；完成后保留成功请求号。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:75) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:1045) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:103) |
| 03 | 高 | 跨操作者待处理数据泄露 | 已修复。批量读取后验证操作者；提醒同样按可见分页计数，猜订单号也不能修改他人状态。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:73) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-public.php:150) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:89) |
| 04 | 高 | 完整支付宝 PEM 无法签名 | 已修复。校验 RSA 类型和位数，规范化完整 PEM/Base64，再交给真实 EasySDK 包装。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:69) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-zfb.php:72) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:49) |
| 05 | 高 | 微信成功响应缺少金额核验 | 已修复。清理状态前精确核对订单、退款请求号、总额、全额退款金额、付款人金额范围和 CNY。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:70) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:688) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:84) |
| 06 | 高 | 过期锁竞争和无所有权释放 | 已修复。数据库连接互斥覆盖 AJAX 生命周期；令牌 CAS 接管/释放，两渠道发送前重新验证持锁，失锁保留上下文。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:75) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-public.php:191) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:114) · [跨进程/失锁测试](/Users/muze/gitee/npcink-pay-refund/tests/lock-integration.php:3) |
| 07 | 高 | 支付宝无时区时间随站点误判 | 已修复。支付宝按 Asia/Shanghai 解释，窗口比较真实时间戳；此源时区契约仍需在指定商户验证。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:68) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-public.php:466) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:38) |
| 08 | 高 | 测试假绿与 CI 跳过 WordPress | 已修复。加载真实 Admin/Authority；5 个入口负向测试；逐个删权限检查的变异测试；CI 安装隔离 WP/MySQL。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md:62) · [代码](/Users/muze/gitee/npcink-pay-refund/bin/verify.sh:15) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:10) |
| 09 | 高 | 烟测失败不恢复配置且复用用户 | 已修复。改抛异常，finally 恢复配置并清理随机临时用户；烟测必须有隔离站点标记。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md:69) · [代码](/Users/muze/gitee/npcink-pay-refund/bin/smoke-admin.php:17) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:190) |
| 10 | 中 | 支付宝诊断类命名空间错误 | 已修复。导入真实 ResponseChecker；有效 RSA 配置必须本地诊断成功。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:69) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-zfb.php:10) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:62) |
| 11 | 中 | 付款人退款为零无法补记 | 已修复。零值与缺字段分开：商户全额金额匹配时按 payer_refund=0 记账；真实补贴订单仍待联调。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:70) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:1006) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:95) |
| 12 | 中 | 将支付时间当退款时间及重复时区偏移 | 已修复。采用提供方退款成功时间或提交时间；去除 current_time(timestamp) 的重复偏移，旧记录不猜测改写。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:72) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-zfb.php:104) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:155) |
| 13 | 中 | 全局 LIMIT 后权限过滤、浏览器局部搜索 | 已修复。在 SQL 中先加操作者/筛选条件，再分页；返回真实匹配总数，前端请求服务端。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:74) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/partials/npcink-pay-refund-admin-query.php:238) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:63) |
| 14 | 中 | 队列类别饥饿和 N+1 | 已修复。三类状态在一个按 option_id 排序的游标查询中批量取值；UI 支持下一批，每次最多处理 200 个候选。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:74) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-public.php:390) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:99) |
| 15 | 中 | 网关异常正文进入日志 | 已修复。日志只保留异常类、HTTP 状态、受限代码及请求 ID，不写 exception message/body。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md:17) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:110) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:136) |
| 16 | 中 | 空/宽松微信时间校验放行 | 已修复。严格 RFC3339 结构和日历解析；拒绝空值、相对时间、非法日期和非字符串。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:68) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:1156) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:31) |
| 17 | 中 | UNKNOWN 展示为已受理 | 已修复。未知卡片不再硬编码 PROCESSING，已确认 PROCESSING 仍显示处理中。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:71) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-wx.php:944) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:43) |
| 18 | 中 | DDL 失败仍推进 schema 版本 | 已修复。独立 schema 版本；实际列类型/非空约束/索引验证成功才更新标记，失败保留重试。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md:41) · [代码](/Users/muze/gitee/npcink-pay-refund/includes/class-npcink-pay-refund-activator.php:78) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:51) |
| 19 | 中 | 保留设置卸载后重装不恢复授权 | 已修复。移除权限时无条件重置能力迁移标记，重装按保留设置重新授予。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md:43) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/partials/class-npcink-pay-refund-uninstall.php:23) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/integration.php:174) |
| 20 | 中 | 忽略支付宝 refundStatus | 已修复。只有 REFUND_SUCCESS 与订单/请求号/金额全匹配才确认；未知/缺失状态保留不确定上下文。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:71) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-zfb.php:437) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/regressions.php:68) |
| 21 | 中 | 订单号含义不清 | 已修复。UI 明示商户 out_trade_no；文档区分 transaction_id / trade_no，保留完整输入。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:67) · [代码](/Users/muze/gitee/npcink-pay-refund/admin/partials/npcink-pay-refund-admin-query.php:82) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/run.php:86) |
| 22 | 中 | 商户验证结论不可追溯 | 机制已补齐；历史/商户证据待补。历史确认降为未附证据的历史陈述；构建输出 ZIP、包内逐文件和包含未跟踪文件的源码 SHA-256 清单；候选商户验证明确 pending。 | [文档](/Users/muze/gitee/npcink-pay-refund/docs/README.zh-CN.md:32) · [代码](/Users/muze/gitee/npcink-pay-refund/bin/build-manifest.php:39) · [测试](/Users/muze/gitee/npcink-pay-refund/tests/build-manifest.py:32) |

## 针对高风险缺陷的反例与执行方式

- 01：数据库密钥与四个 wp-config 常量不同，支付读取必须选常量；执行空白密钥保存后数据库仍是原数据库密钥。
- 02：A 处理旧 CLOSED 后创建下一次 pending，再让 B 处理旧 CLOSED，B 必须失败且新请求号保留。另验证“旧 attempt 已增加、pending 尚未删除”的历史部分写入能恢复；替换尝试成功清理 pending 后仍可查询正确请求号。
- 03：用户 A 的旧记录前插入用户 B 的 25 条新记录；A 仍看到自己的记录且计数为 1。用户 A 直接调用用户 B 待处理订单也被拒绝。
- 04：运行时生成真实 RSA 2048 密钥，将完整 PEM 交给配置规范化后，通过打包 EasySDK Signer 真签名和验签；无效内容必须抛错。
- 05：原请求 1000 分，成功响应改为 refund=1、total=1、USD、payer_refund=-1/1001 或字符串金额，均不得记账或清理 pending。
- 06：独立 PHP 进程持锁时竞争者被拒绝；正常退出及 SIGKILL 后新进程可取得锁；持久化后释放原连接锁，两渠道发送入口拒绝提交且原上下文不变；两个竞争者使用同一个过期值 CAS 时第二个失败；旧令牌释放不得删除新令牌。
- 07：UTC 站点用上海当前支付时间比较，应在退款窗口内；该反例确认转换缺陷，未证明特定商户的源时间。
- 08：在一次性目录逐个删掉五个支付权限入口，5/5 变异均被负向断言捕获。
- 09：烟测写入配置后注入异常，必须进入 finally，恢复原配置/密钥值并删除临时用户；独立烟测不接受未标记的站点。

```bash
composer test
python3 tests/mutation-permissions.py
composer verify:local
# 仅针对独立创建、可销毁的 WordPress 数据库和目录：
# touch /absolute/path/to/isolated-wordpress/.npcink-refund-test-site
WP_PATH=/absolute/path/to/isolated-wordpress composer verify
# 单独运行最低 WordPress 版本集成测试：
wp eval-file tests/integration.php --path=/absolute/path/to/isolated-wordpress
```

完整门禁不会默认连接开发者的已有站点；没有显式 WP_PATH 和隔离标记时返回非零。verify:local 明确不包含 WordPress/MySQL/Plugin Check 证据。

## 本轮实际证据

- PHP 8.1.34 与 PHP 8.4.21：各 31 个回归用例通过；生产 Admin、Config、Authority 类真实载入，WordPress 基础函数仅在离线层使用适配器。
- 独立 MySQL 8.4.0：PHP 8.4.21 / WordPress 7.1.4 与 PHP 8.1.34 / WordPress 6.0 各 53 项集成断言通过。覆盖真实 options、SQL CAS、跨进程排他/崩溃释放、失锁阻止发送、DDL/INSERT/CAS 故障恢复、授权、分页和卸载保留设置后的重装。
- 源码目录和精确构建目录的后台烟测通过；完整门禁中的精确构建目录 Plugin Check 无报告。
- composer validate --strict、composer audit、PHP/JavaScript 语法和安装包清单检查通过。
- 浏览器：WordPress 后台 26 条合成记录，第 1 页 20 条、第 2 页 6 条；搜索 browser-audit-0 回到第 1 页且只显示 1 条旧记录；控制台无错误/警告。未点击退款按钮。
- PHP 8.1.34 从 php.net 官方源码临时编译，下载包 SHA-256 与官方 JSON 元数据一致；未改系统默认 PHP。最低版本实测日志和源码校验元数据随验证材料保存。GitHub Actions 使用真实 MySQL 服务；后续远端执行结论以 `build/validation/remote-ci.json` 的提交号、运行 URL 和状态为准。
- 真实微信 SDK 离线传输测试：GET/POST 请求签名、响应验签、篡改成功响应拒绝、签名 404 允许同号恢复及篡改 404 拒绝。只替换 HTTP handler，使用合成密钥。
- 数据库预检 12 项通过，范围限本次独立 MySQL 的所观察连接；不证明实际部署代理/读写路由。构建身份测试确认修改未跟踪文件内容会改变源码树哈希，并独立核对 ZIP 的每个文件哈希。
- 后续已在用户指定 Local magick-ai（PHP 8.5.12）验证，见 [目标站记录](LOCAL-TARGET-VALIDATION-1.3.8.zh-CN.md)；真实商户联调由用户执行。
- 本地验证日志与机器摘要：`build/validation/verify.log`、`build/validation/wordpress-6.0.log`、`build/validation/summary.json`。
- 安装包及身份信息：`build/npcink-pay-refund-1.3.8.zip`、同名 `.zip.sha256`、`.zip.manifest.json`。以最终生成文件为准，不能沿用此前中间构建的校验值。

## 十二个维度的覆盖与剩余边界

| 维度 | 本轮检查/整改范围 | 结论及边界 |
|---|---|---|
| 1 文档与代码一致性 | 密钥优先级、金额、状态、时间、订单 ID、分页契约 | 已同步对应行为和文档；历史成功声明不再替代证据。 |
| 2 文档质量 | 前置条件、异常恢复、权限、隔离验证、商户证据模板 | 已补充；真实商户示例仍待操作者记录。 |
| 3 测试有效性 | 移除替身 Admin；执行真实入口；实际 SDK RSA；权限变异 | 31 个回归与 5 个变异验证，不声称覆盖全部路径。 |
| 4 测试盲区 | 空值/非法日期/金额类型、零金额、旧响应、DB 故障、互斥、回滚 | 关键反例及跨进程强制终止已自动化；真实网络断线和远端限流未模拟为商户证据。 |
| 5 逻辑与正确性 | 请求号跨尝试/成功后查询、付款人金额、时区和状态判定 | 已修复相关逻辑；旧历史审计时间不根据猜测改写。 |
| 6 健壮性与容错 | pending→terminal 原子状态、结果不明、补记失败、数据库重试 | 本地故障注入通过；灾难性数据库不可写时不能保证持久化。 |
| 7 安全 | 五个退款 AJAX 与新增分页接口权限、所有者、日志、SQL、密钥 | 已修复已定位问题；composer audit 当前无公告，不代表无未知漏洞。 |
| 8 性能与可扩展性 | 队列批量读取/游标、记录服务端分页、搜索关键词上限 | 消除前缀饥饿和 N+1；序列化 option 的所有者预筛选及模糊搜索仍可能扫描较多行，未做生产规模压测。 |
| 9 可维护性 | 配置单一读取、统一终态处理、独立 schema 版本、避免私改 vendor | 本轮变更已自审；未将此工作扩大为全库格式化或删除兼容接口。 |
| 10 工程与运维 | CI、独立站点门禁、烟测恢复、DDL 检验、精确包及 SHA | 本地完整门禁通过；安装目标须运行 12 项预检，且核对同连接 GET_LOCK 与代理/读写路由。 |
| 11 依赖与兼容性 | 锁定依赖审计、PHP 8.1.34/8.4.21 实测、WordPress 6.0/7.1.4；避免 WP 6.2 才支持的 %i | 本轮未引入新运行依赖；最低运行组合已在本地真实执行；远端运行结果另存执行证据。 |
| 12 需求与设计 | 只做后台查询/全额退款/审计，不改 checkout 或第三方订单状态 | 已写明金额、时区、请求 ID、owner、MySQL 锁等隐含契约。 |

## 发布前优先核对的 3 项

1. **指定候选包的真实退款和中断恢复**：按联调清单记录 SHA、脱敏请求号、金额/币种、退款状态及同 ID 重试，原因是本地反例无法证明商户签约和远端响应。
2. **部署数据库锁与旧 pending**：确认 GET_LOCK 与业务写操作落在同一个主库连接；缺少原请求金额/所有者的历史状态只由管理员对账，避免猜测迁移。新终态凭据不能随意删除以重置请求号。
3. **远端 CI 与制品签署**：PHP 8.1 最低组合已本地实测，托管 CI 执行结果须核对记录的提交号和运行 URL。联调记录必须绑定最终 ZIP、源码树和包内文件哈希；任何重建均重新核对身份，历史商户声明不能追补成新包证据。
