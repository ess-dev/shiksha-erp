import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCalendarDate } from '@/lib/format';
import { show } from '@/routes/students';
import type { TransferCertificate } from '@/types/students';

type Props = {
    certificate: TransferCertificate;
    student_ulid: string;
};

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[12rem_1fr] gap-4 border-b py-2 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}

/**
 * A printable transfer certificate. Print or Save as PDF from the browser; the
 * buttons and anything outside the document are hidden when printing.
 */
export default function TransferCertificatePage({
    certificate,
    student_ulid,
}: Props) {
    return (
        <>
            <Head title={`Transfer certificate ${certificate.tc_number}`} />

            <div className="mx-auto max-w-3xl p-6">
                <div className="mb-4 flex items-center justify-between print:hidden">
                    <Link
                        href={show(student_ulid)}
                        className="inline-flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        <ArrowLeft className="size-4" /> Back to the student
                    </Link>
                    <Button onClick={() => window.print()}>
                        <Printer /> Print or save as PDF
                    </Button>
                </div>

                <article className="rounded-md border p-10 print:border-0 print:p-0">
                    <header className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold">
                            {certificate.school_name}
                        </h1>
                        <p className="mt-1 text-lg font-medium tracking-wide uppercase">
                            Transfer certificate
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            TC number {certificate.tc_number}
                        </p>
                    </header>

                    <dl>
                        <Row label="Student" value={certificate.student_name} />
                        <Row
                            label="Admission number"
                            value={certificate.admission_number}
                        />
                        <Row
                            label="Date of birth"
                            value={formatCalendarDate(certificate.born_on)}
                        />
                        <Row label="Gender" value={certificate.gender} />
                        <Row
                            label="Guardian"
                            value={certificate.guardian_names.join(', ') || '—'}
                        />
                        <Row
                            label="Admitted on"
                            value={formatCalendarDate(certificate.admitted_on)}
                        />
                        <Row
                            label="Class last attended"
                            value={certificate.class_label ?? '—'}
                        />
                        <Row
                            label="Last day at school"
                            value={formatCalendarDate(certificate.last_day)}
                        />
                        <Row
                            label="Reason for leaving"
                            value={certificate.reason}
                        />
                    </dl>

                    <footer className="mt-16 flex justify-between text-sm text-muted-foreground">
                        <span>Date of issue: ____________</span>
                        <span>Principal&apos;s signature and seal</span>
                    </footer>
                </article>

                <p className="mt-3 text-xs text-muted-foreground print:hidden">
                    The layout of the certificate is a starting point: schools
                    have their own formats, and the wording needs the
                    school&apos;s and legal review before use.
                </p>
            </div>
        </>
    );
}
