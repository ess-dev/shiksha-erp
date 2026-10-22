import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    // The prototype's toast: deep indigo with white text.
                    '--normal-bg': '#1e1b4b',
                    '--normal-text': '#ffffff',
                    '--normal-border': '#1e1b4b',
                    '--success-bg': '#1e1b4b',
                    '--success-text': '#ffffff',
                    '--success-border': '#1e1b4b',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
