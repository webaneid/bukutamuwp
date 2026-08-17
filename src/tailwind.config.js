/** @type {import('tailwindcss').Config} */
module.exports = {
	prefix: 'bt-',
	// Path relatif terhadap direktori kerja saat build dijalankan (root plugin), BUKAN
	// relatif terhadap file config ini — lihat "build" script di package.json.
	content: [
		'templates/**/*.php',
		'includes/**/*.php',
		'build/js/**/*.js',
	],
	theme: {
		extend: {},
	},
	// Preflight (CSS reset global Tailwind) SENGAJA dimatikan: preflight tidak ikut ter-prefix
	// dan akan mereset elemen h1/p/button/dll di SELURUH halaman tema, melanggar prinsip
	// "tidak terkait dengan CSS bawaan WordPress/tema" di CLAUDE.md. Sebagai gantinya, reset
	// margin untuk elemen di DALAM markup plugin sendiri (h1/h2/p/dll) di-scope manual lewat
	// @layer base di src/input.css — lihat Lessons Learned #18.
	corePlugins: {
		preflight: false,
	},
	plugins: [],
};
