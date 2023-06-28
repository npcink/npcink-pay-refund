import { createApp } from 'vue'
import App from './App.vue'
import store from './store'


  //开发用样式
  //import "./style.css"
  //开发用mockjs拦截
  //import "./mock/index.js"
  

//挂载根组件
const app = createApp(App);


//注册Pinia
app.use(store);
//进行应用挂载
app.mount("#app_refund");
