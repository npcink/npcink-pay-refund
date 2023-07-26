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
  }

  let dataLocal: DataLocal;

  // 在开发环境中使用 VITE_DATA_LOCAL 环境变量
  if (import.meta.env.VITE_APP_ENV === "development") {
    dataLocal = JSON.parse(import.meta.env.VITE_DATA_LOCAL);
  }
  // 在生产环境中使用真实数据源的相关配置
  else {
    dataLocal = (window as any).dataLocal;
  }

  //const dataLocal: DataLocal = (window as any).dataLocal;
  //
  //const dataLocal={
  //route:"http://localhost:5173/",
  //nonce:"asdf",
  //user:[{id:1,name:"111"},{id:2,name:"222"}]
  //}

  //初始数据
  //const config = {
  //  user: dataLocal.user,
  //};

  //存储数据
  let datas = reactive({
    zfb: {
      npc_zfb_appid: "",
      npc_zfb_private_key: "",
      npc_zfb_public_key: "",
    },
    wx: {
      npc_wx_mch_id: "",
      npc_wx_cert_api: "",
      npc_wx_cert_key: "",
    },
    user: {
      npc_user_user: dataLocal.user as DataLocal["user"],
      npc_user_link: [""],
    },
    config: {
      npc_config_mysql: "1",
      npc_config_config: "1",
    },
  });

  //获取数据
  const getData = async () => {
    try {
      const response = await axios.post(
        `${dataLocal.route}pf/v1/get_option`,
        datas,
        {
          headers: {
            "X-WP-Nonce": dataLocal.nonce,
            "Content-Type": "application/json",
          },
        }
      );
      const responseData = response.data;

      // 使用解构语法来获取响应数据对象中的属性，并为每个属性提供默认值以避免未定义的属性
      const {
        zfb: {
          npc_zfb_appid: zfbAppid = "",
          npc_zfb_private_key: zfbPrivateKey = "",
          npc_zfb_public_key: zfbPublicKey = "",
        } = {},
        wx: {
          npc_wx_mch_id: wxNpcRefundMchId = "",
          npc_wx_cert_api: wxCertApi = "",
          npc_wx_cert_key: wxCertKey = "",
        } = {},
        user: {
          npc_user_user: userUser = [],
          npc_user_link: userLink = [],
        } = {},
        config: {
          npc_config_mysql: configMysql = "1",
          npc_config_config: configConfig = "1",
        } = {},
      } = responseData;

      datas.zfb.npc_zfb_appid = zfbAppid;
      datas.zfb.npc_zfb_private_key = zfbPrivateKey;
      datas.zfb.npc_zfb_public_key = zfbPublicKey;

      datas.wx.npc_wx_mch_id = wxNpcRefundMchId;
      datas.wx.npc_wx_cert_api = wxCertApi;
      datas.wx.npc_wx_cert_key = wxCertKey;

      datas.user.npc_user_user = userUser;
      datas.user.npc_user_link = userLink;

      datas.config.npc_config_mysql = configMysql;
      datas.config.npc_config_config = configConfig;
    } catch (error) {
      window.alert("连接服务器失败或后台读取出错！数据读取失败");
      console.log(error);
    }
  };

  //保存数据
  const saveData = () => {
    console.log("保存数据");
    console.log(datas);
    axios
      .post(dataLocal.route + "pf/v1/update_option", datas, {
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

  return { datas, getData, saveData };
});
