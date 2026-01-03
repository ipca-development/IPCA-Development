/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,jsx}"
  ],
  theme: {
    extend: {
      colors: {
        ipcaBlue: {
          light: "#2a5298",
          DEFAULT: "#1e3c72",
        }
      }
    },
  },
  plugins: [],
}