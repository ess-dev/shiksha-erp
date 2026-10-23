import { DashboardCard } from '@/components/dashboard/dashboard-card';

/** The prototype's sample figures, in lakh rupees. Not real data. */
const SAMPLE = [
    { term: 'Term 1', billed: 24.5, collected: 24.5 },
    { term: 'Term 2', billed: 24.5, collected: 21 },
    { term: 'Term 3', billed: 24.5, collected: 9.5 },
];
const TICKS = [0, 5, 10, 15, 20, 25];

/**
 * "Fee collection by term" with sample bars until the Fees module exists.
 */
export default function FeeCollectionSample() {
    const height = 150;
    const y = (lakh: number) => 10 + height - (lakh / 25) * height;

    return (
        <DashboardCard
            title="Fee collection by term"
            subtitle="Billed vs collected, this academic year"
            comingSoon
        >
            <svg
                viewBox="0 0 300 190"
                className="mx-auto h-auto max-h-56 w-full"
                aria-hidden="true"
            >
                {TICKS.map((tick) => (
                    <g key={tick}>
                        <line
                            x1={36}
                            x2={295}
                            y1={y(tick)}
                            y2={y(tick)}
                            className="stroke-border"
                        />
                        <text
                            x={30}
                            y={y(tick) + 4}
                            textAnchor="end"
                            className="fill-muted-foreground text-[10px]"
                        >
                            ₹{tick} L
                        </text>
                    </g>
                ))}
                {SAMPLE.map((item, index) => {
                    const left = 50 + index * 82;

                    return (
                        <g key={item.term}>
                            <rect
                                x={left}
                                y={y(item.billed)}
                                width={26}
                                height={y(0) - y(item.billed)}
                                rx={4}
                                className="fill-primary/30"
                            />
                            <rect
                                x={left + 30}
                                y={y(item.collected)}
                                width={26}
                                height={y(0) - y(item.collected)}
                                rx={4}
                                className="fill-primary"
                            />
                            <text
                                x={left + 28}
                                y={182}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[11px]"
                            >
                                {item.term}
                            </text>
                        </g>
                    );
                })}
            </svg>
            <div className="mt-2 flex gap-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-primary/30" />{' '}
                    Billed
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="size-2.5 rounded-sm bg-primary" />{' '}
                    Collected
                </span>
            </div>
        </DashboardCard>
    );
}
