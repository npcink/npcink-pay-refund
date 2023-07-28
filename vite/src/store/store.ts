import { defineStore } from "pinia";
import { reactive } from "vue";
import axios from "axios";

export const mainStore = defineStore("main", () => {
  interface DataLocal {
    route: string;
    nonce: string;
    user: {
      id: string;
      name: string;
    }[];
    link: {
      title: string;
      url: string;
    }[];
  }

  //传来的值
  let dataLocal: DataLocal;

  // 在开发环境中使用 VITE_DATA_LOCAL 环境变量
  if (import.meta.env.VITE_APP_ENV === "development") {
    dataLocal = JSON.parse(import.meta.env.VITE_DATA_LOCAL);
  }
  // 在生产环境中使用真实数据源的相关配置
  else {
    dataLocal = (window as any).dataLocal;
  }

  //用户
  const userList = dataLocal.user;

  //保存数据
  const configData = reactive({
    //选项值
    npc_refund_config: {
      zfb: {
        appid: "",
        private_key: "",
        public_key: "",
      },
      wx: {
        mch_id: "",
        cert_api: "",
        cert_key: "",
      },
      user: {
        user: [] as Number[],
        link: [
          {
            title: "",
            url: "",
          },
        ],
      },
      config: {
        mysql: 1,
        config: 1,
      },
    },
  });

  //获取数据
  const getData = async () => {
    try {
      const response = await axios.post(
        `${dataLocal.route}pf/v1/get_option`,
        configData,
        {
          headers: {
            "X-WP-Nonce": dataLocal.nonce,
            "Content-Type": "application/json",
          },
        }
      );
      //拿到需要的值
      const axiosData = response.data.npc_refund_config;

      //拿到值了才赋值，拿到空值就用默认值
      if (axiosData) {
        configData.npc_refund_config = axiosData;
      }
    } catch (error) {
      window.alert("连接服务器失败或后台读取出错！数据读取失败");
      console.log(error);
    }
  };

  //保存数据
  const saveData = () => {
    console.log("保存数据");
    console.log(configData);
    axios
      .post(dataLocal.route + "pf/v1/update_option", configData, {
        headers: {
          "X-WP-Nonce": dataLocal.nonce,
        },
      })
      .then((response) => {
        console.log(response.data.message);
        alert("保存成功");
      })
      .catch((_error) => {
        alert("保存失败");
        console.log(_error);
      });
  };

  return { configData, userList, getData, saveData };
});
