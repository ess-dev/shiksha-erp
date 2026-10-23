import { usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { Auth } from '@/types';

/**
 * Explains why the rest of the staff area is closed until two-step sign-in is
 * on. Shown only to people the school's rules require it for.
 */
export default function TwoStepNotice() {
    const { auth } = usePage<{ auth: Auth }>().props;

    if (!auth.two_step_required) {
        return null;
    }

    return (
        <Alert
            data-test="two-step-notice"
            className="mb-6 border-amber-500 bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <ShieldAlert />
            <AlertTitle>Set up two-step sign-in to continue</AlertTitle>
            <AlertDescription>
                Your role needs two-step sign-in. Until you turn it on in
                Security settings (enable two-step, scan the QR code with an
                authenticator app and enter the code), every other page will
                bring you back here.
            </AlertDescription>
        </Alert>
    );
}
