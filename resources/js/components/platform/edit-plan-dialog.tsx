import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import ModuleToggle from '@/components/platform/module-toggle';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/platform/plans';
import type { PlatformModule, PlatformPlan } from '@/types';

type Props = {
    plan: PlatformPlan;
    modules: PlatformModule[];
    onClose: () => void;
};

/**
 * Edit price, minimum billable students and included modules. Taking modules
 * away from a plan that schools are on asks for confirmation first.
 */
export default function EditPlanDialog({ plan, modules, onClose }: Props) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({
        price: String(plan.price_per_student_year_paise / 100),
        min_students: String(plan.min_students),
        modules: plan.module_keys,
    });

    const removed = plan.module_keys.filter(
        (key) => !form.data.modules.includes(key),
    );
    const labelOf = (key: string) =>
        modules.find((module) => module.key === key)?.label ?? key;

    const save = () => {
        setConfirming(false);
        form.patch(update.url(plan.ulid), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (removed.length > 0 && plan.schools_count > 0) {
            setConfirming(true);

            return;
        }

        save();
    };

    const toggle = (key: string, on: boolean) =>
        form.setData(
            'modules',
            on
                ? [...form.data.modules, key]
                : form.data.modules.filter((existing) => existing !== key),
        );

    return (
        <>
            <Dialog open onOpenChange={(open) => !open && onClose()}>
                <DialogContent className="flex max-h-[90vh] flex-col gap-0 p-0 sm:max-w-xl">
                    <DialogHeader className="border-b border-border px-6 py-4">
                        <DialogTitle>Edit {plan.name} plan</DialogTitle>
                        <DialogDescription className="sr-only">
                            Price, minimum students and modules for this plan
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={submit}
                        noValidate
                        className="flex min-h-0 flex-1 flex-col"
                    >
                        <div className="min-h-0 flex-1 overflow-y-auto px-6 py-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="plan-price">
                                        Price per student per year (₹)
                                    </Label>
                                    <Input
                                        id="plan-price"
                                        type="number"
                                        min={0}
                                        step={10}
                                        value={form.data.price}
                                        onChange={(event) =>
                                            form.setData(
                                                'price',
                                                event.target.value,
                                            )
                                        }
                                        autoFocus
                                    />
                                    <InputError message={form.errors.price} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="plan-min">
                                        Minimum billable students
                                    </Label>
                                    <Input
                                        id="plan-min"
                                        type="number"
                                        min={0}
                                        step={10}
                                        value={form.data.min_students}
                                        onChange={(event) =>
                                            form.setData(
                                                'min_students',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.min_students}
                                    />
                                </div>
                            </div>

                            <fieldset className="mt-5">
                                <legend className="mb-2 text-sm font-medium text-slate-600">
                                    Modules included
                                </legend>
                                {modules.map((module) => (
                                    <ModuleToggle
                                        key={module.key}
                                        label={module.label}
                                        checked={form.data.modules.includes(
                                            module.key,
                                        )}
                                        onChange={(on) =>
                                            toggle(module.key, on)
                                        }
                                    />
                                ))}
                                <InputError message={form.errors.modules} />
                            </fieldset>

                            <p className="mt-4 text-[13px] text-muted-foreground">
                                {plan.schools_count === 1
                                    ? '1 school on this plan.'
                                    : `${plan.schools_count} schools on this plan.`}{' '}
                                New prices apply at each school&apos;s next
                                renewal; module changes apply immediately.
                            </p>
                        </div>

                        <DialogFooter className="border-t border-border px-6 py-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="save-plan"
                            >
                                {form.processing && <Spinner />}
                                Save plan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={confirming}
                onOpenChange={(open) => !open && setConfirming(false)}
            >
                <DialogContent className="gap-0 p-0 sm:max-w-md">
                    <DialogHeader className="border-b border-border px-6 py-4">
                        <DialogTitle>
                            Remove modules from{' '}
                            {plan.schools_count === 1
                                ? '1 school'
                                : `${plan.schools_count} schools`}
                            ?
                        </DialogTitle>
                    </DialogHeader>
                    <DialogDescription className="px-6 py-4 text-sm text-slate-600">
                        Schools on {plan.name} lose access to{' '}
                        {removed.map(labelOf).join(', ')} straight away (their
                        data is kept).
                    </DialogDescription>
                    <DialogFooter className="border-t border-border px-6 py-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirming(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={save}
                            data-test="confirm-remove-modules"
                        >
                            Remove modules
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
