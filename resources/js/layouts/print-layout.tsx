import type { ReactNode } from 'react';

/**
 * A bare page for documents meant to be printed (the transfer certificate):
 * no sidebar or header, so the browser's Print or Save as PDF gets only the
 * document.
 */
export default function PrintLayout({ children }: { children: ReactNode }) {
    return <div className="min-h-screen bg-background">{children}</div>;
}
