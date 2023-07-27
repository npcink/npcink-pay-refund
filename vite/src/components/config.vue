<script setup lang="ts">
//设置选项
import { computed } from "vue";
import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();
const form = computed(() =>store.configData.npc_refund_config.config);
</script>

<template>
 
  <h3>删除插件的同时</h3>
  <el-form :model="form" label-width="200px">
    <el-form-item label="删除数据库">
      <el-col :span="18">
        <el-switch
          v-model="form.mysql"
          active-value="1"
          inactive-value="0"
        />
        <el-text class="mx-1" type="info">移除记录退款订单用数据库</el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="清空设置选项">
      <el-col :span="18">
        <el-switch
          v-model="form.config"
          active-value="1"
          inactive-value="0"
        />
        <el-text class="mx-1" type="info">移除所有设置数据</el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>

<style scoped>
span {
  display: block;
}
</style>
