// 定义 trimHyphen() 函数
//订单处理算法
//将格式转换D606224553221068-0转为D606224553221068
function trimHyphen(input) {
  // 判断输入值的末尾是否是 "-1" 或 "-2" 的形式，如果是，则直接返回原始输入值
  if (input.endsWith("-1") || input.endsWith("-2") || input.endsWith("-3")) {
    return input;
  }
  return input.replace(/-\d+$/, "");
}

jQuery(document).ready(function ($) {
  var refundQuery = window.mareRefundQuery || {};

  function showRefundNotice($container, message, type) {
    var noticeType = type || "error";
    $container.empty().append(
      $("<div>", {
        class: "notice notice-" + noticeType + " inline mare-refund-notice",
      }).append($("<p>", { text: message }))
    );
  }

  function getRefundErrorMessage(xhr, fallback) {
    if (
      xhr &&
      xhr.responseJSON &&
      xhr.responseJSON.data &&
      xhr.responseJSON.data.message
    ) {
      return xhr.responseJSON.data.message;
    }

    return fallback;
  }

  function renderRefundResponse($container, response, fallback) {
    if (response && response.success && response.data && response.data.html) {
      $container.html(response.data.html);
      return;
    }

    showRefundNotice(
      $container,
      response && response.data && response.data.message ? response.data.message : fallback
    );
  }

  //支付宝查询

  $("#my-plugin-button").click(function () {
    var data = {
      action: "zfb_order_query",
      nonce: refundQuery.nonce,
      param: trimHyphen($("#my-plugin-input").val()),
    };

    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,

      success: function (response) {
        renderRefundResponse($("#my-plugin-data"), response, "支付宝订单查询失败，请稍后重试。");
      },
      error: function (xhr) {
        showRefundNotice(
          $("#my-plugin-data"),
          getRefundErrorMessage(xhr, "支付宝订单查询失败，请稍后重试。")
        );
      },
    });
  });

  //支付宝退款
  $(document).on("click", "#order-btn", function () {
    var $button = $(this);
    const data = {
      action: "zfb_order_refund",
      nonce: refundQuery.nonce,
      order_id: $button.data("order-id"), //订单号
      order_time: $button.data("order-time"), // 获取订单时间
      order_amount: $button.data("order-amount"), // 获取订单总金额
      order_reason: $("#npcink-zfb-reason").val(), // 获取订单退款原因
    };

    //退款原因为空则进行提示
    if ($("#npcink-zfb-reason").val() === "") {
      showRefundNotice($("#my-plugin-data"), "请输入退款原因。");
      return false;
    }

    $button.prop("disabled", true).text("处理中...");
    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,
      success: function (response) {
        renderRefundResponse($("#my-plugin-data"), response, "支付宝退款失败，请稍后重试。");
      },
      error: function (xhr) {
        showRefundNotice(
          $("#my-plugin-data"),
          getRefundErrorMessage(xhr, "支付宝退款请求失败，请稍后重试或联系管理员检查配置。")
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("支付宝全额退款");
      },
    });
  });

  //微信支付查询
  $("#npcink-wx-button").click(function () {
    var data = {
      action: "wx_order_query",
      nonce: refundQuery.nonce,
      order_id: trimHyphen($("#npcink-wx-input").val()),
    };

    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,
      success: function (response) {
        renderRefundResponse($("#npcink-wx-data"), response, "微信订单查询失败，请稍后重试。");
      },
      error: function (xhr) {
        showRefundNotice(
          $("#npcink-wx-data"),
          getRefundErrorMessage(xhr, "微信订单查询失败，请检查微信鉴权信息或稍后重试。")
        );
      },
    });
  });

  //微信退款
  $(document).on("click", "#wx-order-btn", function () {
    var $button = $(this);

    const data = {
      action: "wx_order_refund",
      nonce: refundQuery.nonce,
      order_id: $button.data("order-id"), //订单号
      order_amount: $button.data("order-amount"), // 获取订单总金额
      order_reason: $("#npcink-wx-reason").val(), //获取退款原因
    };

    //退款原因为空则进行提示
    if ($("#npcink-wx-reason").val() === "") {
      showRefundNotice($("#npcink-wx-data"), "请输入退款原因。");
      return false;
    }

    $button.prop("disabled", true).text("处理中...");
    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,
      success: function (response) {
        renderRefundResponse($("#npcink-wx-data"), response, "微信退款失败，请稍后重试。");
      },
      error: function (xhr) {
        showRefundNotice(
          $("#npcink-wx-data"),
          getRefundErrorMessage(xhr, "微信退款请求失败，请稍后重试或联系管理员检查配置。")
        );
      },
      complete: function () {
        $button.prop("disabled", false).text("微信全额退款");
      },
    });
  });

  //数据展示
  var $dataTableBody = $("#dataTable tbody");
  var $recordKeyword = $("#mare-record-keyword");
  var $recordType = $("#mare-record-type");
  var $recordDateFrom = $("#mare-record-date-from");
  var $recordDateTo = $("#mare-record-date-to");
  var $recordSummary = $("#mare-record-summary");
  var dataArray = parseRefundRecords(refundQuery.data || "[]");

  function parseRefundRecords(rawData) {
    try {
      var records = JSON.parse(rawData);
      return Array.isArray(records) ? records : [];
    } catch (error) {
      return [];
    }
  }

  function normalizeRecordValue(value) {
    return value === null || value === undefined ? "" : String(value);
  }

  function getRecordDate(record) {
    var time = normalizeRecordValue(record.time);
    var match = time.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : "";
  }

  function getFilteredRecords() {
    var keyword = $.trim($recordKeyword.val()).toLowerCase();
    var type = $recordType.val();
    var dateFrom = $recordDateFrom.val();
    var dateTo = $recordDateTo.val();

    return dataArray.filter(function (record) {
      var recordType = normalizeRecordValue(record.type);
      var recordDate = getRecordDate(record);
      var searchable = [
        record.id,
        record.amount,
        record.time,
        record.order,
        record.user,
        record.type,
        record.reason,
      ]
        .map(normalizeRecordValue)
        .join(" ")
        .toLowerCase();

      if (keyword && searchable.indexOf(keyword) === -1) {
        return false;
      }

      if (type && recordType !== type) {
        return false;
      }

      if (dateFrom && (!recordDate || recordDate < dateFrom)) {
        return false;
      }

      if (dateTo && (!recordDate || recordDate > dateTo)) {
        return false;
      }

      return true;
    });
  }

  function renderRecordType(type) {
    var value = normalizeRecordValue(type);
    var className = value === "微信" ? "mare-status-wx" : value === "支付宝" ? "mare-status-zfb" : "mare-status-neutral";
    return $("<span>", {
      class: "mare-status-badge " + className,
      text: value || "-",
    });
  }

  function renderRecords() {
    var records = getFilteredRecords();
    $dataTableBody.empty();

    if (!records.length) {
      $("<tr>")
        .append(
          $("<td>", {
            colspan: 7,
          }).append(
            $("<p>", {
              class: "description mare-record-empty",
              text: dataArray.length ? "没有符合筛选条件的退款记录。" : "暂无退款记录。",
            })
          )
        )
        .appendTo($dataTableBody);
    } else {
      records.forEach(function (record) {
        $("<tr>")
          .append($("<td>", { text: normalizeRecordValue(record.id) }))
          .append($("<td>", { text: normalizeRecordValue(record.amount) }))
          .append($("<td>", { text: normalizeRecordValue(record.time) }))
          .append($("<td>", { text: normalizeRecordValue(record.order) }))
          .append($("<td>", { text: normalizeRecordValue(record.user) }))
          .append($("<td>").append(renderRecordType(record.type)))
          .append($("<td>", { text: normalizeRecordValue(record.reason) }))
          .appendTo($dataTableBody);
      });
    }

    $recordSummary.text("显示 " + records.length + " 条，共 " + dataArray.length + " 条。");
  }

  $recordKeyword.on("input", renderRecords);
  $recordType.add($recordDateFrom).add($recordDateTo).on("change", renderRecords);
  $("#mare-record-reset").on("click", function () {
    $recordKeyword.val("");
    $recordType.val("");
    $recordDateFrom.val("");
    $recordDateTo.val("");
    renderRecords();
  });

  renderRecords();
});
