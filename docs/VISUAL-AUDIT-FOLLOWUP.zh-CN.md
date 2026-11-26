# 退款后台视觉审计整改

日期：2026-11-26。范围：1.3.10 原候选之后的后台界面维护。支付宝交易关闭、支付时间与卸载默认值的整改见 [1.3.10 收尾记录](CLOSEOUT-AND-RETROSPECTIVE-1.3.10.zh-CN.md)，本次不改这些规则。

- **查询不串单。** 微信和支付宝独立管理查询状态。改单号立即移除旧结果和退款入口；查询期间防连点，过期的查询响应与微信轮询不再改写结果区。退款提交期间锁定该渠道的查询，另一渠道不受影响；前端丢弃旧响应不等于取消服务器请求、退款或补记。
- **窄屏能核对。** 待核对与操作记录使用同一份有表头的表格数据。782px 及以下每行折成带标签的双列布局，长订单号、请求号和原因可换行。分页和权限范围不变。
- **结果可区分。** 两渠道使用紧凑的结果卡与原生错误通知，保留交易关闭、退款成功、本地待补记的区别。仅显示层把金额格式统一为两位小数和「元」；数据库、网关字段与 CSV 不变。README 修正旧截图标题，未替换上架图片。

## 浏览器复测

使用专门的本地 WordPress/MySQL 测试站，以 `127.0.0.1` 提供访问，启用插件但不配置任何商户密钥。测试站标记为 `.npcink-refund-test-site` 后，创建至少 22 条退款记录，订单号及原因至少有一条较长，并创建一条微信「本站记录待补记」。可从仓库根目录运行以下命令给空白测试站添加这些合成数据（示例管理员 ID 为 `1`）：

```sh
wp --path="$WP_PATH" eval '
if (!is_file(ABSPATH . ".npcink-refund-test-site")) { throw new RuntimeException("Requires isolated site"); }
require_once WP_PLUGIN_DIR . "/npcink-pay-refund/admin/class-npcink-pay-refund-admin.php";
require_once WP_PLUGIN_DIR . "/npcink-pay-refund/admin/pay/npcink-pay-refund-admin-public.php";
wp_set_current_user(1);
for ($i = 1; $i <= 22; ++$i) {
    Npcink_Pay_Refund_Admin_Public::add_data(wp_date("Y-m-d H:i:s"), "Fixture operator", $i + 0.05,
        "fixture-order-$i-" . str_repeat("X", 36), str_repeat("长文本核对", 12), $i % 2 ? "支付宝" : "微信", 1);
}
Npcink_Pay_Refund_Admin_Public::save_refund_reconciliation(array(
    "n_order" => "fixture-pending-" . str_repeat("W", 39), "n_type" => "微信",
    "n_amount" => 7.05, "n_user_id" => 1, "request_id" => "fixture-request-" . str_repeat("R", 35),
));'

REFUND_TEST_URL=http://127.0.0.1:8080 REFUND_TEST_LOGIN="$FIXTURE_LOGIN" \
  REFUND_TEST_PASSWORD="$FIXTURE_PASSWORD" node tests/query-browser.cjs
```

脚本使用 Chrome 和 Playwright，可用 `PLAYWRIGHT_MODULE` 指定本机的 Playwright 模块路径；设置 `REFUND_SCREENSHOTS` 可输出桌面和窄屏的样例行。记录筛选、分页和待核对列表走本地数据库，所有支付 AJAX 请求由浏览器隔离并模拟。该验证不替代手机实机、读屏器或真实商户验收。

本轮在 WordPress 7.0.4 / MySQL 8.4 的隔离站点通过后台烟测、数据库集成和 390 / 782 / 960 / 1360px 的 Chrome 回归。`composer test` 47 项、权限变异 15/15 和 CSV 下载测试通过。

**发布边界：** 原 1.3.10 ZIP 和验收记录不变。本次源码如要纳入发布，须用独立候选重新验收，不能沿用原包的商户验收。
