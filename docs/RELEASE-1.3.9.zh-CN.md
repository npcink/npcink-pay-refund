# 1.3.9 正式发布记录

日期：2026-11-06。

- GitHub Release：[v1.3.9](https://github.com/npcink/npcink-pay-refund/releases/tag/v1.3.9)。
- WordPress.org：[目录页](https://wordpress.org/plugins/npcink-pay-refund/)、[下载](https://downloads.wordpress.org/plugin/npcink-pay-refund.1.3.9.zip)。目录 API 已返回版本 1.3.9。
- 发布 PR：[#5](https://github.com/npcink/npcink-pay-refund/pull/5)，已合并。
- Git tag：`v1.3.9`，发布提交：`5dc2fa70a49682c8087c239269723657721c178a`；其源码树与已验证提交 `d1df8f1f37bca45297db9764806232a31e336dba` 完全一致。
- WordPress.org SVN：`r3787066`，`trunk/` 和 `tags/1.3.9/` 各325个文件均与商户验收包逐文件 SHA-256 匹配。
- GitHub 发布 ZIP SHA-256：`c342eba300118723b509912fe59d72bf522668708693aebaee903fd24ff422d0`。发布后已重新下载 GitHub ZIP、SHA 文件和原始 manifest，三者与本地原件字节一致。

## 验证范围

使用者在同一对话中先确认“1.3.9，只验证了微信”，随后确认“支付宝已通过，发布吧”。两渠道均按用户确认记为真实商户验收通过；维护工具没有独立访问商户平台，未提供订单号、金额或完成时间，不补造这些数据。具体确认记录附于 Release 的 merchant-validation.json。

两组提交 CI、PR 合并检查及 [main CI](https://github.com/npcink/npcink-pay-refund/actions/runs/42158214183) 均通过；最低 PHP 8.1 / WordPress 6.0 及 PHP 8.4 / 最新 WordPress 组合均通过31回归、5/5权限变异和53集成断言。另完成38项浏览器检查、Local magick-ai覆盖升级、19项安装目录烟测、12项数据库检查和10项HTTPS/AJAX检查。Local覆盖升级前后已有配置及记录指纹保持一致。

## 不可变制品与历史状态

本次直接发布原验收 ZIP，未重新构建。其包内 changelog 中“Unpublished candidate”以及原始 manifest 的 merchant pending、构建时未提交状态是历史构建记录；它们由 Release 说明、committed-candidate-identity.json 和 merchant-validation.json 记录的后续事实补充，原 ZIP 和原始 manifest 不改写。

WordPress.org 自行生成下载 ZIP，因此压缩包字节哈希可以不同；等价性按解压后的325个文件哈希核对。后续仅修订工程发布文档，不替换已发布制品或移动版本标签。

本地详细证据保存在忽略目录 build/validation/1.3.9/，公开 Release 附有原 ZIP、校验值、清单、商户确认和提交映射。升级使用覆盖安装，避免先删除插件触发卸载清理。
