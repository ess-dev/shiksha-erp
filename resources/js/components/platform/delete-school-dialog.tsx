import { router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/platform/confirm-dialog';
import { Input } from '@/components/ui/input';
import { destroy } from '@/routes/platform/schools';

/**
 * Permanent delete of a school. The button stays disabled in effect until the
 * Super Admin types "delete <school name>"; the server checks it again.
 */
export default function DeleteSchoolDialog({
    school,
    onClose,
}: {
    school: { ulid: string; name: string };
    onClose: () => void;
}) {
    const phrase = `delete ${school.name}`;
    const [typed, setTyped] = useState('');
    const [processing, setProcessing] = useState(false);

    return (
        <ConfirmDialog
            title={`Delete ${school.name}?`}
            description="This permanently deletes the school's database, files, sign-in memberships and domains. It cannot be undone. Consider suspending the school instead."
            confirmLabel="Delete school forever"
            destructive
            processing={processing}
            confirmDisabled={typed.trim() !== phrase}
            onClose={onClose}
            onConfirm={() => {
                setProcessing(true);
                router.delete(destroy.url(school.ulid), {
                    data: { confirmation: typed.trim() },
                    onSuccess: onClose,
                    onFinish: () => setProcessing(false),
                });
            }}
        >
            <div className="grid gap-1.5">
                <label htmlFor="delete-school-confirm" className="text-sm">
                    Type <strong className="select-all">{phrase}</strong> to
                    confirm
                </label>
                <Input
                    id="delete-school-confirm"
                    value={typed}
                    autoComplete="off"
                    onChange={(event) => setTyped(event.target.value)}
                />
            </div>
        </ConfirmDialog>
    );
}
