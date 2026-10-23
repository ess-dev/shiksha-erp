import { useState } from 'react';
import ApplicationCard from '@/components/admissions/application-card';
import type { AdmissionBoard, AdmissionCard } from '@/types/admissions';

export default function AdmissionBoardView({
    board,
    canManage,
    onOpen,
    onMove,
}: {
    board: AdmissionBoard;
    canManage: boolean;
    onOpen: (card: AdmissionCard) => void;
    onMove: (card: AdmissionCard, stage: string) => void;
}) {
    const [dragOver, setDragOver] = useState<string | null>(null);

    return (
        <section
            aria-label="Admission pipeline"
            className="flex gap-4 overflow-x-auto pb-3"
        >
            {board.stages.map((stage) => {
                const cards = board.cards.filter(
                    (card) => card.stage === stage.stage,
                );

                return (
                    <section
                        key={stage.stage}
                        aria-labelledby={`stage-${stage.stage}`}
                        onDragOver={(event) => {
                            if (canManage) {
                                event.preventDefault();
                                setDragOver(stage.stage);
                            }
                        }}
                        onDragLeave={() => setDragOver(null)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setDragOver(null);

                            const card = board.cards.find(
                                (candidate) =>
                                    candidate.ulid ===
                                    event.dataTransfer.getData('text/plain'),
                            );

                            if (
                                card !== undefined &&
                                card.stage !== stage.stage
                            ) {
                                onMove(card, stage.stage);
                            }
                        }}
                        className={`flex w-64 shrink-0 flex-col gap-2 rounded-xl p-3 sm:w-72 ${
                            dragOver === stage.stage
                                ? 'bg-accent'
                                : 'bg-muted/50'
                        }`}
                    >
                        <div className="flex items-center justify-between px-1">
                            <h2
                                id={`stage-${stage.stage}`}
                                className="text-sm font-semibold"
                            >
                                {stage.label}
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {stage.count}
                            </span>
                        </div>
                        {cards.length === 0 ? (
                            <p className="py-3 text-center text-xs text-muted-foreground">
                                {canManage ? 'Drop here' : 'Nothing here'}
                            </p>
                        ) : (
                            cards.map((card) => (
                                <ApplicationCard
                                    key={card.ulid}
                                    card={card}
                                    stages={board.stages}
                                    canManage={canManage}
                                    onOpen={() => onOpen(card)}
                                    onMove={(to) => onMove(card, to)}
                                />
                            ))
                        )}
                    </section>
                );
            })}
        </section>
    );
}
