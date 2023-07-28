<script setup lang="ts">
//微信输入框

import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();
import { computed } from "vue";
const form = computed(() => store.configData.npc_refund_config.wx);
</script>

<template>
  <el-form :model="form" label-width="100px">
    <el-form-item label="商户号：">
      <el-col :xs="24" :span="12">
        <el-input
          v-model="form.mch_id"
          placeholder="微信支付商户号"
          clearable
        />
        <el-text class="mx-1" type="info">微信支付中的商户号</el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="证书序列号：">
      <el-col :xs="24" :span="12">
        <el-input
          v-model="form.cert_api"
          placeholder="商户证书序列号"
          clearable
        />
        <el-text class="mx-1" type="info">
          请在 账户中心->API安全->申请API证书，找到您的证书序列号。
        </el-text>
      </el-col>
    </el-form-item>
    <el-form-item label="商户私钥：">
      <el-col :xs="24" :span="12">
        <el-input
          v-model="form.cert_key"
          :rows="2"
          type="textarea"
          placeholder="商户私钥"
        />
        <el-text class="mx-1" type="info"
          >请选择上面对应证书序列号文件的， apiclient_key.pem
          文件，使用文本编辑器打开后复制黏贴至上方。
        </el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>
<style scoped></style>
