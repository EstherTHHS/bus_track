import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = 'Bus Track';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx'),
        );

        return page.default;
    },
    setup({ el, App, props }) {
        if (el) {
            createRoot(el).render(<App {...props} />);
        }
    },
    progress: {
        // Inertia injects this inline, so it cannot read a CSS token.
        // Mirrors --foreground in app.css by hand.
        color: '#3D2B36',
    },
});
