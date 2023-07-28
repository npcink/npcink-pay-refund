<script setup lang="ts">
import wx from "./components/wx.vue";
import zfb from "./components/zfb.vue";
import user from "./components/user.vue";
import config from "./components/config.vue";
import { mainStore } from "./store/store.js";
import { onMounted } from "vue";
//实例化
const store = mainStore();

//保存数据
const saveConfig = () => {
  store.saveData();
};
//页面的开始获取数据
onMounted(async () => {
  await store.getData();
});

//打印数组
const showData = () => {
  console.log(store.configData);
  console.log(store.configData.npc_refund_config);
  console.log(store.configData.npc_refund_config.user);
  console.log(store.configData.npc_refund_config.user.user);
};
</script>

<template>
  <div class="tabCard">
    <el-tabs type="border-card">
      <el-tab-pane label="支付宝">
        <zfb></zfb>
      </el-tab-pane>
      <el-tab-pane label="微信">
        <wx></wx>
      </el-tab-pane>

      <el-tab-pane label="用户">
        <user></user>
      </el-tab-pane>

      <el-tab-pane label="设置">
        <config></config>
      </el-tab-pane>
    </el-tabs>

    <p class="submit">
      <input
        type="submit"
        class="button button-primary"
        value="保存"
        @click="saveConfig()"
      />
    </p>
    <button @click="showData()">打印</button>
  </div>
</template>

<style scoped>
.tabCard {
  max-width: 900px;
}
.tabCard :deep(input) {
  border: none;
}
.tabCard :deep(input[type="text"]:focus) {
  box-shadow: none;
}

.tabCard :deep(input[readonly]) {
  background-color: #fff;
}
</style>
