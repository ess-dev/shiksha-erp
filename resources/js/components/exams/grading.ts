import type { GradeBand } from '@/types';

export type ParsedMark =
    | { kind: 'blank' }
    | { kind: 'absent' }
    | { kind: 'marks'; halves: number }
    | { kind: 'invalid' };

/**
 * Reads a marks box the way the server does (SaveMarks): blank, "AB", or 0 to
 * the maximum in steps of a half. Marks are counted in whole half-marks so
 * the live grade never depends on fractional arithmetic.
 */
export function parseMark(text: string, maxMarks: number): ParsedMark {
    const value = text.trim().toUpperCase();

    if (value === '') {
        return { kind: 'blank' };
    }

    if (value === 'AB') {
        return { kind: 'absent' };
    }

    const match = /^(\d{1,3})(?:\.(\d))?$/.exec(value);

    if (match === null) {
        return { kind: 'invalid' };
    }

    const decimal = match[2] ?? '0';

    if (decimal !== '0' && decimal !== '5') {
        return { kind: 'invalid' };
    }

    const halves = 2 * Number(match[1]) + (decimal === '5' ? 1 : 0);

    return halves > 2 * maxMarks
        ? { kind: 'invalid' }
        : { kind: 'marks', halves };
}

/** The highest grade whose minimum the score reaches (same rule as GradeScale on the server). */
export function gradeFor(
    scale: GradeBand[],
    scored: number,
    outOf: number,
): GradeBand | undefined {
    return (
        scale.find((band) => 100 * scored >= band.min_percent * outOf) ??
        scale[scale.length - 1]
    );
}

/** Whole percent, half-up: 20 of 25 is 80. */
export function wholePercent(scored: number, outOf: number): number {
    return Math.floor((200 * scored + outOf) / (2 * outOf));
}

/** Whole half-marks as marks: 25 is "12.5". */
export function formatHalves(halves: number): string {
    return `${Math.floor(halves / 2)}${halves % 2 === 1 ? '.5' : ''}`;
}

/** Tenths of a percent as text: 913 is "91.3". */
export function formatTenths(tenths: number): string {
    return `${Math.floor(tenths / 10)}.${tenths % 10}`;
}
