import { GraduationCap } from 'lucide-react';

/**
 * The prototype's brand block: indigo mark, product name, and what area this is.
 */
export default function PlatformBrand() {
    return (
        <>
            <span className="grid size-[34px] shrink-0 place-items-center rounded-[9px] bg-primary text-primary-foreground">
                <GraduationCap className="size-5" aria-hidden="true" />
            </span>
            <span className="grid min-w-0 flex-1 text-left leading-tight">
                <strong className="truncate text-[15px] font-semibold">
                    Shiksha ERP
                </strong>
                <small className="truncate text-xs text-muted-foreground">
                    Platform admin
                </small>
            </span>
        </>
    );
}
