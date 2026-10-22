import { Head } from '@inertiajs/react';
import { CalendarX, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    status: 'suspended' | 'expired';
    title: string;
    message: string;
    reason: string | null;
    contact: { label: string; href: string } | null;
};

/**
 * Shown on a school's own address while its subscription is suspended or has
 * expired. The texts come from the server (lang/en/school.php).
 */
export default function Subscription({
    status,
    title,
    message,
    reason,
    contact,
}: Props) {
    const Icon = status === 'expired' ? CalendarX : Lock;

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-3 p-6 text-center">
                <section
                    role="alert"
                    className="grid max-w-md justify-items-center gap-3"
                >
                    <span className="grid size-14 place-items-center rounded-full bg-red-100 text-red-700">
                        <Icon className="size-7" aria-hidden="true" />
                    </span>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">{message}</p>
                    {reason && (
                        <p className="rounded-lg bg-red-100 px-3 py-1.5 text-sm font-medium text-red-800">
                            {reason}
                        </p>
                    )}
                    {contact && (
                        <Button asChild className="mt-1">
                            <a href={contact.href}>{contact.label}</a>
                        </Button>
                    )}
                </section>
            </main>
        </>
    );
}
