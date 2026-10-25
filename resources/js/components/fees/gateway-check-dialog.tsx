import { useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import { selectClassName } from '@/components/school/field';
import type { GatewayInfo, GatewayReport } from '@/types';

/**
 * Asks the payment gateway about a pending payment ("Check status") or a
 * failed one ("Re-check"). With a test gateway the accountant picks what the
 * "bank" says; a real gateway is simply asked and the dialog has no choice.
 */
export default function GatewayCheckDialog({
    recheck,
    gateway,
    description,
    processing,
    onClose,
    onConfirm,
}: {
    recheck: boolean;
    gateway: GatewayInfo | null;
    description: string;
    processing: boolean;
    onClose: () => void;
    onConfirm: (report: GatewayReport | null) => void;
}) {
    const options: { value: GatewayReport; label: string }[] = recheck
        ? [
              { value: 'failed', label: 'Still failed: no money taken' },
              { value: 'captured', label: 'Actually captured (late success)' },
          ]
        : [
              { value: 'captured', label: 'Captured: money received' },
              { value: 'failed', label: 'Failed: no money taken' },
          ];
    const [report, setReport] = useState<GatewayReport>(options[0].value);

    return (
        <ConfirmDialog
            title={recheck ? 'Re-check failed payment' : 'Check payment status'}
            description={
                gateway === null
                    ? 'No payment gateway is set up for this school, so there is nobody to ask. The platform administrator can choose one.'
                    : description
            }
            confirmLabel={recheck ? 'Re-check' : 'Update status'}
            confirmDisabled={gateway === null}
            processing={processing}
            onClose={onClose}
            onConfirm={() => onConfirm(gateway?.simulated ? report : null)}
        >
            {gateway?.simulated && (
                <div className="grid gap-1.5 rounded-md border border-dashed border-amber-400 bg-amber-50 p-3 dark:bg-amber-950/40">
                    <label
                        htmlFor="gateway-report"
                        className="text-xs font-semibold text-amber-900 dark:text-amber-200"
                    >
                        Test gateway only: what the gateway reports
                    </label>
                    <select
                        id="gateway-report"
                        className={selectClassName}
                        value={report}
                        onChange={(event) =>
                            setReport(event.target.value as GatewayReport)
                        }
                    >
                        {options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            )}
        </ConfirmDialog>
    );
}
