/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0f9f4',
          100: '#dcf1e8',
          200: '#b9e3d1',
          300: '#8aceb2',
          400: '#58b38e',
          500: '#2d8a65',
          600: '#256f51',
          700: '#1c5540',
          800: '#174434',
          900: '#13382b',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
