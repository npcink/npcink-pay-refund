import Mock from "mockjs";
const Random = Mock.Random;
const randoms = Random.cname();
//模拟的请求数据
export default {
  getData: () => {
    return {
      zfb: {
        appid: Random.cname(),
        private_key: Random.cname(),
        public_key: Random.cname(),
      },
      wx: {
        npc_refund_mch_id: Random.cname(),
        cert_api: Random.cname(),
        cert_key: Random.cname(),
      },
      user: ["1", "2"],
    };
  },
  postData: () => {
    return {
      code: 200,
      tableData2: [
        {
          id: "01",
          name: "post测试001",
        },
        {
          id: "02",
          name: "post测试002",
        },
        {
          id: "03",
          name: "post测试003",
        },
        {
          id: "04",
          name: "post测试004",
        },
      ],
    };
  },
};
