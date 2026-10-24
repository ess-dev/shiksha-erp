import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import ReportCardDocument from '@/components/exams/report-card-document';
import { Button } from '@/components/ui/button';
import { results } from '@/routes/parent';
import type { ReportCard } from '@/types/parent';

type Props = {
    card: ReportCard;
    child: string;
};

/**
 * The report card for one exam, generated from published marks. Print or
 * Save as PDF from the browser; the buttons are hidden when printing.
 */
export default function ReportCardPage({ card, child }: Props) {
    const result = card.result;

    return (
        <>
            <Head title={`Report card · ${result.name}`} />

            <div className="mx-auto max-w-3xl p-6">
                <div className="mb-4 flex items-center justify-between print:hidden">
                    <Link
                        href={results.url({
                            query: { child, exam: result.ulid },
                        })}
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        <ArrowLeft className="size-4" /> Back to results
                    </Link>
                    <Button onClick={() => window.print()}>
                        <Printer /> Print or save as PDF
                    </Button>
                </div>

                <ReportCardDocument card={card} />
            </div>
        </>
    );
}
