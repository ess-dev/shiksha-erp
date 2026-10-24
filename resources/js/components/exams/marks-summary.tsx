import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { GradeBand } from '@/types';
import type { ParsedMark } from './grading';
import { formatHalves, gradeFor, wholePercent } from './grading';

function Stat({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <div className="rounded-lg bg-muted/50 px-3 py-2">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'text-lg font-semibold tabular-nums',
                    danger && 'text-destructive',
                )}
            >
                {value}
            </p>
        </div>
    );
}

/**
 * "Class summary" from the prototype: how many are entered, the class average,
 * how many are below the pass mark, and how the grades spread.
 */
export default function MarksSummary({
    subtitle,
    parsed,
    maxMarks,
    scale,
    passPercent,
}: {
    subtitle: string;
    parsed: ParsedMark[];
    maxMarks: number;
    scale: GradeBand[];
    passPercent: number;
}) {
    const outOf = 2 * maxMarks;
    const scores = parsed.flatMap((mark) =>
        mark.kind === 'marks' ? [mark.halves] : [],
    );
    const absent = parsed.filter((mark) => mark.kind === 'absent').length;
    const entered = scores.length + absent;
    const total = scores.reduce((sum, value) => sum + value, 0);
    const average =
        scores.length === 0 ? null : wholePercent(total, outOf * scores.length);
    const below = scores.filter(
        (value) => 100 * value < passPercent * outOf,
    ).length;

    const spread = scale.map((band) => ({
        grade: band.grade,
        count: scores.filter(
            (value) => gradeFor(scale, value, outOf)?.grade === band.grade,
        ).length,
    }));
    const top = Math.max(1, ...spread.map((item) => item.count));

    return (
        <Card className="gap-3 p-4">
            <div>
                <h2 className="font-semibold">Class summary</h2>
                <p className="text-sm text-muted-foreground">{subtitle}</p>
            </div>
            <div className="grid grid-cols-3 gap-2">
                <Stat label="Entered" value={`${entered}/${parsed.length}`} />
                <Stat
                    label="Average"
                    value={average === null ? '—' : `${average}%`}
                />
                <Stat
                    label={`Below ${passPercent}%`}
                    value={String(below)}
                    danger={below > 0}
                />
            </div>
            {scores.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    Highest {formatHalves(Math.max(...scores))} · lowest{' '}
                    {formatHalves(Math.min(...scores))}
                    {absent > 0 && ` · ${absent} absent`}
                </p>
            )}
            <div
                role="img"
                aria-label={`Grade spread: ${spread.map((item) => `${item.grade} ${item.count}`).join(', ')}`}
                className="flex h-24 items-end gap-1.5 pt-2"
            >
                {spread.map((item) => (
                    <div
                        key={item.grade}
                        className="flex flex-1 flex-col items-center gap-1 text-xs text-muted-foreground"
                    >
                        <span className="h-4 tabular-nums">
                            {item.count > 0 ? item.count : ''}
                        </span>
                        <span
                            className="w-full rounded-t bg-primary/70"
                            style={{
                                height: `${Math.max(2, (item.count / top) * 48)}px`,
                                opacity: item.count > 0 ? 1 : 0.25,
                            }}
                        />
                        {item.grade}
                    </div>
                ))}
            </div>
        </Card>
    );
}
