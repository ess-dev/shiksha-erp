import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { signOut } from '@/routes/platform';
import { store } from '@/routes/platform/two-step/challenge';

export default function PlatformTwoStepChallenge() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const [code, setCode] = useState('');

    return (
        <>
            <Head title="Two-step verification" />

            <Form
                {...store.form()}
                className="grid gap-4"
                resetOnError
                resetOnSuccess
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {useRecoveryCode ? (
                            <div className="grid gap-2">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    placeholder="Recovery code"
                                    aria-label="Recovery code"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.recovery_code} />
                            </div>
                        ) : (
                            <div className="grid justify-items-center gap-2">
                                <InputOTP
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    autoFocus
                                >
                                    <InputOTPGroup>
                                        {Array.from(
                                            { length: OTP_MAX_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError message={errors.code} />
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            Continue
                        </Button>

                        <button
                            type="button"
                            className="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                            onClick={() => {
                                setUseRecoveryCode(!useRecoveryCode);
                                setCode('');
                                clearErrors();
                            }}
                        >
                            {useRecoveryCode
                                ? 'Use an authenticator code instead'
                                : 'Use a recovery code instead'}
                        </button>
                    </>
                )}
            </Form>

            <Form
                {...signOut.form()}
                className="mt-4 border-t border-border pt-4 text-center"
            >
                <button
                    type="submit"
                    className="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                >
                    Sign out
                </button>
            </Form>
        </>
    );
}

PlatformTwoStepChallenge.layout = {
    title: 'Two-step verification',
    description: 'Enter the 6-digit code from your authenticator app.',
};
