# 1.3.10 正式发布记录

2026-11-27（Asia/Shanghai），按使用者确认的顺序发布原商户验收包。未重新构建，未把 `433ad1b` 或 1.3.11 的后续改动加入该版本，也未操作商户站点。

## 制品与回读

- [GitHub `v1.3.10`](https://github.com/npcink/npcink-pay-refund/releases/tag/v1.3.10) 为 Latest 正式版，标签指向 `e5a806f82f441dc2eff51ee2be3ce92076e6f118`。原 manifest 的 68 个源码文件均与该提交匹配；其 [GitHub CI](https://github.com/npcink/npcink-pay-refund/actions/runs/45768288297) 两组通过。
- GitHub 发布附件为 `npcink-pay-refund-1.3.10.zip`、`.zip.sha256` 和 `.zip.manifest.json`；下载回读与本地原件逐字节一致。原 ZIP SHA-256：`2119d87367d2fda5d31fa06398d2e0afa9b3aa0e5c494d76fcc38a86593f651e`。
- WordPress.org SVN `r3828629`：从远端导出 `trunk/` 和 `tags/1.3.10/`，两者各 326 个文件逐一匹配原包 manifest。顶层 `assets/` 使用与发布提交一致的公开目录素材，不进入插件安装包。
- [WordPress.org 插件目录](https://wordpress.org/plugins/npcink-pay-refund/) API 已返回 `1.3.10` 及对应下载。目录 ZIP 为重新压缩的制品，其 SHA-256 为 `74f97793b9ca19cd374d95fe0ba7901f3777870b2288b28f0943060059e4ebe6`；解压内容与原验收包一致。

## 验收与后续

- 支付宝、微信由使用者确认原包通过，见[商户验收补记](CLOSEOUT-AND-RETROSPECTIVE-1.3.10.zh-CN.md#商户验收补记)。原 manifest 的构建提交基线、未提交状态和 `merchant_validation: pending` 是构建时记录，保留不改写；商户确认只对应上述原包。发布过程没有重新调用支付网关。
- [1.3.11](RELEASE-1.3.11.zh-CN.md) 仍是单独的 GitHub 预发布，不是 Latest，也未上架 WordPress.org。其两渠道商户验收和桌面 Excel 抽验仍待完成。
- 升级请先备份并覆盖安装，不要先删除插件，避免触发站点已保存的卸载清理选择。
