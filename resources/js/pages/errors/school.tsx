import { Head } from '@inertiajs/react';

export default function School({
    title,
    message,
}: {
    title: string;
    message: string;
}) {
    return (
        <>
            <Head title={title} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-2 p-6 text-center">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <p className="max-w-md text-muted-foreground">{message}</p>
            </main>
        </>
    );
}
