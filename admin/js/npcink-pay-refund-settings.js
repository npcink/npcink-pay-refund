//下载数据库文件
jQuery(document).ready(function ($) {
  var refundSettings = window.npcinkPayRefundSettings;
  if (!refundSettings || !refundSettings.strings) { return; }
  var strings = refundSettings.strings;

  function format(key) {
    var args = arguments;
    return strings[key].replace(/%([1-9][0-9]*)\$s/g, function (placeholder, index) {
      return Number(index) < args.length ? String(args[Number(index)]) : placeholder;
    });
  }

  var $downloadButton = $("#npcink-pay-refund-download");

  // 使用 AJAX 请求获取数据库表数据
  //表格下载JSON数据

  function showSettingsNotice(message, type) {
    var noticeType = type || "error";
    $(".npcink-pay-refund-settings-notice").remove();
    $("<div>", {
      class: "notice notice-" + noticeType + " inline npcink-pay-refund-settings-notice",
    })
      .append($("<p>", { text: message }))
      .insertAfter($downloadButton.closest("p"));
  }

  $downloadButton.click(function () {
    $.ajax({
      url: refundSettings.ajaxurl,
      type: "POST",
      data: {
        action: "npcink_pay_refund_download_data", // 用于在 PHP 中识别请求类型的参数
        nonce: refundSettings.nonce,
      },
      success: function (response) {
        // 处理从 PHP 返回的数据
        const data = Array.isArray(response)
          ? response
          : response && response.success && response.data
          ? response.data.rows
          : [];
        if (data.length === 0) {
          showSettingsNotice(strings.noDownloadRows);
          return;
        }
        if (response && response.success && response.data && response.data.truncated) {
          showSettingsNotice(format("downloadLimited", response.data.limit), "warning");
        }
        

        // 定义自定义列名的映射对象
        const columnMapping = {
          id: strings.csvId,
          n_amount: strings.csvAmount,
          n_time: strings.csvTime,
          n_order: strings.csvOrder,
          n_user: strings.csvUser,
          n_type: strings.csvType,
          n_reason: strings.csvReason,
        };

        // 将数据转换为 CSV 格式，并应用自定义列名
        const csvData = convertToCSV(data, columnMapping);
        const csvFileName = strings.csvFilename;

        const blob = new Blob(["\uFEFF", csvData], { type: "text/csv;charset=utf-8" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = csvFileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      },
      error: function () {
        showSettingsNotice(strings.downloadFailed);
      },
    });
  });

  var $refundUserSearch = $("#npcink-pay-refund-refund-user-search");
  if ($refundUserSearch.length) {
    var $refundUserResults = $("#npcink-pay-refund-refund-user-results");
    var $refundUserSpinner = $("#npcink-pay-refund-refund-user-spinner");
    var $selectedRefundUsers = $("#npcink-pay-refund-selected-refund-users");
    var refundUserSearchTimer = null;
    var refundUserSearchRequest = null;

    function getSelectedRefundUserIds() {
      var selected = {};
      $selectedRefundUsers.find(".npcink-pay-refund-selected-user").each(function () {
        selected[String($(this).data("user-id"))] = true;
      });
      return selected;
    }

    function updateSelectedRefundUserEmptyState() {
      var hasUsers = $selectedRefundUsers.find(".npcink-pay-refund-selected-user").length > 0;
      $selectedRefundUsers.find("[data-empty-state='1']").remove();
      if (!hasUsers) {
        $("<tr>", {
          "data-empty-state": "1",
        })
          .append(
            $("<td>", { colspan: 4 }).append(
              $("<p>", {
                class: "description npcink-pay-refund-user-empty",
                text: strings.emptySelected,
              })
            )
          )
          .appendTo($selectedRefundUsers);
      }
    }

    function renderRefundUserResults(users) {
      var selected = getSelectedRefundUserIds();
      $refundUserResults.empty();

      if (!users.length) {
        $("<p>", {
          class: "description npcink-pay-refund-user-search-message",
          text: strings.noUsers,
        }).appendTo($refundUserResults);
        return;
      }

      var $table = $("<table>", {
        class: "widefat striped npcink-pay-refund-user-table npcink-pay-refund-user-result-table",
      });
      var $thead = $("<thead>").append(
        $("<tr>")
          .append($("<th>", { text: strings.name }))
          .append($("<th>", { text: strings.account }))
          .append($("<th>", { text: strings.role }))
          .append($("<th>", { class: "npcink-pay-refund-user-action-column", text: strings.action }))
      );
      var $tbody = $("<tbody>");

      users.forEach(function (user) {
        var userId = String(user.id);
        var isSelected = !!selected[userId];

        $("<tr>")
          .append($("<td>").append($("<strong>", { text: user.name })))
          .append($("<td>", { text: format("accountWithId", user.login, user.id) }))
          .append($("<td>", { text: (user.roles || []).join("、") }))
          .append(
            $("<td>").append(
              $("<button>", {
                type: "button",
                class: "button npcink-pay-refund-add-refund-user",
                disabled: isSelected,
                text: isSelected ? strings.alreadySelected : strings.add,
              }).data("user", user)
            )
          )
          .appendTo($tbody);
      });

      $table.append($thead, $tbody).appendTo($refundUserResults);
    }

    function renderSelectedRefundUser(user) {
      var $row = $("<tr>", {
        class: "npcink-pay-refund-selected-user",
        "data-user-id": user.id,
      });
      $("<td>")
        .append(
          $("<input>", {
            type: "hidden",
            name: "npcink_pay_refund_config[user][user][]",
            value: user.id,
          })
        )
        .append($("<strong>", { text: user.name }))
        .appendTo($row);
      $("<td>", { text: format("accountWithId", user.login, user.id) }).appendTo($row);
      $("<td>", { text: (user.roles || []).join("、") }).appendTo($row);
      $("<td>")
        .append(
          $("<button>", {
            type: "button",
            class: "button npcink-pay-refund-remove-refund-user",
            text: strings.remove,
          })
        )
        .appendTo($row);

      return $row;
    }

    function getRenderedRefundUserResults() {
      return $refundUserResults
        .find(".npcink-pay-refund-add-refund-user")
        .map(function () {
          return $(this).data("user");
        })
        .get();
    }

    function searchRefundUsers(term) {
      if (refundUserSearchRequest) {
        refundUserSearchRequest.abort();
      }

      if (term.length < 1) {
        $refundUserResults.empty().append(
          $("<p>", {
            class: "description npcink-pay-refund-user-search-message",
            text:
              strings.typeToSearch,
          })
        );
        return;
      }

      $refundUserSpinner.addClass("is-active");
      $refundUserResults.empty().append(
        $("<p>", {
          class: "description npcink-pay-refund-user-search-message",
          text: strings.searching,
        })
      );

      refundUserSearchRequest = $.ajax({
        url: refundSettings.ajaxurl,
        type: "POST",
        data: {
          action: "npcink_pay_refund_search_refund_users",
          nonce: refundSettings.nonce,
          term: term,
        },
      })
        .done(function (response) {
          var users = response && response.success && response.data ? response.data.users : [];
          renderRefundUserResults(users || []);
        })
        .fail(function (xhr, status) {
          if (status === "abort") {
            return;
          }
          $refundUserResults.empty().append(
            $("<p>", {
              class: "description npcink-pay-refund-user-search-message",
              text: strings.searchFailed,
            })
          );
        })
        .always(function () {
          $refundUserSpinner.removeClass("is-active");
          refundUserSearchRequest = null;
        });
    }

    $refundUserSearch.on("input", function () {
      var term = $.trim($(this).val());
      clearTimeout(refundUserSearchTimer);
      refundUserSearchTimer = setTimeout(function () {
        searchRefundUsers(term);
      }, 300);
    });

    $refundUserResults.on("click", ".npcink-pay-refund-add-refund-user", function () {
      var user = $(this).data("user");
      if (!user || getSelectedRefundUserIds()[String(user.id)]) {
        return;
      }

      $selectedRefundUsers.find("[data-empty-state='1']").remove();
      renderSelectedRefundUser(user).appendTo($selectedRefundUsers);
      renderRefundUserResults(getRenderedRefundUserResults());
    });

    $selectedRefundUsers.on("click", ".npcink-pay-refund-remove-refund-user", function () {
      $(this).closest(".npcink-pay-refund-selected-user").remove();
      updateSelectedRefundUserEmptyState();
      if ($refundUserSearch.val().length >= 1) {
        searchRefundUsers($.trim($refundUserSearch.val()));
      }
    });

    updateSelectedRefundUserEmptyState();
  }

  $(".npcink-pay-refund-check-payment-config").on("click", function () {
    var $button = $(this);
    var channel = $button.data("channel");
    var $result = $("#npcink-pay-refund-payment-check-" + channel);
    var originalText = $button.text();

    if (!channel || !$result.length) {
      return;
    }

    $button
      .prop("disabled", true)
      .text(strings.checkingConfig);
    $result.empty().append(
      $("<p>", {
        class: "description",
        text: strings.checkingConfig,
      })
    );

    $.ajax({
      url: refundSettings.ajaxurl,
      type: "POST",
      dataType: "json",
      data: {
        action: "npcink_pay_refund_check_payment_config",
        nonce: refundSettings.nonce,
        channel: channel,
      },
    })
      .done(function (response) {
        if (response && response.success && response.data) {
          renderPaymentCheckResult($result, response.data);
          return;
        }

        renderPaymentCheckError(
          $result,
          response && response.data && response.data.message
            ? response.data.message
            : strings.checkConfigFailed
        );
      })
      .fail(function (xhr) {
        var message =
          xhr &&
          xhr.responseJSON &&
          xhr.responseJSON.data &&
          xhr.responseJSON.data.message
            ? xhr.responseJSON.data.message
            : strings.checkConfigFailed;
        renderPaymentCheckError($result, message);
      })
      .always(function () {
        $button.prop("disabled", false).text(originalText);
      });
  });

  function renderPaymentCheckError($container, message) {
    $container.empty().append(
      $("<div>", {
        class: "notice notice-error inline",
      }).append($("<p>", { text: message }))
    );
  }

  function renderPaymentCheckResult($container, data) {
    var items = Array.isArray(data.items) ? data.items : [];
    var isOk = data.status === "ok";
    var $notice = $("<div>", {
      class: "notice " + (isOk ? "notice-success" : "notice-error") + " inline",
    }).append(
      $("<p>").append($("<strong>", { text: data.message || (isOk ? strings.checkPassed : strings.checkBlocked) }))
    );

    var $table = $("<table>", {
      class: "widefat striped npcink-pay-refund-check-table",
    });
    var $tbody = $("<tbody>");

    $("<thead>")
      .append(
        $("<tr>")
          .append($("<th>", { text: strings.status }))
          .append($("<th>", { text: strings.item }))
          .append($("<th>", { text: strings.details }))
      )
      .appendTo($table);

    items.forEach(function (item) {
      var status = item.status === "ok" ? "ok" : "error";
      $("<tr>")
        .append(
          $("<td>").append(
            $("<span>", {
              class: "npcink-pay-refund-status-badge npcink-pay-refund-status-" + status,
              text: status === "ok" ? strings.pass : strings.blocked,
            })
          )
        )
        .append($("<td>", { text: item.label || "" }))
        .append($("<td>", { text: item.message || "" }))
        .appendTo($tbody);
    });

    $table.append($tbody);
    $container.empty().append($notice);
    if (items.length) {
      var $details = $("<details>", { class: "npcink-pay-refund-disclosure" }).prop("open", !isOk);
      $details.append($("<summary>", { text: isOk ? strings.viewChecks : strings.viewBlocked }), $table);
      $container.append($details);
    }
  }

  // 将数据转换为 CSV 格式，并应用自定义列名
  function convertToCSV(data, columnMapping) {
    var headers = Object.keys(columnMapping);
    var csv = headers.map(function (header) {
      return escapeCsvValue(columnMapping[header]);
    }).join(",") + "\r\n";
    for (var i = 0; i < data.length; i++) {
      var row = headers.map(function (header) { return escapeCsvValue(data[i][header]); });
      csv += row.join(",") + "\r\n";
    }

    return csv;
  }

  function escapeCsvValue(value) {
    var text = value === null || value === undefined ? "" : String(value);
    if (/^[\t\r\n]|^\s*[=+\-@\uFF1D\uFF0B\uFF0D\uFF20]/.test(text)) {
      text = "'" + text;
    }
    return '"' + text.replace(/"/g, '""') + '"';
  }
});
