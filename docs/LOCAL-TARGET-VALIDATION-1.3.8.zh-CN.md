# 1.3.8：Local magick-ai 目标站验证

日期：2026-11-06。用户指定 Local 的 magick-ai 为测试目标；真实商户联调由用户执行。本记录不包含商户退款成功结论。

## 目标与制品

- Local 站点：`magick-ai.local`，单站点，环境类型 `local`。
- WordPress 7.1.4 / Local PHP 8.5.12 / MySQL 8.4.0。
- 候选 ZIP：`build/npcink-pay-refund-1.3.8.zip`。
- 安装时 ZIP SHA-256：`ffb83a3b9bf9e3ced39050afd7296dc4301b166276068939acc32b4239f53ee3`。
- 安装前退款插件不存在，插件配置、旧 pending/uncertain/reconciliation、退款审计表均不存在。无需迁移旧退款；不代表其他部署目标也没有旧状态。
- 操作前完成完整数据库备份，保存在本机私有备份目录，未加入 Git、制品或公开日志。

## 已执行

| 检查 | 实际结果 | 证据文件（build/validation/local-magick-ai/） |
|---|---|---|
| 数据库路由/连接锁预检 | 12 项通过；可写节点、独立连接排他、临时写入后同连接读取和释放 | database-preflight.json |
| 已安装目录烟测 | 19 项通过；现有管理员、页面渲染、schema/索引、记录总数、5 个支付入口 nonce/权限拒绝、配置不变 | installed-site-smoke.json |
| PHP 8.5 回归 | 31 用例通过 | php-8.5-tests.log |
| 实际 HTTPS 后台请求 | 配置页、退款页和其他插件页均 200 且包含目标内容；记录/队列 AJAX 返回空列表；5 个支付入口无效 nonce 均 403 | http-smoke.json |
| 制品核对 | 已安装目录的每个文件均与候选包清单一致，无额外文件 | summary.json |
| Plugin Check | 结果以本轮保存日志为准 | plugin-check.log |
| PHP 日志增量 | 只检查本轮偏移后的新增内容，不把历史 PHP 扩展警告归因于候选包 | php-new-errors.log |

HTTP 测试只为现有管理员创建一个短期测试会话，结束后已单独销毁，不读取登录密码；未创建测试用户、未写支付配置、未向网关发请求。后台正常初始化可能创建 schema/capability 迁移标记。没有在该已有站点运行会删改表结构、卸载插件的隔离集成测试，也未添加隔离站点标记。

## CI 与商户交接

远端 CI 在 `codex/refund-audit-remediation` 分支运行两组 PHP/WordPress 矩阵；具体执行结论、运行 URL、提交号和制品身份以 `build/validation/remote-ci.json` 为准，不能仅凭 workflow 文件宣称通过。CI 各自重建的 ZIP 具有各自哈希，不等同于此目标站已安装 ZIP 的字节身份。

用户可在本地后台继续填写商户配置，并按 `REFUND-INTEGRATION-CHECKLIST.md` 完成真实商户联调。候选包仍未发布；商户结果明确为 pending。若后续修改包内代码或重建制品，须重新记录 SHA-256 并核对安装目录。
