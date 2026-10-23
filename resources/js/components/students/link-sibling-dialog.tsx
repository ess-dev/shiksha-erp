import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
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
import { requestJson } from '@/lib/http';
import siblings from '@/routes/students/siblings';
import type { StudentProfile, StudentSibling } from '@/types/students';

export default function LinkSiblingDialog({
    student,
    onClose,
}: {
    student: StudentProfile;
    onClose: () => void;
}) {
    const [term, setTerm] = useState('');
    const [found, setFound] = useState<StudentSibling[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [linking, setLinking] = useState(false);

    useEffect(() => {
        if (term.trim().length < 2) {
            return;
        }

        let cancelled = false;
        const timer = setTimeout(() => {
            void requestJson<StudentSibling[]>(
                siblings.candidates.url(student.ulid, {
                    query: { q: term.trim() },
                }),
            ).then((result) => {
                if (!cancelled) {
                    setFound(result.data ?? []);
                }
            });
        }, 250);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [term, student.ulid]);

    const link = (siblingUlid: string) => {
        setError(null);

        router.post(
            siblings.store.url(student.ulid),
            { sibling: siblingUlid },
            {
                preserveScroll: true,
                onStart: () => setLinking(true),
                onFinish: () => setLinking(false),
                onSuccess: onClose,
                onError: (errors) =>
                    setError(
                        errors.student ??
                            errors.sibling ??
                            'The students could not be linked.',
                    ),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Link a sibling to {student.name}</DialogTitle>
                    <DialogDescription>
                        For children whose guardians differ. Students who share
                        a guardian are siblings already. Linking works only when
                        every guardian of both students has portal access, and
                        it shows the link to staff only.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1.5">
                    <Label htmlFor="sibling-search">
                        Search by name or admission number
                    </Label>
                    <Input
                        id="sibling-search"
                        value={term}
                        onChange={(event) => {
                            setTerm(event.target.value);

                            if (event.target.value.trim().length < 2) {
                                setFound([]);
                            }
                        }}
                        autoFocus
                    />
                </div>

                <ul className="max-h-60 divide-y overflow-y-auto rounded-md border text-sm">
                    {term.trim().length >= 2 && found.length === 0 && (
                        <li className="px-3 py-3 text-muted-foreground">
                            No matching student.
                        </li>
                    )}
                    {found.map((candidate) => (
                        <li
                            key={candidate.ulid}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <span>
                                {candidate.name}
                                <span className="block text-xs text-muted-foreground">
                                    {candidate.class_label} ·{' '}
                                    {candidate.campus_name}
                                </span>
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={linking}
                                onClick={() => link(candidate.ulid)}
                            >
                                Link
                            </Button>
                        </li>
                    ))}
                </ul>
                <InputError message={error ?? undefined} />

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
