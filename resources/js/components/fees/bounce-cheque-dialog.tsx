import { useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import { selectClassName } from '@/components/school/field';

/** Asks the bank's reason for a bounced cheque; the reason is audited. */
export default function BounceChequeDialog({
    reasons,
    processing,
    onClose,
    onConfirm,
}: {
    reasons: string[];
    processing: boolean;
    onClose: () => void;
    onConfirm: (reason: string) => void;
}) {
    const [reason, setReason] = useState(reasons[0] ?? '');

    return (
        <ConfirmDialog
            title="Cheque bounced"
            description="The installments go back to unpaid. Any bounce charge would be added separately, as the school's policy says."
            confirmLabel="Mark bounced"
            destructive
            processing={processing}
            onClose={onClose}
            onConfirm={() => onConfirm(reason)}
        >
            <div className="grid gap-1.5">
                <label htmlFor="bounce-reason" className="text-sm">
                    Bank’s reason
                </label>
                <select
                    id="bounce-reason"
                    className={selectClassName}
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                >
                    {reasons.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </div>
        </ConfirmDialog>
    );
}
