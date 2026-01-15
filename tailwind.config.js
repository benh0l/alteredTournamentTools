/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    // Scan all Twig templates
    './templates/**/*.html.twig',
    // Scan JavaScript files
    './assets/**/*.js',
    // Scan PHP files that might contain Tailwind classes
    './src/**/*.php',
  ],
  theme: {
    extend: {
      // Custom breakpoints matching NFR requirements
      screens: {
        'mobile': {'max': '767px'},      // < 768px
        'tablet': {'min': '768px', 'max': '1023px'},  // 768-1024px
        'desktop': {'min': '1024px'},    // > 1024px
        // Default Tailwind breakpoints also available:
        // sm: 640px, md: 768px, lg: 1024px, xl: 1280px, 2xl: 1536px
      },
      // Custom colors for tournament app - Orange/Amber theme
      colors: {
        'tournament': {
          'primary': '#f97316',    // Orange-500 (main)
          'secondary': '#ea580c',  // Orange-600 (darker)
          'accent': '#fbbf24',     // Amber-400 (warm accent)
          'highlight': '#f43f5e',  // Rose-500 (highlight)
          'success': '#10b981',    // Emerald-500 for completed
          'warning': '#f59e0b',    // Amber-500 for ongoing
          'danger': '#ef4444',     // Red-500 for disputes
        },
        // Timer color codes (FR43)
        'timer': {
          'green': '#10b981',     // 0-35 minutes
          'orange': '#f59e0b',    // 35-50 minutes
          'red': '#ef4444',       // 50+ minutes
        },
        // Gradient background colors
        'warm': {
          '50': '#fffbeb',        // Amber-50
          '100': '#fef3c7',       // Amber-100
          '200': '#fde68a',       // Amber-200
        }
      },
      // Touch-friendly button sizes (NFR32: >= 44px)
      minHeight: {
        'touch': '44px',
      },
      minWidth: {
        'touch': '44px',
      },
      // Spacing for generous touch targets
      spacing: {
        'touch': '44px',
      }
    },
  },
  plugins: [
    // Forms plugin for better form styling
    // require('@tailwindcss/forms'),
    // Typography plugin for prose content
    // require('@tailwindcss/typography'),
  ],
}
