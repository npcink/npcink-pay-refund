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
  //支付宝查询

  $("#my-plugin-button").click(function () {
    var data = {
      action: "zfb_order_query",
      param: trimHyphen($("#my-plugin-input").val()),
    };
    //console.log(data.param);
    $.ajax({
      url: public.ajaxurl,
      type: "POST",
      data: data,

      success: function (data) {
        $("#my-plugin-data").html(data);
      },
    });
  });

  //支付宝退款
  $(document).on("click", "#order-btn", function () {
    const data = {
      action: "zfb_order_refund",
      order_id: $(this).data("order-id"), //订单号
      order_time: $(this).data("order-time"), // 获取订单时间
      order_amount: $(this).data("order-amount"), // 获取订单总金额
      order_reason: $("#npcink-zfb-reason").val(), // 获取订单退款原因
    };

    //退款原因为空则进行提示
    if ($("#npcink-zfb-reason").val() === "") {
      alert("请输入退款原因");
      return false;
    }

    $.ajax({
      url: public.ajaxurl,
      type: "POST",
      data: data,
      success: function (response) {
        // 处理返回的数据
        $("#my-plugin-data").html(response);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.log(jqXHR, textStatus, errorThrown);
      },
    });
  });

  //微信支付查询
  $("#npcink-wx-button").click(function () {
    var data = {
      action: "wx_order_query",
      order_id: trimHyphen($("#npcink-wx-input").val()),
    };

    $.ajax({
      url: public.ajaxurl,
      type: "POST",
      data: data,
      success: function (data) {
        $("#npcink-wx-data").html(data);
      },
    });
  });

  //微信退款
  $(document).on("click", "#wx-order-btn", function () {
    var $button = $(this);

    // 禁用按钮
    $button.prop("disabled", true);

    // 恢复按钮可用状态
    setTimeout(function () {
      $button.prop("disabled", false);
    }, 20000); // 10秒后恢复按钮可用状态

    const data = {
      action: "wx_order_refund",
      order_id: $(this).data("order-id"), //订单号
      order_amount: $(this).data("order-amount"), // 获取订单总金额
      order_reason: $("#npcink-wx-reason").val(), //获取退款原因
    };

    //退款原因为空则进行提示
    if ($("#npcink-wx-reason").val() === "") {
      alert("请输入退款原因");
      return false;
    }

    $.ajax({
      url: public.ajaxurl,
      type: "POST",
      data: data,
      success: function (response) {
        // 处理返回的数据

        $("#npcink-wx-data").html(response);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.log(errorThrown);
      },
    });
  });

  //数据展示
  const dataTableBody = $("#dataTable tbody"); // 获取表格的 tbody 元素
  const dataArray = JSON.parse(public.data);
  dataArray.forEach(function (data) {
    // 创建一个新行的 HTML 代码
    let newRow =
      "<tr>" +
      "<td>" +
      data.id +
      "</td>" +
      "<td>" +
      data.amount +
      "</td>" +
      "<td>" +
      data.time +
      "</td>" +
      "<td>" +
      data.order +
      "</td>" +
      "<td>" +
      data.user +
      "</td>" +
      "<td>" +
      data.type +
      "</td>" +
      "<td>" +
      data.reason +
      "</td>" +
      "</tr>";

    // 将新行添加到表格的 tbody 中
    dataTableBody.append(newRow);
  });
});
