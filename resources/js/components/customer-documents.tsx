import { useForm } from '@inertiajs/react';
import { useRef } from 'react';
import { Download, FileText, Upload, LockKeyhole } from 'lucide-react';
import { Pager } from '@/components/office-ui';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';

const kinds: Record<string, string> = {
    customer_photo: 'Customer photograph',
    cnic_copy: 'CNIC copy',
    signed_booking_form: 'Signed booking form',
    signed_transfer_form: 'Signed purchaser transfer form',
    payment_evidence: 'Payment evidence',
    other: 'Other supporting document',
};
export type CustomerDocumentsPage = {
    data: {
        id: number;
        kind: string;
        mime: string;
        bytes: number;
        created_at: string;
    }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function CustomerDocuments({
    customerId,
    documents,
    manage,
}: {
    customerId: number;
    documents: CustomerDocumentsPage;
    manage: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ kind: string; file: File | null }>({
        kind: 'customer_photo',
        file: null,
    });
    useUnsavedChanges(form.isDirty);
    return (
        <section
            className="ef-panel overflow-hidden"
            aria-label="Customer documents"
        >
            <div className="ef-panel-heading">
                <div className="flex items-center gap-3">
                    <span className="rounded-lg bg-blue-50 p-2 text-blue-600">
                        <LockKeyhole className="size-5" />
                    </span>
                    <div>
                        <h2 className="font-semibold">
                            Identity & booking documents
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Private access for assigned staff and authorized
                            admins.
                        </p>
                    </div>
                </div>
            </div>
            {manage && (
                <form
                    className="space-y-4 border-b p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/customers/${customerId}/documents`, {
                            preserveScroll: true,
                            forceFormData: true,
                            onSuccess: () => {
                                form.reset();
                                if (fileInput.current)
                                    fileInput.current.value = '';
                            },
                        });
                    }}
                >
                    <div className="grid items-start gap-4 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="document-kind">Document type</Label>
                            <select
                                id="document-kind"
                                className="mt-2 min-h-10 w-full rounded-lg border bg-background px-3 text-sm"
                                value={form.data.kind}
                                onChange={(event) =>
                                    form.setData('kind', event.target.value)
                                }
                            >
                                {Object.entries(kinds).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.kind} />
                        </div>
                        <div>
                            <Label htmlFor="customer-document">
                                Upload file
                            </Label>
                            <Input
                                id="customer-document"
                                ref={fileInput}
                                type="file"
                                className="mt-2"
                                required
                                accept={
                                    form.data.kind === 'customer_photo'
                                        ? '.jpg,.jpeg,.png'
                                        : '.jpg,.jpeg,.png,.pdf'
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.file} />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="max-w-sm text-xs leading-5 text-muted-foreground">
                            JPG/PNG photographs; JPG, PNG or PDF documents.
                            Maximum 10 MB. Upload a new file to retain updated
                            evidence.
                        </p>
                        <Button disabled={form.processing}>
                            <Upload className="size-4" />
                            {form.processing
                                ? `Uploading${form.progress?.percentage ? ` ${form.progress.percentage}%` : '…'}`
                                : 'Upload document'}
                        </Button>
                    </div>
                </form>
            )}
            {documents.data.length ? (
                <ul className="divide-y">
                    {documents.data.map((document) => (
                        <li
                            className="flex items-center justify-between gap-4 px-6 py-4"
                            key={document.id}
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <FileText className="size-5 shrink-0 text-blue-600" />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {kinds[document.kind] ?? document.kind}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        #{document.id} ·{' '}
                                        {Math.ceil(document.bytes / 1024)} KB ·{' '}
                                        {new Date(
                                            document.created_at,
                                        ).toLocaleDateString('en-PK', {
                                            timeZone: 'Asia/Karachi',
                                        })}
                                    </p>
                                </div>
                            </div>
                            <a
                                className="ef-text-link shrink-0 text-xs"
                                href={`/customers/${customerId}/documents/${document.id}`}
                            >
                                <Download className="size-4" />
                                <span>Download</span>
                                <span className="sr-only">
                                    {' '}
                                    {kinds[document.kind]} #{document.id}
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="p-6 text-sm text-muted-foreground">
                    No documents uploaded yet. Add the customer's photograph and
                    supporting booking evidence here.
                </p>
            )}
            <Pager {...documents} />
        </section>
    );
}
