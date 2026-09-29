import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** How long a success message stays before it starts fading. */
const VISIBLE_MS = 2500;

/** Must stay in step with the literal `duration-200` class below: Tailwind
 *  only sees class names written out in full, never ones built at runtime. */
const FADE_MS = 200;

type Phase = 'hidden' | 'shown' | 'leaving';

/**
 * The "Day saved." confirmation.
 *
 * It floats over the page rather than sitting in the flow: an inline banner
 * that appears and then removes itself pushes the calendar down and pulls it
 * back up again a moment later, so you get two jumps for one save.
 */
export default function FlashBanner() {
    const { flash } = usePage<SharedProps>().props;
    const [phase, setPhase] = useState<Phase>('hidden');

    useEffect(() => {
        if (!flash.success) {
            setPhase('hidden');

            return;
        }

        setPhase('shown');

        const fade = window.setTimeout(() => setPhase('leaving'), VISIBLE_MS);
        const remove = window.setTimeout(() => setPhase('hidden'), VISIBLE_MS + FADE_MS);

        return () => {
            window.clearTimeout(fade);
            window.clearTimeout(remove);
        };
        // Inertia hands back a fresh props object on every response, so saving
        // the same day twice restarts the timer even though the text is equal.
    }, [flash]);

    if (phase === 'hidden' || !flash.success) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className={cn(
                'fixed top-4 left-1/2 z-50 -translate-x-1/2 rounded-base border-2 border-border',
                'bg-success px-5 py-3 font-heading shadow-shadow transition-all duration-200',
                phase === 'leaving' && 'opacity-0 -translate-y-2',
            )}
        >
            {flash.success}
        </div>
    );
}
