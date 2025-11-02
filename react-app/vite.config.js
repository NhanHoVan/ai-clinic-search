import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import fs from 'fs';

const appsDir = path.resolve(__dirname, 'src/apps');

function findApps() {
  const apps = {};
  if (!fs.existsSync(appsDir)) {
    console.warn(`⚠️ Không tìm thấy thư mục apps tại ${appsDir}`);
    return apps;
  }

  for (const category of fs.readdirSync(appsDir)) {
    const categoryPath = path.join(appsDir, category);
    if (!fs.statSync(categoryPath).isDirectory()) continue;

    for (const app of fs.readdirSync(categoryPath)) {
      const appPath = path.join(categoryPath, app);
      const entryFile = path.join(appPath, 'index.jsx');
      if (fs.existsSync(entryFile)) {
        apps[`${category}/${app}`] = entryFile;
      }
    }
  }
  return apps;
}

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'build',
    cssCodeSplit: false,
    rollupOptions: {
      input: findApps(),
      output: {
        format: 'es',
        entryFileNames: '[name]/index.js',
        chunkFileNames: 'shared/chunks/[name]-[hash].js',
        assetFileNames: 'shared/assets/[name]-[hash][extname]',
        manualChunks: {
          'vendor-react': ['react', 'react-dom'],
          'vendor-antd': ['antd', '@ant-design/icons'],
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src/common'),
    },
  },
  base: './',
})
