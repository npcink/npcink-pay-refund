<script setup lang="ts">
//作者输入框
import { ref, toRef, watchEffect } from "vue";
import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();
//拿到数据
const data = store.datas.user;
//数据
const options = toRef(data, "npc_user_user");

//监听下
watchEffect(() => {
  data.npc_user_user = options.value;
});

/**
 * 可访问链接
 */
//拿到链接选项值
//添加框的数量
const inputCount = ref(5);
//拿到默认值
const inputs = toRef(data, "npc_user_link");

//B2主题选项
const configB2 = () => {
  inputs.value = [
    {
      title: "订单管理",
      url: "b2_orders_list",
    },
    {
      title: "退款",
      url: "refund_querys",
    },
  ];
};
</script>

<template>
  {{ inputs[1] }}
  <el-form label-width="100px">
    <el-form-item label="退款操作员：">
      <el-col :span="12">
        <el-select
          v-model="options"
          multiple
          filterable
          style="width: 100%"
          placeholder="选择有退款权限的用户"
        >
          <el-option
            v-for="item in options"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
        <br />
        <el-text class="mx-1" type="info">
          选中的操作员有权限进行退款操作(仅限“订阅者”以上权限)
        </el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="访问页面：">
      <el-col :span="12">
        快捷配置：
        <el-button type="primary" size="default" round @click="configB2()"
          >B2</el-button
        >

        <div class="item_input" v-for="index in inputCount" :key="index">
         
          
          <el-input v-model="inputs[index - 1]" placeholder="请输入链接名">
            <template #prepend>名称</template>
          </el-input>
          <el-input v-model="inputs[index - 1]" placeholder="请输入链接">
            <template #prepend>链接</template>
          </el-input>
        </div>
        <el-text class="mx-1" type="info">
          被选中的操作员仅能访问以上页面
        </el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>
<style scoped>
.item_input {
  padding: 1em 0 0;
}
</style>
