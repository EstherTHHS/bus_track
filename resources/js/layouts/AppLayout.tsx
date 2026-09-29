import FlashBanner from '@/components/FlashBanner';
import { Button } from '@/components/ui/button';
import type { SharedProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { ReactNode } from 'react';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen">
            <header className="border-b-2 border-border bg-secondary-background">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 2xl:max-w-[1600px]">
                    <Link href="/dashboard" className="text-xl font-heading tracking-tight uppercase">
                        Bus Track
                    </Link>

                    <div className="flex items-center gap-3">
                        {auth.user && (
                            <span className="hidden text-sm sm:inline">{auth.user.name}</span>
                        )}
                        <Button
                            variant="neutral"
                            size="sm"
                            render={<Link href="/logout" method="post" as="button" />}
                        >
                            <LogOut />
                            Log out
                        </Button>
                    </div>
                </div>
            </header>

            <FlashBanner />

            <main className="mx-auto max-w-7xl px-4 py-6 2xl:max-w-[1600px]">
                {/* Errors stay put: they say an action did not happen, so they
                    should not vanish before they are read. */}
                {flash.error && (
                    <p className="mb-4 rounded-base border-2 border-border bg-danger px-4 py-3 font-heading shadow-shadow">
                        {flash.error}
                    </p>
                )}

                {children}
            </main>
        </div>
    );
}
