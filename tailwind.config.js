import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                tutor: {
                    navy: '#022448',
                    gold: '#feae2c',
                    surface: '#f7f9fb',
                    line: '#d8dadc',
                    ink: '#191c1e',
                    muted: '#43474e',
                },
            },
            fontFamily: {
                sans: ['Source Sans 3', ...defaultTheme.fontFamily.sans],
                display: ['Playfair Display', ...defaultTheme.fontFamily.serif],
            },
            boxShadow: {
                tutor: '0px 4px 20px rgba(30, 58, 95, 0.05)',
                'tutor-strong': '0px 10px 30px rgba(30, 58, 95, 0.12)',
            },
        },
    },

    plugins: [forms],
};
