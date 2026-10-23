import { Form, Head, Link, router } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { resendCode, signIn } from '@/routes/parent';
import { store } from '@/routes/parent/verify';

type Props = {
    phone_masked: string;
    code_length: number;
    resend_seconds: number;
    status?: string | null;
};

export default function ParentVerify({
    phone_masked: phoneMasked,
    code_length: codeLength,
    resend_seconds: resendSeconds,
    status,
}: Props) {
    const [code, setCode] = useState('');
    const [wait, setWait] = useState(resendSeconds);

    useEffect(() => {
        if (wait <= 0) {
            return;
        }

        const timer = window.setTimeout(() => setWait(wait - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [wait]);

    const resend = () => {
        router.post(
            resendCode.url(),
            {},
            { preserveScroll: true, onSuccess: () => setWait(resendSeconds) },
        );
    };

    return (
        <>
            <Head title="Enter your code" />

            <Form
                {...store.form()}
                className="grid gap-4"
                resetOnError
                resetOnSuccess
            >
                {({ errors, processing }) => (
                    <>
                        <p className="text-center text-sm text-muted-foreground">
                            If {phoneMasked} is registered with the school, we
                            sent it a {codeLength}-digit code.
                        </p>

                        <div className="grid justify-items-center gap-2">
                            <InputOTP
                                name="code"
                                maxLength={codeLength}
                                value={code}
                                onChange={setCode}
                                disabled={processing}
                                pattern={REGEXP_ONLY_DIGITS}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                autoFocus
                                aria-label="One-time code"
                            >
                                <InputOTPGroup>
                                    {Array.from(
                                        { length: codeLength },
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

                        {status && (
                            <p
                                role="status"
                                className="text-center text-sm text-green-700 dark:text-green-400"
                            >
                                {status}
                            </p>
                        )}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing || code.length < codeLength}
                        >
                            {processing && <Spinner />}
                            Sign in
                        </Button>
                    </>
                )}
            </Form>

            <div className="mt-4 flex items-center justify-between border-t border-border pt-4 text-sm">
                <Link
                    href={signIn.url()}
                    className="text-muted-foreground underline underline-offset-4 hover:text-foreground"
                >
                    Use another number
                </Link>
                <button
                    type="button"
                    onClick={resend}
                    disabled={wait > 0}
                    className="text-primary underline underline-offset-4 disabled:text-muted-foreground disabled:no-underline"
                >
                    {wait > 0 ? `Resend code in ${wait}s` : 'Resend code'}
                </button>
            </div>
        </>
    );
}

ParentVerify.layout = {
    title: 'Enter your code',
    description: 'Type the code we sent by SMS.',
};
