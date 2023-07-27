//下载数据库文件
jQuery(document).ready(function ($) {
  // 使用 AJAX 请求获取数据库表数据
  //表格下载JSON数据

  $("#button_download").click(function () {
    $.ajax({
      url: api.ajaxurl,
      type: "POST",
      data: {
        action: "download_data", // 用于在 PHP 中识别请求类型的参数
      },
      success: function (response) {
        // 处理从 PHP 返回的数据
        const data = response;
        if (data.length === 0) {
          alert("暂无数据可供下载");
          return;
        }
        

        // 定义自定义列名的映射对象
        const columnMapping = {
          id: "退款编号",
          n_amount: "退款金额",
          n_time: "退款时间",
          n_order: "订单号",
          n_user: "操作员工",
          n_type: "类型",
          n_reason: "退款原因",
        };

        // 将数据转换为 CSV 格式，并应用自定义列名
        const csvData = convertToCSV(data, columnMapping);
        const csvFileName = "退款订单记录表.csv";

        // 使用 FileSaver.js 下载 CSV 文件
        const blob = new Blob([csvData], { type: "text/csv;charset=utf-8" });
        saveAs(blob, csvFileName);
      },
      error: function (xhr, ajaxOptions, thrownError) {
        console.log(thrownError);
      },
    });
  });

  // 将数据转换为 CSV 格式
  // 将数据转换为 CSV 格式，并应用自定义列名
  function convertToCSV(data, columnMapping) {
    var csv = "";

    // 添加表头
    var headers = Object.keys(data[0]);
    var mappedHeaders = headers.map(function (header) {
      return columnMapping[header] || header;
    });

    csv += mappedHeaders.join(",") + "\n";

    // 添加数据行
    for (var i = 0; i < data.length; i++) {
      var row = Object.values(data[i]);
      csv += row.join(",") + "\n";
    }

    return csv;
  }
});
