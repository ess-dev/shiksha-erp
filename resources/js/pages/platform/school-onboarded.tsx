import { Head, Link } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatCalendarDate } from '@/lib/format';
import { index, onboard, show } from '@/routes/platform/schools';
import type { OnboardedSchool } from '@/types';

type Props = {
    onboarded: OnboardedSchool;
    roles: { key: string; label: string }[];
    link_minutes: number;
};

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard blocked: the link is still selectable in the field.
        }
    };

    return (
        <Button type="button" variant="outline" size="sm" onClick={copy}>
            {copied ? (
                <Check aria-hidden="true" />
            ) : (
                <Copy aria-hidden="true" />
            )}
            {copied ? 'Copied' : 'Copy link'}
        </Button>
    );
}

export default function PlatformSchoolOnboarded({
    onboarded,
    roles,
    link_minutes: linkMinutes,
}: Props) {
    const roleLabel = (key: string) =>
        roles.find((role) => role.key === key)?.label ?? key;

    return (
        <>
            <Head title={`${onboarded.name} is set up`} />

            <section className="rounded-xl border border-border bg-card px-5 py-10 text-center shadow-xs">
                <div className="mx-auto grid size-14 place-items-center rounded-full bg-green-100 text-green-700">
                    <Check className="size-7" aria-hidden="true" />
                </div>
                <h2 className="mt-3 text-lg font-semibold">
                    {onboarded.name} is set up
                </h2>
                <p className="mx-auto mt-1 max-w-xl text-sm text-slate-600">
                    {onboarded.status === 'trial' && onboarded.trial_ends_on
                        ? `Trial until ${formatCalendarDate(onboarded.trial_ends_on)}`
                        : 'Active subscription'}{' '}
                    on the {onboarded.plan_name} plan · {onboarded.campuses}{' '}
                    {onboarded.campuses === 1 ? 'campus' : 'campuses'} ·{' '}
                    {onboarded.sections}{' '}
                    {onboarded.sections === 1 ? 'section' : 'sections'} · no
                    students yet. Signs in at{' '}
                    <strong className="font-semibold">
                        {onboarded.address}
                    </strong>
                    .
                </p>
                <div className="mt-5 flex flex-wrap justify-center gap-2">
                    <Button asChild>
                        <Link href={show(onboarded.ulid)}>Open school</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index()}>All schools</Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={onboard()}>Onboard another</Link>
                    </Button>
                </div>
            </section>

            <section
                className="mt-4 rounded-xl border border-border bg-card shadow-xs"
                aria-labelledby="invites-title"
            >
                <div className="px-5 pt-4">
                    <h3
                        id="invites-title"
                        className="text-[15px] font-semibold"
                    >
                        Hand these set-password links to the staff
                    </h3>
                    <p className="mt-0.5 text-[13px] text-muted-foreground">
                        No email is sent yet. Each link is shown only here, only
                        now, and works for {linkMinutes} minutes; after that the
                        person can use “Forgot password” on the school’s sign-in
                        page.
                    </p>
                </div>
                <ul className="divide-y divide-border px-5 pt-2 pb-3">
                    {onboarded.invites.map((invite) => (
                        <li key={invite.email} className="grid gap-2 py-3">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <span className="font-semibold">
                                        {invite.name}
                                    </span>{' '}
                                    <span className="text-sm text-muted-foreground">
                                        · {roleLabel(invite.role)} ·{' '}
                                        {invite.email}
                                    </span>
                                </div>
                                {invite.link && (
                                    <CopyButton text={invite.link} />
                                )}
                            </div>
                            {invite.link ? (
                                <input
                                    readOnly
                                    aria-label={`Set-password link for ${invite.name}`}
                                    value={invite.link}
                                    onFocus={(event) =>
                                        event.currentTarget.select()
                                    }
                                    className="h-9 w-full rounded-md border border-input bg-muted/40 px-3 font-mono text-xs"
                                />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Already has a Shiksha account; they sign in
                                    with their existing password.
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}

PlatformSchoolOnboarded.layout = {
    title: 'Onboard a school',
};
