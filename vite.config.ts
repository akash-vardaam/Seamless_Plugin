import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: 'https://mafp.seamlessams.com',
        changeOrigin: true,
        secure: false,
        followRedirects: true,
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
      },
    },
  },
  build: {
    // Output directly into the WordPress plugin's react-build folder
    outDir: path.resolve(
      __dirname,
      'seamless-20260211-190730/seamless-wordpress-plugin/src/Public/assets/react-build/dist'
    ),
    emptyOutDir: true,
    rollupOptions: {
      output: {
        // Stable, non-hashed filenames so the PHP enqueue logic doesn't need to scan
        entryFileNames: 'assets/index-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/index-[hash].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
})
