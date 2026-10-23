import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { sendCode } from '@/routes/parent';

export default function ParentSignIn() {
    return (
        <>
            <Head title="Parent sign in" />

            <Form {...sendCode.form()} className="grid gap-4">
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Mobile number</Label>
                            <div className="flex gap-2">
                                <span
                                    aria-hidden
                                    className="flex h-9 items-center rounded-md border bg-muted px-3 text-sm text-muted-foreground"
                                >
                                    +91
                                </span>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="numeric"
                                    autoComplete="tel-national"
                                    placeholder="10-digit mobile number"
                                    required
                                    autoFocus
                                />
                            </div>
                            <InputError message={errors.phone} />
                            <p className="text-xs text-muted-foreground">
                                Use the number the school has for you. We will
                                send a code by SMS.
                            </p>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Send code
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ParentSignIn.layout = {
    title: 'Parent sign in',
    description: 'Sign in with your mobile number to see your children.',
};
