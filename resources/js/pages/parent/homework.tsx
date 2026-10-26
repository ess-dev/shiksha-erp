import { Head, router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { HOMEWORK_ACCEPT, dueLabel } from '@/components/homework/due-label';
import { HomeworkFileLinks } from '@/components/homework/homework-files';
import { formatCalendarDay } from '@/lib/format';
import { resizeImage } from '@/lib/resize-image';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/parent/homework';
import type { ParentHomeworkItem, ParentHomeworkState } from '@/types';
import type { ParentShell } from '@/types/parent';

type Props = {
    shell: ParentShell;
    items: ParentHomeworkItem[];
    child_ulid: string | null;
    child_first_name: string | null;
    section_label: string | null;
    session_name: string;
    is_current_session: boolean;
    max_files: number;
    max_file_mb: number;
};

type Tab = ParentHomeworkState | 'all';

const TABS: { value: Tab; label: string }[] = [
    { value: 'due', label: 'To do' },
    { value: 'done', label: 'Submitted' },
    { value: 'missed', label: 'Missed' },
    { value: 'all', label: 'All' },
];

function Pill({
    tone,
    children,
}: {
    tone: 'green' | 'amber' | 'red' | 'grey';
    children: string;
}) {
    const tones = {
        green: 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
        red: 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300',
        grey: 'bg-muted text-muted-foreground',
    };

    return (
        <span
            className={cn(
                'inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}

function StatePill({ item }: { item: ParentHomeworkItem }) {
    if (item.state === 'done') {
        return item.is_late ? (
            <Pill tone="amber">Submitted late</Pill>
        ) : (
            <Pill tone="green">Submitted</Pill>
        );
    }

    if (item.state === 'missed') {
        return <Pill tone="red">Missed</Pill>;
    }

    return (
        <Pill tone={item.days_left === 0 ? 'amber' : 'grey'}>
            {dueLabel(item.days_left, item.due_on)}
        </Pill>
    );
}

export default function ParentHomework({
    items,
    child_ulid: childUlid,
    child_first_name: childName,
    section_label: section,
    session_name: sessionName,
    is_current_session: isCurrent,
    max_files: maxFiles,
    max_file_mb: maxFileMb,
}: Props) {
    const [tab, setTab] = useState<Tab>('due');
    const [uploading, setUploading] = useState<string | null>(null);
    const [problem, setProblem] = useState<{
        ulid: string;
        message: string;
    } | null>(null);

    if (childName === null || childUlid === null) {
        return (
            <>
                <Head title="Homework" />
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        No homework for {sessionName}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Homework is shown for the sessions your children were
                        enrolled in.
                    </p>
                </div>
            </>
        );
    }

    const count = (state: ParentHomeworkState) =>
        items.filter((item) => item.state === state).length;
    const shown = items
        .filter((item) => tab === 'all' || item.state === tab)
        // To do: soonest due first. The other tabs: latest first.
        .sort((a, b) =>
            tab === 'due'
                ? a.due_on.localeCompare(b.due_on)
                : b.due_on.localeCompare(a.due_on),
        );

    const upload = async (item: ParentHomeworkItem, list: FileList | null) => {
        if (list === null || list.length === 0) {
            return;
        }

        const files = await Promise.all(Array.from(list).map(resizeImage));

        if (files.length > maxFiles) {
            setProblem({
                ulid: item.ulid,
                message: `You can upload up to ${maxFiles} files.`,
            });

            return;
        }

        const tooBig = files.find((f) => f.size > maxFileMb * 1024 * 1024);

        if (tooBig !== undefined) {
            setProblem({
                ulid: item.ulid,
                message: `${tooBig.name} is larger than ${maxFileMb} MB.`,
            });

            return;
        }

        router.post(
            submit.url(item.ulid),
            { child: childUlid, files },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => {
                    setUploading(item.ulid);
                    setProblem(null);
                },
                onFinish: () => setUploading(null),
                onError: (errors) =>
                    setProblem({
                        ulid: item.ulid,
                        message:
                            errors.homework ??
                            Object.values(errors)[0] ??
                            'The upload did not work. Please try again.',
                    }),
            },
        );
    };

    return (
        <>
            <Head title="Homework" />

            <p className="mb-4 text-sm text-muted-foreground">
                {isCurrent
                    ? `Homework for ${childName} in ${section}.`
                    : `Homework for ${childName} in ${section} (${sessionName}). Past sessions can be viewed only.`}
            </p>

            <div
                className="mb-4 flex flex-wrap gap-2"
                role="group"
                aria-label="Show"
            >
                {TABS.map((t) => {
                    const total = t.value === 'all' ? 0 : count(t.value);

                    return (
                        <button
                            key={t.value}
                            type="button"
                            aria-pressed={tab === t.value}
                            onClick={() => setTab(t.value)}
                            className={cn(
                                'rounded-full border px-4 py-1.5 text-sm font-medium',
                                tab === t.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-card hover:bg-muted',
                            )}
                        >
                            {t.label}
                            {total > 0 && ` · ${total}`}
                        </button>
                    );
                })}
            </div>

            {shown.length === 0 ? (
                <div className="rounded-xl border bg-card p-8 text-center">
                    <h2 className="text-base font-semibold">
                        {tab === 'due'
                            ? 'Nothing due right now'
                            : 'Nothing here yet'}
                    </h2>
                </div>
            ) : (
                <div className="grid gap-3">
                    {shown.map((item) => (
                        <HomeworkCard
                            key={item.ulid}
                            item={item}
                            uploading={uploading === item.ulid}
                            problem={
                                problem?.ulid === item.ulid
                                    ? problem.message
                                    : undefined
                            }
                            maxFiles={maxFiles}
                            maxFileMb={maxFileMb}
                            onPick={(list) => void upload(item, list)}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

function HomeworkCard({
    item,
    uploading,
    problem,
    maxFiles,
    maxFileMb,
    onPick,
}: {
    item: ParentHomeworkItem;
    uploading: boolean;
    problem?: string;
    maxFiles: number;
    maxFileMb: number;
    onPick: (files: FileList | null) => void;
}) {
    const input = useRef<HTMLInputElement>(null);
    const titleId = `hw-${item.ulid}`;

    return (
        <article
            aria-labelledby={titleId}
            className="grid gap-2.5 rounded-xl border bg-card p-4"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">
                        {item.subject} · {item.teacher_name}
                    </p>
                    <h2 id={titleId} className="font-semibold">
                        {item.title}
                    </h2>
                </div>
                <StatePill item={item} />
            </div>

            {item.instructions !== null && (
                <p className="text-sm">{item.instructions}</p>
            )}

            <HomeworkFileLinks files={item.attachments} />

            <p className="text-sm text-muted-foreground">
                Set {formatCalendarDay(item.assigned_on).replace(/^\w+, /, '')}{' '}
                · due {formatCalendarDay(item.due_on)} ·{' '}
                {item.submission_type === 'upload'
                    ? 'upload in app'
                    : 'in notebook'}
            </p>

            {item.my_files.length > 0 && (
                <div className="grid gap-1.5">
                    <p className="text-sm text-muted-foreground">Your upload</p>
                    <HomeworkFileLinks files={item.my_files} />
                </div>
            )}

            {item.feedback !== null && (
                <p className="rounded-lg bg-muted/60 px-3 py-2.5 text-sm">
                    <strong>Teacher’s feedback:</strong> {item.feedback}
                </p>
            )}

            {item.can_upload && (
                <div className="grid gap-1">
                    <button
                        type="button"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                        className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
                    >
                        <Upload className="size-4" aria-hidden />
                        {uploading
                            ? 'Uploading…'
                            : item.state === 'missed'
                              ? 'Upload late'
                              : 'Upload work (photos or documents)'}
                    </button>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept={HOMEWORK_ACCEPT}
                        className="sr-only"
                        tabIndex={-1}
                        aria-label={`Upload work for ${item.title}`}
                        onChange={(event) => {
                            onPick(event.target.files);
                            event.target.value = '';
                        }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Up to {maxFiles} files, {maxFileMb} MB each. Photos are
                        resized to save data.
                    </p>
                    {problem !== undefined && (
                        <p role="alert" className="text-sm text-destructive">
                            {problem}
                        </p>
                    )}
                </div>
            )}

            {item.state !== 'done' && item.submission_type === 'notebook' && (
                <p className="text-sm text-muted-foreground">
                    Do this in the notebook; the teacher checks it in class.
                </p>
            )}
        </article>
    );
}
