import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue, route as ziggyRoute } from '../../vendor/tightenco/ziggy';

const ziggyConfig = (() => {
    const element = document.getElementById('ziggy-routes-json');
    const config = element ? JSON.parse(element.textContent || '{}') : (window.Ziggy || {});

    if (window.location.protocol === 'https:' && config?.url?.startsWith('http://')) {
        config.url = window.location.origin;
    }

    config.location = new URL(window.location.href);

    return config;
})();

window.Ziggy = ziggyConfig;
window.route = (name, params, absolute, config = ziggyConfig) => {
    config.location = new URL(window.location.href);

    return ziggyRoute(name, params, absolute, config);
};

const configuredAppName = import.meta.env.VITE_APP_NAME;
const appName = configuredAppName && !configuredAppName.includes('$') ? configuredAppName : 'TutorLink';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig)
            .mount(el);
    },
    progress: {
        color: '#feae2c',
    },
});
