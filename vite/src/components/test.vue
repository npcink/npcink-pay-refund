<script setup lang="ts">
import { reactive, ref,  watch } from "vue";
import { mainStore } from "../store/store.js";

const store = mainStore();
const data = store.datas.user;

const inputCount = ref(5);
const inputs = reactive(Array.from({ length: inputCount.value }, () => ({
  title: "",
  url: ""
})));

watch(inputCount, (newVal) => {
  for (let i = inputs.length; i < newVal; i++) {
    inputs.push({ title: "", url: "" });
  }
  if (inputs.length > newVal) {
    inputs.splice(newVal);
  }
});

if (data && Array.isArray(data.npc_user_link)) {
  const defaultValues = data.npc_user_link;
  for (let i = 0; i < inputs.length && i < defaultValues.length; i++) {
    inputs[i].title = defaultValues[i].title || "";
    inputs[i].url = defaultValues[i].url || "";
  }
}
</script>

<template>
  <div v-for="(input, index) in inputs" :key="index">
    <el-input v-model="input.title" placeholder="请输入链接名"></el-input>
    <el-input v-model="input.url" placeholder="请输入链接"></el-input>
  </div>
</template>
