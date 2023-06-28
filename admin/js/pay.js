
// 定义 trimHyphen() 函数
//订单处理算法
//将格式转换D606224553221068-0转为D606224553221068
function trimHyphen(input) {
 
// 判断输入值的末尾是否是 "-1" 或 "-2" 的形式，如果是，则直接返回原始输入值
  if (input.endsWith('-1') || input.endsWith('-2')|| input.endsWith('-3')) {
    return input;
  }
    return input.replace(/-\d+$/, '');
  
}




 
 
 
        jQuery(document).ready(function($) {
           //支付宝查询
            
            $('#my-plugin-button').click(function() {
                 var data = { 
                     action: 'my_plugin_request_data', 
                     param: trimHyphen($('#my-plugin-input').val()),
                 };
                 //console.log(data.param);
                $.ajax({
                    url: public.ajaxurl,
                    type: 'POST',
                    data:data,
                    
                    success: function(data) {
                        $('#my-plugin-data').html(data);
                    }
                });
            });
            
        
            
 
        
     
    
    
    

    
         //支付宝退款
       $(document).on('click', '#order-btn', function() {
           const data = {
               action: 'my_plugin_order_detail', 
               order_id : $(this).data('order-id'),//订单号
               order_time : $(this).data('order-time'), // 获取订单时间
               order_amount : $(this).data('order-amount'), // 获取订单总金额
               order_reason : $('#npcink-zfb-reason').val(),  // 获取订单退款原因
           };
            
            //退款原因为空则进行提示
             if ($('#npcink-zfb-reason').val() === "") {
        alert("请输入退款原因");
        return false;
    }
           
            
            $.ajax({
                url: public.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    // 处理返回的数据
                     $('#my-plugin-data').html(response);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR, textStatus, errorThrown);
                }
            });
        });
            
            
            //微信支付查询
             $('#npcink-wx-button').click(function() {
                 
                 var data = { 
                     action: 'my_plugin_request_wx', 
                     order_id: trimHyphen($('#npcink-wx-input').val()),
                 };
                 
                $.ajax({
                    url: public.ajaxurl,
                    type: 'POST',
                     data: data,
                    success: function(data) {
                        $('#npcink-wx-data').html(data);
                    }
                });
            });
            
       //微信退款
       $(document).on('click', '#wx-order-btn', function() {
           const data = {
               action: 'npcink_refund_wx',
               order_id : $(this).data('order-id'),//订单号
               order_amount : $(this).data('order-amount'), // 获取订单总金额
               order_reason : $('#npcink-wx-reason').val(), //获取退款原因
           };

            //退款原因为空则进行提示
             if ($('#npcink-wx-reason').val() === "") {
        alert("请输入退款原因");
        return false;
    }
            
            
            $.ajax({
                url: public.ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    // 处理返回的数据
                    
                     $('#npcink-wx-data').html(response);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log(errorThrown);
                }
            });
        });
        
        
        //数据展示
          
        $.ajax({
    url: public.json,
    dataType: 'json',
    
    success: function(data) {
        //console.table(data.data);
    var result = '';
    
result += '<table class="wp-list-table widefat fixed striped" style="max-width: 1200px;"><tr><th>ID</th><th>金额</th><th>时间</th><th>订单号</th><th>操作员</th><th>原因</th><th>类型</th></tr>';

// 使用slice方法获取最后10条数据，并倒序排序
var lastTenData = data.data.slice(-10).reverse();

// 遍历最后10条数据，构造HTML表格内容
lastTenData.forEach(function(item) {
  result += '<tr>'
    + '<td>' + item.id + '</td>'
    + '<td>' + item.amount + '元</td>'
    + '<td>' + item.time + '</td>'
    + '<td>' + item.order + '</td>'
    + '<td>' + item.user + '</td>'
    + '<td>' + item.reason + '</td>'
    + '<td>' + item.type + '</td>'
    + '</tr>';
});

result += '</table>';
      
    $('#result').html(result);
},

    error: function(jqXHR, textStatus, errorThrown) {
      console.log('Error: ' + textStatus + ' ' + errorThrown);
    }
  });
  

        
        
            
            
            //表格下载JSON数据
           
			
			$('#export-btn').click(function() {
      $.ajax({
        url: public.json,
        dataType: 'json',
        success: function(data) {
          var keys = Object.keys(data.data[0]);
          var csvString = keys.join(',') + '\n';
          data.data.forEach(function(item) {
            var row = [];
            keys.forEach(function(key) {
              row.push(item[key]);
            });
            csvString += row.join(',') + '\n';
          });
          var blob = new Blob([csvString], { type: 'text/csv;charset=utf-8' });
          saveAs(blob, '退款订单操作记录.csv');
        }
      });
    });
			
			
			
			
			
    
});

