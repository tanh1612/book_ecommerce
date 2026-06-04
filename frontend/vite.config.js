// vite.config.js
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      "/api": {
        target: "http://book_ecommerce.test",
        changeOrigin: true,
      },
      "/sanctum": {
        target: "http://book_ecommerce.test",
        changeOrigin: true,
      },
    },
  },
});
