import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, AlertCircle, ArrowLeft } from 'lucide-react';
import { PageHeading, StatusBadge } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/lib/i18n';

type Row = {
    id: number;
    row_number: number;
    payload: Record<string, string>;
    errors: string[] | null;
    state: string;
};
type Batch = {
    id: number;
    template: string;
    state: string;
    row_count: number;
    error_count: number;
    source_filename: string | null;
    created_at: string;
    committed_at: string | null;
    actor: { id: number; name: string } | null;
    rows: Row[];
};

export default function ImportShow({
    batch,
    template,
}: {
    batch: Batch;
    template: { label: string; headers: string[] } | null;
}) {
    const t = useTranslate();
    const form = useForm({});
    const canCommit = batch.state === 'dry_run' && batch.error_count === 0;

    return (
        <>
            <Head title={`${t('Import batch')} #${batch.id}`} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Administration')}
                    title={`${t('Import batch')} #${batch.id}`}
                    description={`${template?.label ?? batch.template} · ${batch.source_filename ?? ''}`}
                >
                    <Button asChild variant="outline">
                        <Link href="/imports">
                            <ArrowLeft className="size-4" />
                            {t('Back to imports')}
                        </Link>
                    </Button>
                    {canCommit && (
                        <Button
                            onClick={() =>
                                form.post(`/imports/${batch.id}/commit`)
                            }
                            disabled={form.processing}
                        >
                            <CheckCircle2 className="size-4" />
                            {t('Commit import')}
                        </Button>
                    )}
                </PageHeading>

                <div className="grid gap-4 sm:grid-cols-4">
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Status')}
                        </p>
                        <p className="mt-2 text-2xl font-bold">{batch.state}</p>
                    </div>
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Rows')}
                        </p>
                        <p className="mt-2 text-2xl font-bold">
                            {batch.row_count}
                        </p>
                    </div>
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('Errors')}
                        </p>
                        <p className="mt-2 text-2xl font-bold">
                            {batch.error_count}
                        </p>
                    </div>
                    <div className="ef-panel p-5">
                        <p className="text-sm text-muted-foreground">
                            {t('By')}
                        </p>
                        <p className="mt-2 text-lg font-semibold">
                            {batch.actor?.name ?? '—'}
                        </p>
                    </div>
                </div>

                {batch.state === 'dry_run' && batch.error_count > 0 && (
                    <div className="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                        <AlertCircle className="mt-1 size-5 shrink-0" />
                        <p>
                            {t(
                                'Fix the errors below and upload the file again. Nothing is saved until you commit a clean dry run.',
                            )}
                        </p>
                    </div>
                )}

                <section className="ef-panel overflow-hidden">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">{t('Rows')}</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="ef-table">
                            <thead>
                                <tr>
                                    <th>{t('Row')}</th>
                                    <th>{t('Status')}</th>
                                    {template?.headers.map((header) => (
                                        <th key={header}>{header}</th>
                                    ))}
                                    <th>{t('Errors')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {batch.rows.map((row) => (
                                    <tr key={row.id}>
                                        <td>{row.row_number}</td>
                                        <td>
                                            <StatusBadge
                                                label={row.state}
                                                tone={
                                                    row.state === 'valid'
                                                        ? 'success'
                                                        : row.state ===
                                                            'invalid'
                                                          ? 'danger'
                                                          : 'info'
                                                }
                                            />
                                        </td>
                                        {template?.headers.map((header) => (
                                            <td key={header}>
                                                {row.payload[header] ?? ''}
                                            </td>
                                        ))}
                                        <td className="text-red-600">
                                            {row.errors?.join('; ') ?? ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}
