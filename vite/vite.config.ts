import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'



// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    
    AutoImport({
      resolvers: [ElementPlusResolver()],
    }),
    Components({
      resolvers: [ElementPlusResolver()],
    }),

    

],

build: {
  rollupOptions: {
    output: {
      // 指定 chunk 文件名（含导出的代码）
      //chunkFileNames: 'js/[name].js',
      // 指定静态资源文件名（不含导出的代码）
      //assetFileNames: 'assets/[name].[ext]',
      entryFileNames: "index.js",
      assetFileNames: "[name][extname]",
      chunkFileNames: "[name].js",
    },
  },
},


server:{
  proxy: {
    // 选项写法
    '/api': {
      target: 'http://ceshi13.dishait.cn',
      changeOrigin: true,
      rewrite: (path) => path.replace(/^\/api/, '')
      }
  }
},




})
