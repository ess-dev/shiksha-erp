type Slice = { label: string; value: number; color: string };

const RADIUS = 100 / (2 * Math.PI);
const GAP = 0.4;

/**
 * A plain SVG doughnut (about 64% cut-out) so the dashboard needs no chart
 * library. The legend below carries the same numbers for everyone.
 */
export default function StatusDoughnut({
    slices,
    label,
}: {
    slices: Slice[];
    label: string;
}) {
    const total = slices.reduce((sum, slice) => sum + slice.value, 0);
    let offset = 0;

    return (
        <div className="flex flex-col items-center gap-4">
            <svg
                viewBox="0 0 44 44"
                className="size-[212px]"
                role="img"
                aria-label={label}
            >
                <g transform="rotate(-90 22 22)">
                    {total === 0 ? (
                        <circle
                            cx="22"
                            cy="22"
                            r={RADIUS}
                            fill="none"
                            stroke="#e2e8f0"
                            strokeWidth="7.3"
                        />
                    ) : (
                        slices
                            .filter((slice) => slice.value > 0)
                            .map((slice) => {
                                const length = (slice.value / total) * 100;
                                const start = offset;
                                offset += length;

                                return (
                                    <circle
                                        key={slice.label}
                                        cx="22"
                                        cy="22"
                                        r={RADIUS}
                                        fill="none"
                                        stroke={slice.color}
                                        strokeWidth="7.3"
                                        strokeDasharray={`${Math.max(length - GAP, 0.1)} ${100 - length + GAP}`}
                                        strokeDashoffset={-start}
                                    />
                                );
                            })
                    )}
                </g>
            </svg>

            <ul className="flex flex-wrap justify-center gap-x-4 gap-y-1.5 text-[13px] text-slate-500">
                {slices.map((slice) => (
                    <li key={slice.label} className="flex items-center gap-1.5">
                        <span
                            aria-hidden="true"
                            className="h-2.5 w-9 rounded-[2px]"
                            style={{ backgroundColor: slice.color }}
                        />
                        {slice.label}
                        <span className="sr-only">: {slice.value}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
