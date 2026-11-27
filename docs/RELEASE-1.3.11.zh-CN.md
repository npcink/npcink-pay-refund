# 1.3.11 GitHub 预发布记录

日期：2026-11-27（Asia/Shanghai）。根据维护者授权，仅发布到 GitHub 项目，供验收使用。未部署到商户站点，未调用真实退款，也未写入 WordPress.org SVN。

## 制品与回读

- [GitHub `v1.3.11`](https://github.com/npcink/npcink-pay-refund/releases/tag/v1.3.11) 已发布，非草稿，标记为 Pre-release，不设为 Latest。回读 GitHub Latest 仍为 `v1.3.9`；WordPress.org 目录 API 和下载指向 `1.3.9`。
- [PR #7](https://github.com/npcink/npcink-pay-refund/pull/7) 已合并。标签指向合并提交 `b5c84d71e097daf83181336da9e1da1bb203542e`；其文件树与构建提交 `f5d8eea38a2ef9c448a7c3823564502e71e1c6fd` 一致，逐一核对了 manifest 中的源码文件哈希。
- 使用 Release 附件 `npcink-pay-refund-1.3.11.zip` 安装，不用 GitHub 自动生成的源码包。SHA-256：`b8a7ee79b541c669d55dafb62142b0a13437e552ed441b45d133301ecf321f44`。重新下载 ZIP、`.zip.sha256` 和 `.zip.manifest.json`，三者与冻结原件逐字节一致，并复核了 ZIP 的完整文件清单。
- 本地冻结制品和详细日志在 `build/validation/1.3.11-f5d8eea/`（Git 忽略）；原 manifest 的 `merchant_validation: pending` 不改写。原 1.3.10 ZIP 未替换，SHA-256 仍为 `2119d87367d2fda5d31fa06398d2e0afa9b3aa0e5c494d76fcc38a86593f651e`。

## 已验证与仍待验收

48 项本地回归、15/15 权限变异、CSV、隔离 WordPress/MySQL 集成、Plugin Check 和精确 ZIP 安装后四视口浏览器回归均已通过。[合并后 CI](https://github.com/npcink/npcink-pay-refund/actions/runs/45922643084) 的 PHP 8.1 / WordPress 6.0 和 PHP 8.4 / WordPress latest 两组通过。

| 事项 | 状态与后续 |
|---|---|
| L1、L7、L9 | 动态文案接入文本域、微信已记账但待处理状态清理失败的同号恢复、零值边界回归已纳入。 |
| H2 | 缺真实支付宝请求号冲突证据，先按[商户取证清单](REFUND-INTEGRATION-CHECKLIST.md#h2-alipay-request-number-conflict-evidence)核对，不改请求号策略。 |
| L3、L5 | 完成 50 万条合成记录基准和容量探针，未做生产压测、分页改造或表迁移。由运维先提供脱敏规模、延迟和自增水位。 |
| L8 | 仅验证同一 PHP 请求中 SDK Factory 保留首个配置，未证实实际插件共存冲突，未实施隔离。 |
| 限流 | 只有设计。采集授权站点的频次后再评估试点阈值，不阻断已提交退款的同号核对。 |
| 桌面 Excel、支付宝和微信商户验收 | 待完成。必须记录这个 ZIP 的 SHA-256；原 1.3.10 商户结果不能沿用。 |

详细操作和重新评估条件见 [维护候选与验收](MAINTENANCE-CANDIDATE-1.3.11.zh-CN.md) 和 [开发与发布规范](DEVELOPMENT-AND-RELEASE-STANDARDS.zh-CN.md)。GitHub 预发布只解决交付验收包，不代替验收，也不构成稳定发布或 WordPress.org 发布授权。
