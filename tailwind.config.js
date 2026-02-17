/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#0f3759',
        secondary: '#00b2ca',
        accent: '#e8f4f6',
      },
    },
  },
  plugins: [],
  important: true, // Add !important to all Tailwind classes to override WordPress styles
}
