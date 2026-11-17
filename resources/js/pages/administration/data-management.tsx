import { Head, router } from '@inertiajs/react';
import { Search, ShieldAlert, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, PageHeading, Pager } from '@/components/office-ui';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { useTranslate } from '@/lib/i18n';

type Resource = { key: string; label: string; count: number };
type RecordRow = {
    id: number;
    title: string;
    summary: Record<string, string | number>;
    created_at: string | null;
};

export default function DataManagement({
    resources,
    selected,
    q,
    records,
    recentDeletions,
}: {
    resources: Resource[];
    selected: string;
    q: string;
    records: {
        data: RecordRow[];
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    recentDeletions: Array<{
        id: number;
        resource_label: string;
        reason: string;
        deleted_rows: number;
        created_at: string;
    }>;
}) {
    const t = useTranslate();
    const [search, setSearch] = useState(q);
    const [target, setTarget] = useState<RecordRow | null>(null);
    const [reason, setReason] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [processing, setProcessing] = useState(false);
    const current = resources.find((item) => item.key === selected);
    const remove = () => {
        if (!target) return;
        setProcessing(true);
        router.delete(`/data-management/${selected}/${target.id}`, {
            data: { resource: selected, reason, confirmation },
            preserveScroll: true,
            onFinish: () => setProcessing(false),
            onSuccess: () => {
                setTarget(null);
                setReason('');
                setConfirmation('');
            },
        });
    };

    return (
        <>
            <Head title={t('Data management')} />
            <div className="ef-page">
                <PageHeading
                    eyebrow={t('Main admin only')}
                    title={t('Data management')}
                    description={t(
                        'Find and permanently delete operational records together with their dependent records.',
                    )}
                />
                <div className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-950 dark:border-red-900 dark:bg-red-950/35 dark:text-red-100">
                    <ShieldAlert className="mt-0.5 size-5 shrink-0" />
                    <p>
                        {t(
                            'Deletion cannot be undone. EstateFlow records the main admin, reason, original record and number of dependent rows removed.',
                        )}
                    </p>
                </div>
                <div className="grid gap-5 xl:grid-cols-[16rem_minmax(0,1fr)]">
                    <aside className="ef-panel h-fit p-2">
                        {resources.map((resource) => (
                            <button
                                key={resource.key}
                                type="button"
                                onClick={() =>
                                    router.get('/data-management', {
                                        resource: resource.key,
                                    })
                                }
                                className={`flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-start text-sm ${selected === resource.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                            >
                                <span>{t(resource.label)}</span>
                                <span className="rounded-full bg-background/20 px-2 text-xs tabular-nums">
                                    {resource.count}
                                </span>
                            </button>
                        ))}
                    </aside>
                    <section className="ef-panel overflow-hidden">
                        <div className="ef-toolbar flex-wrap">
                            <div>
                                <h2 className="font-semibold">
                                    {t(current?.label ?? '')}
                                </h2>
                                <p className="text-xs text-muted-foreground">
                                    {records.total} {t('records')}
                                </p>
                            </div>
                            <form
                                className="flex min-w-0 flex-1 gap-2 sm:max-w-md"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.get('/data-management', {
                                        resource: selected,
                                        q: search,
                                    });
                                }}
                            >
                                <div className="relative min-w-0 flex-1">
                                    <Search className="absolute start-3 top-3 size-4 text-muted-foreground" />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        className="ps-9"
                                        placeholder={t('Search records')}
                                    />
                                </div>
                                <Button variant="outline">{t('Search')}</Button>
                            </form>
                        </div>
                        {records.data.length === 0 ? (
                            <EmptyState
                                title={t('No records found')}
                                description={t(
                                    'There are no records in this category.',
                                )}
                            />
                        ) : (
                            <div className="divide-y">
                                {records.data.map((record) => (
                                    <div
                                        key={record.id}
                                        className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold break-words">
                                                {record.title ||
                                                    `#${record.id}`}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                ID {record.id}
                                            </p>
                                            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                {Object.entries(
                                                    record.summary,
                                                ).map(([key, value]) => (
                                                    <span key={key}>
                                                        {key.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                        : {String(value)}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                        <Button
                                            variant="destructive"
                                            onClick={() => setTarget(record)}
                                        >
                                            <Trash2 className="size-4" />
                                            {t('Delete')}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                        <Pager
                            prev_page_url={records.prev_page_url}
                            next_page_url={records.next_page_url}
                        />
                    </section>
                </div>
                {recentDeletions.length > 0 && (
                    <section className="ef-panel p-5">
                        <h2 className="font-semibold">
                            {t('Recent deletions')}
                        </h2>
                        <div className="mt-4 divide-y">
                            {recentDeletions.map((event) => (
                                <div key={event.id} className="py-3 text-sm">
                                    <p className="font-medium">
                                        {event.resource_label}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {event.reason} · {event.deleted_rows}{' '}
                                        {t('rows removed')}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
            <Dialog
                open={target !== null}
                onOpenChange={(open) => !open && setTarget(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Permanently delete record?')}
                        </DialogTitle>
                        <DialogDescription>
                            {target?.title} · ID {target?.id}.{' '}
                            {t('All dependent records will also be deleted.')}
                        </DialogDescription>
                    </DialogHeader>
                    <label className="space-y-2 text-sm font-medium">
                        <span>{t('Reason')}</span>
                        <textarea
                            className="min-h-24 w-full rounded-md border bg-background px-3 py-2 font-normal"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            placeholder={t(
                                'Explain why this record must be deleted',
                            )}
                            maxLength={500}
                        />
                    </label>
                    <label className="space-y-2 text-sm font-medium">
                        <span>{t('Type DELETE to confirm')}</span>
                        <Input
                            value={confirmation}
                            onChange={(event) =>
                                setConfirmation(event.target.value)
                            }
                            autoComplete="off"
                        />
                    </label>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setTarget(null)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={
                                processing ||
                                confirmation !== 'DELETE' ||
                                reason.trim().length < 8
                            }
                            onClick={remove}
                        >
                            {processing
                                ? t('Deleting…')
                                : t('Delete permanently')}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

DataManagement.layout = (page: React.ReactNode) => (
    <AppLayout children={page} />
);
