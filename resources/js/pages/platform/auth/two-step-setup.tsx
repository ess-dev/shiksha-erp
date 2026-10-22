import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { signOut } from '@/routes/platform';
import { begin, confirm } from '@/routes/platform/two-step/setup';
import type { TwoStepSetup } from '@/types';

type Props = {
    setup: TwoStepSetup | null;
};

export default function PlatformTwoStepSetup({ setup }: Props) {
    const [code, setCode] = useState('');
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="Set up two-step verification" />

            {setup === null ? (
                <Form {...begin.form()} className="grid gap-4">
                    {({ processing }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                You will need an authenticator app such as
                                Google Authenticator, Microsoft Authenticator or
                                Authy.
                            </p>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-test="platform-two-step-begin"
                            >
                                {processing && <Spinner />}
                                Begin setup
                            </Button>
                        </>
                    )}
                </Form>
            ) : (
                <div className="grid gap-5">
                    <div
                        className="mx-auto w-fit rounded-lg border border-border bg-white p-2 [&>svg]:size-44"
                        aria-label="QR code for your authenticator app"
                        dangerouslySetInnerHTML={{ __html: setup.qr_code_svg }}
                    />

                    <div className="grid gap-1.5">
                        <p className="text-center text-xs text-muted-foreground">
                            Cannot scan? Enter this key instead
                        </p>
                        <div className="flex items-center gap-2 rounded-md border border-border bg-muted px-3 py-2">
                            <code className="min-w-0 flex-1 text-sm break-all">
                                {setup.secret_key}
                            </code>
                            <button
                                type="button"
                                aria-label="Copy key"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() => void copy(setup.secret_key)}
                            >
                                {copied === setup.secret_key ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                            </button>
                        </div>
                    </div>

                    <Form
                        {...confirm.form()}
                        className="grid gap-4"
                        resetOnError
                        onSuccess={() => setCode('')}
                    >
                        {({ errors, processing }) => (
                            <>
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
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        code.length < OTP_MAX_LENGTH
                                    }
                                    data-test="platform-two-step-confirm"
                                >
                                    {processing && <Spinner />}
                                    Turn on two-step
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form {...begin.form()} className="text-center">
                        <button
                            type="submit"
                            className="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                        >
                            Start over with a new key
                        </button>
                    </Form>
                </div>
            )}

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

PlatformTwoStepSetup.layout = {
    title: 'Set up two-step verification',
    description:
        'Super Admin accounts must use two-step. Scan the code, then enter the 6-digit number your app shows.',
};
