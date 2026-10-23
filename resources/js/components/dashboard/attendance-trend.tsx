import { formatCalendarDate } from '@/lib/format';
import type { AttendanceTrendPoint } from '@/types';

const WIDTH = 600;
const HEIGHT = 240;
const LEFT = 40;
const RIGHT = 28;
const TOP = 10;
const BOTTOM = 28;

/**
 * The attendance line chart as plain SVG (no chart library). The y axis runs
 * from 80% (lower when a day falls below it) to 100%; days without any saved
 * register are gaps. Every point carries a `<title>` with its value, and the
 * same numbers are in the accessible label.
 */
export default function AttendanceTrend({
    points,
    today,
}: {
    points: AttendanceTrendPoint[];
    today: string;
}) {
    const values = points
        .map((point) => point.percent_tenths)
        .filter((value): value is number => value !== null);

    if (values.length === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                No registers saved in the last school days yet.
            </p>
        );
    }

    const lowest = Math.min(800, Math.floor(Math.min(...values) / 50) * 50);
    const ticks: number[] = [];

    for (let tick = lowest; tick <= 1000; tick += 50) {
        ticks.push(tick);
    }

    const plotWidth = WIDTH - LEFT - RIGHT;
    const plotHeight = HEIGHT - TOP - BOTTOM;
    const x = (index: number) =>
        LEFT +
        (points.length === 1
            ? plotWidth / 2
            : (index / (points.length - 1)) * plotWidth);
    const y = (tenths: number) =>
        TOP + ((1000 - tenths) / (1000 - lowest)) * plotHeight;
    const label = (date: string) =>
        date === today
            ? 'Today'
            : formatCalendarDate(date).replace(/ \d{4}$/, '');

    // Runs of consecutive days that have a value, drawn as separate lines.
    const runs: { index: number; value: number }[][] = [];
    let run: { index: number; value: number }[] = [];

    points.forEach((point, index) => {
        if (point.percent_tenths === null) {
            if (run.length > 0) {
                runs.push(run);
            }

            run = [];

            return;
        }

        run.push({ index, value: point.percent_tenths });
    });

    if (run.length > 0) {
        runs.push(run);
    }

    const summary = points
        .map(
            (point) =>
                `${label(point.date)}: ${point.percent_tenths === null ? 'not marked' : `${(point.percent_tenths / 10).toFixed(1)}%`}`,
        )
        .join(', ');

    return (
        <svg
            viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
            className="h-auto w-full"
            role="img"
            aria-label={`Attendance by day. ${summary}`}
        >
            {ticks.map((tick) => (
                <g key={tick}>
                    <line
                        x1={LEFT}
                        x2={WIDTH - RIGHT}
                        y1={y(tick)}
                        y2={y(tick)}
                        className="stroke-border"
                        strokeWidth={1}
                    />
                    <text
                        x={LEFT - 6}
                        y={y(tick) + 4}
                        textAnchor="end"
                        className="fill-muted-foreground text-[11px]"
                    >
                        {tick / 10}%
                    </text>
                </g>
            ))}

            {runs.map((segment) => {
                const line = segment
                    .map(
                        (p, i) =>
                            `${i === 0 ? 'M' : 'L'}${x(p.index)},${y(p.value)}`,
                    )
                    .join(' ');
                const first = segment[0];
                const last = segment[segment.length - 1];
                const area =
                    first && last
                        ? `${line} L${x(last.index)},${y(lowest)} L${x(first.index)},${y(lowest)} Z`
                        : '';

                return (
                    <g key={first?.index}>
                        <path d={area} className="fill-primary/10" />
                        <path
                            d={line}
                            fill="none"
                            className="stroke-primary"
                            strokeWidth={2}
                            strokeLinejoin="round"
                        />
                    </g>
                );
            })}

            {points.map((point, index) =>
                point.percent_tenths === null ? null : (
                    <circle
                        key={point.date}
                        cx={x(index)}
                        cy={y(point.percent_tenths)}
                        r={3.5}
                        className="fill-primary"
                    >
                        <title>
                            {`${label(point.date)}: ${(point.percent_tenths / 10).toFixed(1)}% present`}
                        </title>
                    </circle>
                ),
            )}

            {points.map((point, index) => (
                <text
                    key={point.date}
                    x={x(index)}
                    y={HEIGHT - 8}
                    textAnchor="middle"
                    className="fill-muted-foreground text-[11px]"
                >
                    {label(point.date)}
                </text>
            ))}
        </svg>
    );
}
