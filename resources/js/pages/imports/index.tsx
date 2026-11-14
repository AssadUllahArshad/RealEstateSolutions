import { Head, Link, useForm } from '@inertiajs/react';
import { Download, Upload, FileSpreadsheet } from 'lucide-react';
import { EmptyState, PageHeading } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';

type Template = { key: string; label: string; headers: string[] };
type Batch = {
    id: number;
    template: string;
    state: string;
    row_count: number;
    error_count: number;
    source_filename: string | null;
    created_at: string;
    actor: { id: number; name: string } | null;
};

export default function Imports({
    templates,
    batches,
}: {
    templates: Template[];
    batches: Batch[];
}) {
    const t = useTranslate();
    const form = useForm<{ template: string; file: File | null }>({
        template: templates[0]?.key ?? 'customers',
        file: null,
    });

    return (
        <>
            <Head title={t('Import data')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Administration')}
                    title={t('Import data')}
                    description={t(
                        'Choose a template, download it, fill it in and upload it. The dry run validates every row and shows errors before anything is saved.',
                    )}
                />
                <div className="flex gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <FileSpreadsheet className="mt-1 size-5 shrink-0" />
                    <p>
                        {t(
                            'This import creates records. It does not post payments, recognize money or change existing bookings.',
                        )}
                    </p>
                </div>

                <section className="ef-panel p-6">
                    <h2 className="font-semibold">{t('Download template')}</h2>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        {templates.map((template) => (
                            <a
                                key={template.key}
                                href={`/imports/template/${template.key}`}
                                className="flex items-center justify-between gap-3 rounded-xl border p-4 text-sm font-semibold transition hover:border-primary/35 hover:bg-accent"
                            >
                                <span>{template.label}</span>
                                <Download className="size-4 text-muted-foreground" />
                            </a>
                        ))}
                    </div>
                </section>

                <section className="ef-panel p-6">
                    <h2 className="font-semibold">{t('Upload CSV')}</h2>
                    <form
                        className="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/imports/dry-run', {
                                forceFormData: true,
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="import-template">
                                {t('Template')}
                            </Label>
                            <select
                                id="import-template"
                                className="ef-input mt-1 w-full"
                                value={form.data.template}
                                onChange={(event) =>
                                    form.setData('template', event.target.value)
                                }
                            >
                                {templates.map((template) => (
                                    <option
                                        key={template.key}
                                        value={template.key}
                                    >
                                        {template.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="import-file">{t('CSV file')}</Label>
                            <Input
                                id="import-file"
                                type="file"
                                accept=".csv,text/csv"
                                className="mt-1"
                                onChange={(event) =>
                                    form.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                        <Button
                            type="submit"
                            className="self-end"
                            disabled={form.processing || !form.data.file}
                        >
                            <Upload className="size-4" />
                            {t('Dry run')}
                        </Button>
                    </form>
                    {form.errors.file && (
                        <p className="mt-2 text-sm text-red-600">
                            {form.errors.file}
                        </p>
                    )}
                    {form.errors.template && (
                        <p className="mt-2 text-sm text-red-600">
                            {form.errors.template}
                        </p>
                    )}
                </section>

                <section className="ef-panel overflow-hidden">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">{t('Import batch')}</h2>
                    </div>
                    {batches.length ? (
                        <div className="overflow-x-auto">
                            <table className="ef-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{t('Template')}</th>
                                        <th>{t('Status')}</th>
                                        <th>{t('Rows')}</th>
                                        <th>{t('Errors')}</th>
                                        <th>{t('File')}</th>
                                        <th>{t('By')}</th>
                                        <th>{t('When')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {batches.map((batch) => (
                                        <tr key={batch.id}>
                                            <td>
                                                <Link
                                                    href={`/imports/${batch.id}`}
                                                    className="ef-text-link"
                                                >
                                                    #{batch.id}
                                                </Link>
                                            </td>
                                            <td>{batch.template}</td>
                                            <td>{batch.state}</td>
                                            <td>{batch.row_count}</td>
                                            <td>{batch.error_count}</td>
                                            <td>
                                                {batch.source_filename ?? '—'}
                                            </td>
                                            <td>{batch.actor?.name ?? '—'}</td>
                                            <td>{batch.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState
                            title={t('No import batches yet')}
                            description={t(
                                'Download a template, fill it in and upload it to start.',
                            )}
                        />
                    )}
                </section>
            </div>
        </>
    );
}
