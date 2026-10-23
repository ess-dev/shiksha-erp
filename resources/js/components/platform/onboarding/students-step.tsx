import { Clock, FileSpreadsheet } from 'lucide-react';

/**
 * Importing a student list needs the Students module, which is not built, so
 * only "Do it later" can be chosen for now.
 */
export default function StudentsStep() {
    return (
        <div className="grid gap-2">
            <label className="flex cursor-not-allowed items-center gap-3 rounded-xl border border-border p-3.5 opacity-60">
                <input type="radio" name="students-method" disabled />
                <FileSpreadsheet
                    className="size-5 shrink-0"
                    aria-hidden="true"
                />
                <span className="grid flex-1">
                    <strong>Upload a spreadsheet</strong>
                    <span className="text-[13px] text-muted-foreground">
                        CSV or Excel with name, class, section, guardian and
                        phone
                    </span>
                </span>
                <span className="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                    Coming soon
                </span>
            </label>
            <label className="flex items-center gap-3 rounded-xl border-2 border-primary bg-secondary/60 p-3.5">
                <input
                    type="radio"
                    name="students-method"
                    className="accent-primary"
                    checked
                    readOnly
                />
                <Clock className="size-5 shrink-0" aria-hidden="true" />
                <span className="grid">
                    <strong>Do it later</strong>
                    <span className="text-[13px] text-muted-foreground">
                        The school admin imports students after setup
                    </span>
                </span>
            </label>
        </div>
    );
}
