import { Head, Link, router } from '@inertiajs/react';
import { FileCheck2, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AdmissionBoardView from '@/components/admissions/admission-board';
import ApplicationDialog from '@/components/admissions/application-dialog';
import {
    EnrolDialog,
    EnrolledDialog,
} from '@/components/admissions/enrol-dialog';
import EnquiryDialog from '@/components/admissions/enquiry-dialog';
import MissingDocumentsDialog from '@/components/admissions/missing-documents-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { index, move, settings } from '@/routes/admissions';
import type {
    AdmissionAbilities,
    AdmissionBoard,
    AdmissionCard,
    AdmissionDetail,
    AdmissionOptions,
    EnrolledApplicant,
} from '@/types/admissions';
import type { SectionOption } from '@/types/students';

type Props = {
    board: AdmissionBoard;
    abilities: AdmissionAbilities;
    options: AdmissionOptions;
    show_closed: boolean;
    selected: AdmissionDetail | null;
    enrol_sections: SectionOption[];
    today: string;
};

function Kpi({ label, value }: { label: string; value: string }) {
    return (
        <Card className="gap-1 px-4 py-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-2xl font-semibold">{value}</span>
        </Card>
    );
}

export default function AdmissionsIndex({
    board,
    abilities,
    options,
    show_closed,
    selected,
    enrol_sections,
    today,
}: Props) {
    const [adding, setAdding] = useState(false);
    const [enrolling, setEnrolling] = useState(false);
    const [enrolled, setEnrolled] = useState<EnrolledApplicant | null>(null);
    const [confirming, setConfirming] = useState<{
        card: AdmissionCard;
        stage: string;
    } | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash as
                | { enrolled?: EnrolledApplicant }
                | undefined;

            if (flash?.enrolled) {
                setEnrolled(flash.enrolled);
            }
        });
    }, []);

    const visit = (open: string | null, closed: boolean) =>
        router.get(
            index.url(),
            { open: open ?? undefined, closed: closed ? 1 : undefined },
            {
                only: ['selected', 'enrol_sections', 'show_closed'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );

    const open = (card: AdmissionCard) => visit(card.ulid, show_closed);

    const post = (card: AdmissionCard, stage: string, accept = false) =>
        router.post(
            move.url(card.ulid),
            { stage, accept_missing_documents: accept },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        errors.application ?? 'The application was not moved.',
                    ),
            },
        );

    const moveCard = (card: AdmissionCard, stage: string) => {
        if (stage === 'enrolled') {
            if (!abilities.enroll) {
                toast.info(
                    'Enrolment is done by the principal’s office. Your decision has been recorded.',
                );
            } else if (
                card.stage !== 'decision' ||
                card.decision !== 'accepted'
            ) {
                toast.warning(
                    `Record an “Accept” decision before enrolling ${card.name.split(' ')[0]}.`,
                );
            } else {
                startEnrol(card.ulid);
            }

            return;
        }

        if (stage === 'verified' && card.missing_documents > 0) {
            setConfirming({ card, stage });

            return;
        }

        post(card, stage);
    };

    const startEnrol = (ulid: string) => {
        if (selected?.ulid !== ulid) {
            visit(ulid, show_closed);
        }

        setEnrolling(true);
    };

    const detailCard = (detail: AdmissionDetail): AdmissionCard =>
        board.cards.find((card) => card.ulid === detail.ulid) ?? {
            ulid: detail.ulid,
            name: detail.name,
            class_name: detail.class_name,
            campus_name: detail.campus_name,
            source: detail.source_label,
            parent_name: detail.parent_name,
            stage: detail.stage,
            decision: detail.decision,
            is_closed: detail.is_closed,
            is_locked: detail.is_locked,
            days: 0,
            missing_documents: detail.missing_documents,
            student_ulid: detail.student_ulid,
        };

    return (
        <>
            <Head title="Admissions" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Admissions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Drag cards between stages, or use the arrow buttons.
                            Enrolling creates the student and the guardian.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex cursor-pointer items-center gap-2 text-sm">
                            <Checkbox
                                checked={show_closed}
                                onCheckedChange={(checked) =>
                                    router.get(
                                        index.url(),
                                        {
                                            closed:
                                                checked === true
                                                    ? 1
                                                    : undefined,
                                        },
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            replace: true,
                                        },
                                    )
                                }
                            />
                            Show rejected
                        </label>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={settings()}>
                                <FileCheck2 /> Required documents
                            </Link>
                        </Button>
                        {abilities.manage && (
                            <Button size="sm" onClick={() => setAdding(true)}>
                                <Plus /> Add enquiry
                            </Button>
                        )}
                    </div>
                </div>

                <section
                    aria-label="Pipeline totals"
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Kpi
                        label="Open applications"
                        value={String(board.summary.open_applications)}
                    />
                    <Kpi
                        label="New this week"
                        value={String(board.summary.new_this_week)}
                    />
                    <Kpi
                        label="Enrolled this cycle"
                        value={String(board.summary.enrolled)}
                    />
                    <Kpi
                        label="Enquiry to enrolment"
                        value={`${board.summary.conversion_percent}%`}
                    />
                </section>

                <AdmissionBoardView
                    board={board}
                    canManage={abilities.manage}
                    onOpen={open}
                    onMove={moveCard}
                />
            </div>

            {adding && (
                <EnquiryDialog
                    options={options}
                    today={today}
                    onClose={() => setAdding(false)}
                />
            )}

            {selected !== null && !enrolling && (
                <ApplicationDialog
                    application={selected}
                    options={options}
                    abilities={abilities}
                    onClose={() => visit(null, show_closed)}
                    onMove={(stage) => moveCard(detailCard(selected), stage)}
                    onEnrol={() => startEnrol(selected.ulid)}
                />
            )}

            {selected !== null && enrolling && (
                <EnrolDialog
                    application={selected}
                    sections={enrol_sections}
                    onClose={() => {
                        setEnrolling(false);
                        visit(null, show_closed);
                    }}
                />
            )}

            {confirming !== null && (
                <MissingDocumentsDialog
                    name={confirming.card.name.split(' ')[0]}
                    count={confirming.card.missing_documents}
                    onCancel={() => setConfirming(null)}
                    onConfirm={() => {
                        post(confirming.card, confirming.stage, true);
                        setConfirming(null);
                    }}
                />
            )}

            {enrolled !== null && (
                <EnrolledDialog
                    enrolled={enrolled}
                    onClose={() => setEnrolled(null)}
                />
            )}
        </>
    );
}

AdmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
