import { FileText, Image as ImageIcon, X } from 'lucide-react';
import type { HomeworkFile } from '@/types';

/** "182 KB" or "1.4 MB". */
export function formatFileSize(bytes: number): string {
    return bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** Links to files already stored, as the prototype's attachment chips. */
export function HomeworkFileLinks({ files }: { files: HomeworkFile[] }) {
    if (files.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {files.map((file) => (
                <li key={file.ulid}>
                    <a
                        href={file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex max-w-full items-center gap-2 rounded-lg border px-3 py-1.5 text-sm hover:bg-muted"
                    >
                        {file.is_image ? (
                            <ImageIcon
                                className="size-4 shrink-0 text-primary"
                                aria-hidden
                            />
                        ) : (
                            <FileText
                                className="size-4 shrink-0 text-primary"
                                aria-hidden
                            />
                        )}
                        <span className="truncate">{file.name}</span>
                        <span className="shrink-0 text-xs text-muted-foreground">
                            {formatFileSize(file.size_bytes)}
                        </span>
                    </a>
                </li>
            ))}
        </ul>
    );
}

/** Files picked but not uploaded yet, each with a remove button. */
export function PickedFiles({
    files,
    onRemove,
}: {
    files: File[];
    onRemove: (index: number) => void;
}) {
    if (files.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-wrap gap-2">
            {files.map((file, index) => (
                <li
                    key={`${file.name}-${index}`}
                    className="inline-flex max-w-full items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                >
                    <span className="truncate">{file.name}</span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {formatFileSize(file.size)}
                    </span>
                    <button
                        type="button"
                        aria-label={`Remove ${file.name}`}
                        onClick={() => onRemove(index)}
                        className="rounded p-0.5 hover:bg-muted"
                    >
                        <X className="size-3.5" aria-hidden />
                    </button>
                </li>
            ))}
        </ul>
    );
}
