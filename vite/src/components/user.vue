<script setup lang="ts">
//作者输入框
import { ref, toRef, watchEffect } from "vue";
import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();

//数据
const value = toRef(store.datas, "npc_refund_user");

//监听下
watchEffect(() => {
  store.datas.npc_refund_user = value.value;
});

//拿到选项值
const optionss = ref(store.config.user);

//添加框
const inputCount = ref(2);
const inputs = ref([]);

const addInput = () => {
  inputCount.value++;
};
</script>

<template>
  <el-form label-width="100px">
    <el-form-item label="退款操作员：">
      <el-col :span="12">
        <el-select
          v-model="value"
          multiple
          filterable
          style="width: 100%"
          placeholder="选择有退款权限的用户"
        >
          <el-option
            v-for="item in optionss"
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
        <el-button @click="addInput">添加新页面</el-button>
        <div class="item_input" v-for="index in inputCount" :key="index">
          <el-input v-model="inputs[index - 1]" placeholder="请输入网址">
            <template #prepend
              >http(s)://xx.com/wp-admin/admin.php?page=</template
            >
          </el-input>
        </div>
        <el-text class="mx-1" type="info">
          选中的操作员仅能访问以上页面
        </el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>
<style scoped>
.item_input {
  padding: 1em 0;
}
</style>
