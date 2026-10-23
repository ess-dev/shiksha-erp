import { GraduationCap } from 'lucide-react';
import { usePage } from '@inertiajs/react';

export default function ParentAuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { school_name: schoolName } = usePage<{ school_name?: string }>()
        .props;

    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-4 md:p-10">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-6">
                    <div className="mx-auto flex w-fit items-center gap-2.5">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                            <GraduationCap className="size-5" aria-hidden />
                        </span>
                        <div>
                            <p className="text-sm leading-tight font-semibold">
                                {schoolName === undefined || schoolName === ''
                                    ? 'Shiksha ERP'
                                    : schoolName}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Parent portal
                            </p>
                        </div>
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
