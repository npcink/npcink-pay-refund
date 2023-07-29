<script setup lang="ts">
//作者输入框
import { computed } from "vue";
import { Delete } from "@element-plus/icons-vue";
import { mainStore } from "../store/store.js";
//实例化
const store = mainStore();

//页面的开始获取数据

//拿到数据
const form = computed(() => store.configData.npc_refund_config.user);

//用户数据
const options = store.userList;

//B2主题选项
const configB2 = () => {
  form.value.link = [
    {
      title: "订单管理",
      url: "http://www.site.com/wp-admin/admin.php?page=b2_orders_list",
    },
    {
      title: "退款管理",
      url: "https://www.site.com/wp-admin/index.php?page=refund_querys",
    },
  ];
};

//添加选项
const configFive = () => {
  form.value.link.push({
    title: "",
    url: "",
  });
};

//删除选项
const configDelt = (index: number) => {
  if (index >= 0 && index < form.value.link.length) {
    form.value.link.splice(index, 1);
  }
};

//TODO:添加格式检查,避免用户在链接框中输入中文
</script>

<template>
  <el-form :model="form" label-width="100px">
    <el-form-item label="退款操作员：">
      <el-col :xs="24" :span="18">
        <el-select
          v-model="form.user"
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
      <el-col :xs="24" :span="18">
        快捷配置：
        <el-button type="primary" size="default" round @click="configB2()">
          B2
        </el-button>

        <div class="item_input" v-for="(item, index) in form.link" :key="index">
          <el-row>
            <el-col :span="3" class="delt">
              <el-button
                type="danger"
                size="default"
                :icon="Delete"
                @click="configDelt(index)"
                circle
              ></el-button>
            </el-col>
            <el-col :span="21">
              <el-input
                v-model="item.title"
                placeholder="请输入链接名"
                clearable
              >
                <template #prepend>名称</template>
              </el-input>
              <el-input v-model="item.url" placeholder="请输入链接" clearable>
                <template #prepend>链接</template>
              </el-input>
            </el-col>
          </el-row>
        </div>
        <br />
        <el-button type="primary" size="default" round @click="configFive()">
          添加
        </el-button>
        <el-text class="mx-1" type="info">
          <dl>
            <dt>被选中的操作员仅能访问以上页面</dt>
            <dd>用户ID为1的不受影响</dd>
            <dd>以链接中的?page=后的内容作判断条件</dd>
          </dl>
        </el-text>
      </el-col>
    </el-form-item>
  </el-form>
</template>
<style scoped>
.item_input {
  padding: 1em 0 0;
}
.el-input-group {
  padding: 0.2em 0 0;
}
.delt {
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
