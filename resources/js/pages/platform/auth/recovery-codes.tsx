import { Head, Link } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/platform';

type Props = {
    recoveryCodes: string[];
};

export default function PlatformRecoveryCodes({ recoveryCodes }: Props) {
    const [copied, copy] = useClipboard();
    const all = recoveryCodes.join('\n');

    return (
        <>
            <Head title="Recovery codes" />

            <div className="grid gap-5">
                <ul className="grid gap-1.5 rounded-lg border border-border bg-muted p-3 text-center font-mono text-sm">
                    {recoveryCodes.map((recoveryCode) => (
                        <li key={recoveryCode}>{recoveryCode}</li>
                    ))}
                </ul>

                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy(all)}
                >
                    {copied === all ? <Check /> : <Copy />}
                    {copied === all ? 'Copied' : 'Copy all codes'}
                </Button>

                <Button asChild>
                    <Link href={dashboard()} data-test="platform-continue">
                        I have saved them, continue
                    </Link>
                </Button>
            </div>
        </>
    );
}

PlatformRecoveryCodes.layout = {
    title: 'Save your recovery codes',
    description:
        'Each code works once if you lose your phone. Store them somewhere safe, such as a password manager.',
};
