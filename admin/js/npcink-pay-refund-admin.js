jQuery(document).ready(function ($) {
  var refundQuery = window.npcinkPayRefundQuery || {};
  var wxRefundPollTimer = null;
  var channels = {};

  function showRefundNotice($container, message, type) {
    var noticeType = type || "error";
    $container.empty().append(
      $("<div>", {
        class: "notice notice-" + noticeType + " inline npcink-pay-refund-refund-notice",
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

  function stopWxRefundPolling() {
    if (wxRefundPollTimer) {
      clearTimeout(wxRefundPollTimer);
      wxRefundPollTimer = null;
    }
  }

  function appendWxPollingNotice($container, message, type) {
    $container.find(".npcink-pay-refund-wx-polling-notice").remove();
    $("<p>", {
      class: "description npcink-pay-refund-wx-polling-notice npcink-pay-refund-wx-polling-" + (type || "info"),
      text: message,
    }).appendTo($container);
  }

  function pollWxRefundStatus(orderId, attempt, generation) {
    var maxAttempts = 10;
    var nextAttempt = attempt || 1;
    var channel = channels.wx;
    var $container = channel.$result;

    if (!orderId || generation !== channel.generation) {
      return;
    }

    appendWxPollingNotice($container, "正在同步微信退款状态（" + nextAttempt + "/" + maxAttempts + "）...");
    wxRefundPollTimer = setTimeout(function () {
      wxRefundPollTimer = null;
      $.ajax({
        url: refundQuery.ajaxurl,
        type: "POST",
        dataType: "json",
        data: {
          action: "npcink_pay_refund_wx_refund_status",
          nonce: refundQuery.nonce,
          order_id: orderId,
        },
        success: function (response) {
          if (generation !== channel.generation) { return; }
          renderRefundResponse($container, response, "微信退款状态查询失败，请稍后手动查询。");

          if (!response || !response.success || !response.data) {
            return;
          }

          if (response.data.refund_status === "SUCCESS") {
            stopWxRefundPolling();
            return;
          }

          if (response.data.poll && nextAttempt < maxAttempts) {
            pollWxRefundStatus(orderId, nextAttempt + 1, generation);
            return;
          }

          if (response.data.poll) {
            appendWxPollingNotice($container, "微信仍在处理退款，请稍后再次点击查询刷新结果。", "pending");
          }
        },
        error: function () {
          if (generation === channel.generation) {
            appendWxPollingNotice($container, "微信退款状态暂时无法同步，请稍后手动查询。", "error");
          }
        },
        complete: function () { requestOperational(0); },
      });
    }, 1000);
  }

  function resetQueryView(channel) {
    // Ignore stale callbacks without cancelling server-side reconciliation.
    channel.generation += 1;
    if (channel.key === "wx") { stopWxRefundPolling(); }
    channel.querying = false;
    channel.$button.prop("disabled", false).text(channel.label);
    channel.$status.empty();
    channel.$result.empty();
  }

  function wireOrderQuery(key, param, fallback) {
    var prefix = "#npcink-pay-refund-" + key;
    var channel = channels[key] = {
      key: key,
      $input: $(prefix + "-input"),
      $button: $(prefix + "-button"),
      $status: $(prefix + "-status"),
      $result: $(prefix + "-data"),
      generation: 0,
      querying: false,
      refunding: false,
    };
    channel.label = channel.$button.text();

    channel.$input.on("input", function () {
      if (!channel.refunding) { resetQueryView(channel); }
    });

    $(prefix + "-query").on("submit", function (event) {
      event.preventDefault();
      if (channel.refunding || channel.querying) { return; }
      resetQueryView(channel);
      var generation = channel.generation;
      var order = $.trim(channel.$input.val());
      if (!order) {
        showRefundNotice(channel.$result, refundQuery.emptyOrder);
        channel.$input.trigger("focus");
        return;
      }

      var data = { action: "npcink_pay_refund_" + key + "_order_query", nonce: refundQuery.nonce };
      data[param] = order;
      channel.querying = true;
      channel.$button.prop("disabled", true).text(refundQuery.querying);
      channel.$status.text(refundQuery.querying);
      $.ajax({
        url: refundQuery.ajaxurl,
        type: "POST",
        dataType: "json",
        data: data,
        success: function (response) {
          if (generation === channel.generation) { renderRefundResponse(channel.$result, response, fallback); }
        },
        error: function (xhr) {
          if (generation === channel.generation) { showRefundNotice(channel.$result, getRefundErrorMessage(xhr, fallback)); }
        },
        complete: function () {
          if (generation === channel.generation) {
            channel.querying = false;
            channel.$button.prop("disabled", false).text(channel.label);
            channel.$status.empty();
          }
          requestOperational(0);
        },
      });
    });
  }

  wireOrderQuery("zfb", "param", "支付宝订单查询失败，请稍后重试。");
  wireOrderQuery("wx", "order_id", "微信订单查询失败，请检查微信鉴权信息或稍后重试。");

  function setRefunding(channel, refunding) {
    channel.refunding = refunding;
    channel.$input.add(channel.$button).prop("disabled", refunding);
    $operationalRecordsBody.find(".npcink-pay-refund-open-operational").filter(function () {
      return $(this).attr("data-channel") === (channel.key === "wx" ? "微信" : "支付宝");
    }).prop("disabled", refunding);
  }

  //支付宝退款
  $(document).on("click", "#order-btn", function () {
    var channel = channels.zfb;
    if (channel.querying || channel.refunding) { return; }
    var $button = $(this);
    var orderId = String($button.data("order-id") || "");
    var amount = String($button.data("order-amount") || "");
    var reason = $.trim($("#npcink-pay-refund-zfb-reason").val());
    const data = {
      action: "npcink_pay_refund_zfb_order_refund",
      nonce: refundQuery.nonce,
      order_id: orderId, //订单号
      order_amount: $button.data("order-amount"), // 获取订单总金额
      order_reason: reason, // 获取订单退款原因
    };

    //退款原因为空则进行提示
    if (reason === "") {
      showRefundNotice($("#npcink-pay-refund-zfb-data"), "请输入退款原因。");
      return false;
    }

    if (!window.confirm("确认向支付宝提交全额退款？\n订单号：" + orderId + "\n金额：" + displayAmount(amount) + "\n原因：" + reason)) {
      return false;
    }

    setRefunding(channel, true);
    $button.prop("disabled", true).text("处理中...");
    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,
      success: function (response) {
        renderRefundResponse($("#npcink-pay-refund-zfb-data"), response, "支付宝退款失败，请稍后重试。");
      },
      error: function (xhr) {
        showRefundNotice(
          $("#npcink-pay-refund-zfb-data"),
          getRefundErrorMessage(xhr, "支付宝退款请求失败，请稍后重试或联系管理员检查配置。")
        );
      },
      complete: function () {
        setRefunding(channel, false);
        $button.prop("disabled", false).text("支付宝全额退款");
        requestOperational(0);
        requestRecords(1);
      },
    });
  });

  //微信退款
  $(document).on("click", "#wx-order-btn", function () {
    var channel = channels.wx;
    if (channel.querying || channel.refunding) { return; }
    var $button = $(this);
    var orderId = String($button.data("order-id") || "");
    var amount = String($button.data("order-amount") || "");
    var reason = $.trim($("#npcink-pay-refund-wx-reason").val());

    const data = {
      action: "npcink_pay_refund_wx_order_refund",
      nonce: refundQuery.nonce,
      order_id: orderId, //订单号
      order_amount: $button.data("order-amount"), // 获取订单总金额
      order_reason: reason, //获取退款原因
    };

    //退款原因为空则进行提示
    if (reason === "") {
      showRefundNotice($("#npcink-pay-refund-wx-data"), "请输入退款原因。");
      return false;
    }

    if (!window.confirm("确认向微信支付提交全额退款？\n订单号：" + orderId + "\n金额：" + displayAmount(amount, 100) + "\n原因：" + reason)) {
      return false;
    }

    stopWxRefundPolling();
    channel.generation += 1;
    setRefunding(channel, true);
    $button.prop("disabled", true).text("处理中...");
    $.ajax({
      url: refundQuery.ajaxurl,
      type: "POST",
      dataType: "json",
      data: data,
      success: function (response) {
        renderRefundResponse($("#npcink-pay-refund-wx-data"), response, "微信退款失败，请稍后重试。");
        stopWxRefundPolling();
        if (response && response.success && response.data && response.data.poll) {
          pollWxRefundStatus(response.data.order_id || data.order_id, 1, channel.generation);
        }
      },
      error: function (xhr) {
        showRefundNotice(
          $("#npcink-pay-refund-wx-data"),
          getRefundErrorMessage(xhr, "微信退款请求失败，请稍后重试或联系管理员检查配置。")
        );
      },
      complete: function () {
        setRefunding(channel, false);
        $button.prop("disabled", false).text("微信全额退款");
        requestOperational(0);
        requestRecords(1);
      },
    });
  });

  //数据展示
  var $refundRecordsBody = $("#npcink-pay-refund-records tbody");
  var $operationalRecordsBody = $("#npcink-pay-refund-operational-records tbody");
  var recordLabels = readColumnLabels($refundRecordsBody);
  var operationalLabels = readColumnLabels($operationalRecordsBody);
  var $operationalPanel = $("#npcink-pay-refund-operational-state");
  var $operationalSummary = $("#npcink-pay-refund-operational-summary");
  var $recordKeyword = $("#npcink-pay-refund-record-keyword");
  var $recordType = $("#npcink-pay-refund-record-type");
  var $recordDateFrom = $("#npcink-pay-refund-record-date-from");
  var $recordDateTo = $("#npcink-pay-refund-record-date-to");
  var $recordSummary = $("#npcink-pay-refund-record-summary");
  var recordPage = refundQuery.records || { rows: [], total: 0, page: 1, pages: 0 };
  var dataArray = recordPage.rows || [];
  var operationalPage = refundQuery.operational || { rows: [], next_cursor: 0 };
  var operationalRows = operationalPage.rows || [];
  var operationalCursor = 0;
  var operationalRequest;
  var operationalSequence = 0;
  var recordRequest;
  var recordSequence = 0;
  var filterTimer;

  function requestRecords(page) {
    var sequence = ++recordSequence;
    if (recordRequest) { recordRequest.abort(); }
    recordRequest = $.ajax({
      url: refundQuery.ajaxurl, method: "POST", dataType: "json",
      data: { action: "npcink_pay_refund_records", nonce: refundQuery.nonce, page: page,
        keyword: $recordKeyword.val(), type: $recordType.val(), from: $recordDateFrom.val(), to: $recordDateTo.val() }
    }).done(function (response) {
      if (sequence !== recordSequence) { return; }
      if (!response.success) { $recordSummary.text("记录查询失败，请重试。"); return; }
      recordPage = response.data;
      dataArray = recordPage.rows || [];
      renderRecords();
    }).fail(function (xhr, status) {
      if (status !== "abort" && sequence === recordSequence) { $recordSummary.text("记录查询失败，请重试。"); }
    });
  }

  function showOperationalError() {
    $operationalPanel.prop("hidden", false).prop("open", true);
    $operationalSummary.text("退款状态未能刷新 · 请重试");
    $operationalRecordsBody.empty().append(messageRow(6, "待处理状态查询失败，不能确认是否仍有待处理项。请刷新首批重试。", true));
  }

  function requestOperational(cursor) {
    if (!$operationalPanel.length) { return; }
    var sequence = ++operationalSequence;
    if (operationalRequest) { operationalRequest.abort(); }
    $("#npcink-pay-refund-operational-next, #npcink-pay-refund-operational-reset").prop("disabled", true);
    operationalRequest = $.post(refundQuery.ajaxurl, { action: "npcink_pay_refund_operational", nonce: refundQuery.nonce, cursor: cursor })
      .done(function (response) {
        if (sequence !== operationalSequence) { return; }
        if (!response.success || !response.data) { showOperationalError(); return; }
        operationalCursor = cursor;
        operationalPage = response.data;
        operationalRows = operationalPage.rows || [];
        renderOperationalRows();
      }).fail(function (xhr, status) {
        if (status !== "abort" && sequence === operationalSequence) { showOperationalError(); }
      }).always(function () {
        if (sequence !== operationalSequence) { return; }
        $("#npcink-pay-refund-operational-reset").prop("disabled", false);
        $("#npcink-pay-refund-operational-next").prop("disabled", !operationalPage.next_cursor);
      });
  }
  $("#npcink-pay-refund-operational-next").on("click", function () { requestOperational(operationalPage.next_cursor); });
  $("#npcink-pay-refund-operational-reset").on("click", function () { requestOperational(0); });
  $("#npcink-pay-refund-record-prev").on("click", function () { requestRecords(recordPage.page - 1); });
  $("#npcink-pay-refund-record-next").on("click", function () { requestRecords(recordPage.page + 1); });

  function readColumnLabels($body) {
    return $body.closest("table").find("thead th").map(function () { return $(this).text(); }).get();
  }

  function recordCell(label, value, className) {
    return $("<td>", { role: "cell", class: className || "" })
      .append($("<span>", { class: "npcink-pay-refund-cell-label", "aria-hidden": "true", text: label }))
      .append($("<span>", { class: "npcink-pay-refund-cell-value" }).append(value.jquery ? value : document.createTextNode(value || "—")));
  }

  function messageRow(columns, message, alert) {
    return $("<tr>", { role: "row" }).append($("<td>", { colspan: columns, role: "cell" })
      .append($("<p>", { class: "description npcink-pay-refund-record-empty", role: alert ? "alert" : undefined, text: message })));
  }

  function renderOperationalRows() {
    if (!$operationalRecordsBody.length) {
      return;
    }
    var hasMore = !!operationalPage.next_cursor;
    var hasState = operationalRows.length > 0 || hasMore || operationalCursor > 0;
    $operationalPanel.prop("hidden", !hasState);
    if (!hasState) { $operationalPanel.prop("open", false); }
    $operationalSummary.text(operationalRows.length
      ? "本批 " + operationalRows.length + " 项退款待核对" + (hasMore ? "（还有更多）" : "") + " · 查看详情"
      : (hasMore ? "还有退款状态待检查 · 查看详情" : "本批无待处理项 · 返回首批核对"));
    $("#npcink-pay-refund-operational-next").prop("hidden", !hasMore).prop("disabled", !hasMore);
    $operationalRecordsBody.empty();
    if (!operationalRows.length) {
      messageRow(6, operationalPage.next_cursor ? "本批无可显示状态，请继续下一批。" : "本批无待处理退款状态。").appendTo($operationalRecordsBody);
      return;
    }
    operationalRows.forEach(function (row) {
      var channel = normalizeRecordValue(row.channel);
      var order = normalizeRecordValue(row.order);
      var $button = $("<button>", { type: "button", class: "button npcink-pay-refund-open-operational", text: "查询核对", disabled: channels[channel === "微信" ? "wx" : "zfb"].refunding })
        .attr("data-channel", channel).attr("data-order", order);
      $("<tr>", { role: "row", class: "npcink-pay-refund-record-row" })
        .append(recordCell(operationalLabels[0], order, "npcink-pay-refund-cell-order"))
        .append(recordCell(operationalLabels[1], displayAmount(row.amount), "npcink-pay-refund-amount"))
        .append(recordCell(operationalLabels[2], renderRecordType(channel)))
        .append(recordCell(operationalLabels[3], normalizeRecordValue(row.state), "npcink-pay-refund-cell-wide"))
        .append(recordCell(operationalLabels[4], normalizeRecordValue(row.request_id), "npcink-pay-refund-cell-wide"))
        .append(recordCell(operationalLabels[5], $button, "npcink-pay-refund-cell-action"))
        .appendTo($operationalRecordsBody);
    });
  }

  $operationalRecordsBody.on("click", ".npcink-pay-refund-open-operational", function () {
    var $button = $(this);
    var channel = $button.attr("data-channel");
    var order = $button.attr("data-order");
    var view = channels[channel === "微信" ? "wx" : "zfb"];
    if (view.refunding) { return; }
    view.$input.val(order).trigger("input");
    view.$input.closest("form").trigger("submit");
    view.$input.get(0).scrollIntoView({ block: "center" });
    view.$input.trigger("focus");
  });

  function normalizeRecordValue(value) {
    return value === null || value === undefined ? "" : String(value);
  }

  function displayAmount(value, scale) {
    var amount = Number(value) / (scale || 1);
    return normalizeRecordValue(value) !== "" && Number.isFinite(amount)
      ? amount.toFixed(2) + " " + refundQuery.yuan : "—";
  }

  function renderRecordType(type) {
    var value = normalizeRecordValue(type);
    var className = value === "微信" ? "npcink-pay-refund-status-wx" : value === "支付宝" ? "npcink-pay-refund-status-zfb" : "npcink-pay-refund-status-neutral";
    return $("<span>", {
      class: "npcink-pay-refund-status-badge " + className,
      text: value || "-",
    });
  }

  function renderRecords() {
    var records = dataArray;
    $refundRecordsBody.empty();

    if (!records.length) {
      messageRow(7, $recordKeyword.val() || $recordType.val() || $recordDateFrom.val() || $recordDateTo.val()
        ? "没有符合筛选条件的退款记录。" : "暂无退款记录。").appendTo($refundRecordsBody);
    } else {
      records.forEach(function (record) {
        $("<tr>", { role: "row", class: "npcink-pay-refund-record-row" })
          .append(recordCell(recordLabels[0], normalizeRecordValue(record.order), "npcink-pay-refund-cell-order"))
          .append(recordCell(recordLabels[1], displayAmount(record.amount), "npcink-pay-refund-amount"))
          .append(recordCell(recordLabels[2], renderRecordType(record.type)))
          .append(recordCell(recordLabels[3], normalizeRecordValue(record.time)))
          .append(recordCell(recordLabels[4], normalizeRecordValue(record.user)))
          .append(recordCell(recordLabels[5], normalizeRecordValue(record.id)))
          .append(recordCell(recordLabels[6], normalizeRecordValue(record.reason), "npcink-pay-refund-cell-wide"))
          .appendTo($refundRecordsBody);
      });
    }

    var advancedCount = [$recordType.val(), $recordDateFrom.val(), $recordDateTo.val()].filter(Boolean).length;
    $("#npcink-pay-refund-filter-summary").text(advancedCount ? "更多筛选（" + advancedCount + " 项已启用）" : "更多筛选");
    $("#npcink-pay-refund-record-pagination").prop("hidden", recordPage.pages <= 1 && recordPage.page <= 1);
    $recordSummary.text("第 " + recordPage.page + " 页，显示 " + records.length + " 条，共 " + recordPage.total + " 条。");
    $("#npcink-pay-refund-record-prev").prop("disabled", recordPage.page <= 1);
    $("#npcink-pay-refund-record-next").prop("disabled", recordPage.page >= recordPage.pages);
  }

  $recordKeyword.on("input", function () {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(function () { requestRecords(1); }, 250);
  });
  $recordType.add($recordDateFrom).add($recordDateTo).on("change", function () { requestRecords(1); });
  $("#npcink-pay-refund-record-reset").on("click", function () {
    $recordKeyword.val("");
    $recordType.val("");
    $recordDateFrom.val("");
    $recordDateTo.val("");
    requestRecords(1);
  });

  $("#npcink-pay-refund-operational-next").prop("disabled", !operationalPage.next_cursor);
  renderOperationalRows();
  if (window.location.hash === "#npcink-pay-refund-operational-state" && !$operationalPanel.prop("hidden")) {
    $operationalPanel.prop("open", true);
  }
  renderRecords();
});
