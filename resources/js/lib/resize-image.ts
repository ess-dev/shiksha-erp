/** Photos longer than this on either side are scaled down before upload. */
const MAX_SIDE = 1600;
/** Photos already smaller than this are left alone. */
const SKIP_UNDER_BYTES = 400 * 1024;
const JPEG_QUALITY = 0.8;

/**
 * Shrinks a phone photo before it is uploaded, to save the family's data and
 * the school's storage: the long side is scaled to at most 1600 px and the
 * picture is saved as JPEG. It only helps; the server still checks every file.
 * Anything that is not a JPEG, PNG or WebP photo, or cannot be decoded, comes
 * back unchanged, and so does the original when the result is not smaller.
 */
export async function resizeImage(file: File): Promise<File> {
    if (
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        file.size < SKIP_UNDER_BYTES
    ) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(
            1,
            MAX_SIDE / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement('canvas');

        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');

        if (context === null) {
            return file;
        }

        // A PNG with a see-through background would turn black as a JPEG.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
        );

        if (blob === null || blob.size >= file.size) {
            return file;
        }

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        });
    } catch {
        return file;
    }
}
