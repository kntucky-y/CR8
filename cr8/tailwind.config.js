/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        'bg-color': '#EBD3F8',
        'purple': '#7A1CAC',
        'light-purple': '#AD49E1',
        'dark-purple': '#4F0E72',
        'darkest-purple': '#310847',
        'pink-ish': '#F695FF',
      },
      fontFamily: {
        'lilita': ['"Lilita One"', 'cursive'],
        'lily': ['"Lily Script One"', 'cursive'],
        'outfit': ['"Outfit"', 'sans-serif'],
        'poetsen': ['"Poetsen One"', 'sans-serif'],
      },
      animation: {
        'float': 'float 6s ease-in-out infinite',
        'slide-up': 'slideUp 0.5s ease-out',
        'fade-in': 'fadeIn 0.5s ease-out',
      },
      keyframes: {
        float: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-20px)' }
        },
        slideUp: {
          '0%': { transform: 'translateY(20px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' }
        }
      }
    },
  },
  plugins: [],
}
