<script setup lang="ts">
//支付宝输入框
import { computed } from "vue";
import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();
//通过计算属性拿到最新值
const form = computed(() => store.configData.npc_refund_config.zfb);
</script>

<template>
  <el-form :model="form" label-width="100px">
    <el-form-item label="APP ID：">
      <el-col :span="12">
        <el-input show-password v-model="form.appid" placeholder="APP ID" />
        <el-text class="mx-1" type="info">支付宝中的APP ID</el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="应用私钥：">
      <el-col :span="12">
        <el-input
          v-model="form.private_key"
          :rows="5"
          type="textarea"
          placeholder="应用私钥"
        />
        <el-text class="mx-1" type="info">
          本主题使用的是 RSA2 算法生成的私钥。请使用 RSA2 算法来生成。
        </el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="支付宝公钥：">
      <el-col :span="12">
        <el-input
          v-model="form.public_key"
          :rows="5"
          type="textarea"
          placeholder="支付宝公钥"
        />
        <el-text class="mx-1" type="info">
          请在账户中心->密钥管理->开放平台密钥，找到添加了支付功能的应用，根据你的加密类型，查看支付宝公钥。
        </el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>
<style scoped></style>
