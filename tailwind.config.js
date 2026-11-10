/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./vista.php",
    "./assets/js/**/*.js"
  ],
  safelist: [
    { pattern: /bg-(slate|emerald|rose|amber|indigo|purple|cyan|pink|blue)-(500|600|700|800|900|950)(\/\d+)?/ },
    { pattern: /border-(slate|emerald|rose|amber|indigo|purple|cyan|pink|blue)-(200|300|400|500|600|700|800)(\/\d+)?/ },
    { pattern: /text-(slate|emerald|rose|amber|indigo|purple|cyan|pink|blue)-(100|200|300|400|500|600|700|800|900)/ },
    { pattern: /shadow-(emerald|rose|amber|indigo|purple|cyan|slate)-(950)(\/\d+)?/ }
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
