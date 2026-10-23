import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showStudent } from '@/routes/students';
import type { AdmissionCard, AdmissionStage } from '@/types/admissions';

export default function ApplicationCard({
    card,
    stages,
    canManage,
    onOpen,
    onMove,
}: {
    card: AdmissionCard;
    stages: AdmissionStage[];
    canManage: boolean;
    onOpen: () => void;
    onMove: (stage: string) => void;
}) {
    const position = stages.findIndex((stage) => stage.stage === card.stage);
    const previous = stages[position - 1];
    const next = stages[position + 1];
    const movable = canManage && !card.is_locked;

    return (
        <article
            draggable={movable}
            onDragStart={(event) => {
                event.dataTransfer.setData('text/plain', card.ulid);
                event.dataTransfer.effectAllowed = 'move';
            }}
            className={`rounded-lg border bg-card p-3 text-sm shadow-xs ${
                movable ? 'cursor-grab active:cursor-grabbing' : ''
            }`}
        >
            <div className="flex items-start justify-between gap-2">
                <span className="font-semibold">{card.name}</span>
                <span className="shrink-0 text-xs text-muted-foreground">
                    {card.days === 0 ? 'today' : `${card.days}d`}
                </span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
                {card.class_name} · {card.campus_name} · {card.source}
            </p>
            <p className="text-xs text-muted-foreground">{card.parent_name}</p>

            {(card.decision === 'accepted' && card.stage !== 'enrolled') ||
            card.decision === 'waitlisted' ||
            card.is_closed ||
            (card.missing_documents > 0 &&
                (card.stage === 'enquiry' || card.stage === 'application')) ? (
                <div className="mt-2 flex flex-wrap gap-1">
                    {card.decision === 'accepted' &&
                        card.stage !== 'enrolled' && <Badge>Accepted</Badge>}
                    {card.decision === 'waitlisted' && (
                        <Badge variant="secondary">Waitlisted</Badge>
                    )}
                    {card.is_closed && (
                        <Badge variant="destructive">Not admitted</Badge>
                    )}
                    {card.missing_documents > 0 &&
                        (card.stage === 'enquiry' ||
                            card.stage === 'application') && (
                            <Badge variant="outline">
                                {card.missing_documents} docs pending
                            </Badge>
                        )}
                </div>
            ) : null}

            <div className="mt-3 flex gap-1.5">
                {movable && previous !== undefined && (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-8"
                        aria-label={`Move ${card.name} back to ${previous.label}`}
                        onClick={() => onMove(previous.stage)}
                    >
                        <ArrowLeft />
                    </Button>
                )}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-8 grow"
                    aria-label={`Open ${card.name}'s application`}
                    onClick={onOpen}
                >
                    Open
                </Button>
                {movable && next !== undefined && (
                    <Button
                        type="button"
                        size="icon"
                        className="size-8"
                        aria-label={`Move ${card.name} to ${next.label}`}
                        onClick={() => onMove(next.stage)}
                    >
                        <ArrowRight />
                    </Button>
                )}
                {card.stage === 'enrolled' && card.student_ulid !== null && (
                    <Button variant="outline" size="sm" className="h-8" asChild>
                        <Link href={showStudent(card.student_ulid)}>
                            Profile
                        </Link>
                    </Button>
                )}
            </div>
        </article>
    );
}
