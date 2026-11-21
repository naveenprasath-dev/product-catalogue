import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';

const appName = 'Admin · Product Catalogue';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => (name.startsWith('auth/') ? AuthLayout : AppLayout),
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'var(--primary)',
    },
});
