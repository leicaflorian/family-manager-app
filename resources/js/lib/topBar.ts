/**
 * What the top bar of the tablet and the computer shares with the bar of the menu of the tablet, which takes its
 * place while the menu is open.
 */
import { cn } from '@/lib/utils';

/** The square buttons of the bars: Back, the menu and the bell, 44 px with a thin border. */
export const squareButton = cn(
    'relative flex size-11 shrink-0 items-center justify-center rounded-md border border-edge-soft bg-card text-ink',
);
