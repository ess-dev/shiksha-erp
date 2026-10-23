import PlatformBrand from '@/components/platform/platform-brand';

export default function PlatformAuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-6">
                    <div className="mx-auto flex w-fit items-center gap-2.5">
                        <PlatformBrand />
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
                        <div className="mb-6 space-y-1.5 text-center">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
