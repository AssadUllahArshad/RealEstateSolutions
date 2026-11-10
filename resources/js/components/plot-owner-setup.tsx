import { useForm } from '@inertiajs/react';
import { UserRoundCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';
import type { PlotOwner } from '@/types/inventory';

function OwnerProfileEditor({
    owner,
    adminName,
}: {
    owner: PlotOwner;
    adminName: string;
}) {
    const t = useTranslate();
    const form = useForm({
        name: owner.name,
        default_percentage: owner.default_percentage ?? '',
    });
    useUnsavedChanges(form.isDirty);

    return (
        <form
            className="grid gap-3 border-b py-4 sm:grid-cols-[1fr_1fr_1fr_auto]"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(`/inventory/owners/${owner.id}`, {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                });
            }}
        >
            <div>
                <Label htmlFor={`owner-name-${owner.id}`}>
                    {t('Owner name')}
                </Label>
                <Input
                    id={`owner-name-${owner.id}`}
                    className="mt-2"
                    maxLength={120}
                    required
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <InputError message={form.errors.name} />
            </div>
            <div>
                <Label htmlFor={`owner-percentage-${owner.id}`}>
                    {t('Default owner share (%)')}
                </Label>
                <Input
                    id={`owner-percentage-${owner.id}`}
                    className="mt-2"
                    inputMode="decimal"
                    value={form.data.default_percentage}
                    onChange={(event) =>
                        form.setData('default_percentage', event.target.value)
                    }
                />
                <InputError message={form.errors.default_percentage} />
            </div>
            <div className="self-end pb-2 text-sm text-muted-foreground">
                {t('Responsible admin')}: {adminName}
            </div>
            <Button
                className="self-end"
                disabled={form.processing || !form.isDirty}
            >
                {t('Save owner profile')}
            </Button>
        </form>
    );
}

export function PlotOwnerSetup({
    owners,
    admins,
}: {
    owners: PlotOwner[];
    admins: { id: number; name: string }[];
}) {
    const t = useTranslate();
    const form = useForm({
        name: '',
        admin_user_id: '',
        default_percentage: '',
    });
    useUnsavedChanges(form.isDirty);
    return (
        <section className="ef-panel p-6">
            <div className="flex items-center gap-3">
                <UserRoundCheck className="size-5 text-primary" />
                <h2 className="text-lg font-semibold">
                    {t('Plot owners & administrators')}
                </h2>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
                {t(
                    'Create a profile for each owner and link their admin account. Assign plots in inventory. Existing plots stay unassigned until reviewed. This identifies the owner; it does not calculate settlements.',
                )}
            </p>
            <div className="my-5 divide-y">
                {owners.map((owner) => (
                    <OwnerProfileEditor
                        key={owner.id}
                        owner={owner}
                        adminName={
                            admins.find(
                                (admin) => admin.id === owner.admin_user_id,
                            )?.name ?? `Admin #${owner.admin_user_id}`
                        }
                    />
                ))}
            </div>
            <form
                className="grid gap-4 sm:grid-cols-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/inventory/owners', {
                        preserveScroll: true,
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <div>
                    <Label htmlFor="owner-percentage">
                        {t('Default owner share (%)')}
                    </Label>
                    <Input
                        id="owner-percentage"
                        inputMode="decimal"
                        value={form.data.default_percentage}
                        onChange={(event) =>
                            form.setData(
                                'default_percentage',
                                event.target.value,
                            )
                        }
                        className="mt-2"
                    />
                    <InputError message={form.errors.default_percentage} />
                </div>
                <div>
                    <Label htmlFor="owner-name">{t('Owner name')}</Label>
                    <Input
                        id="owner-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={120}
                        required
                        className="mt-2"
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div>
                    <Label htmlFor="owner-admin">
                        {t('Responsible admin')}
                    </Label>
                    <select
                        id="owner-admin"
                        className="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
                        required
                        value={form.data.admin_user_id}
                        onChange={(event) =>
                            form.setData('admin_user_id', event.target.value)
                        }
                    >
                        <option value="">{t('Choose an admin')}</option>
                        {admins.map((admin) => (
                            <option key={admin.id} value={admin.id}>
                                {admin.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.admin_user_id} />
                </div>
                <div className="flex items-end">
                    <Button disabled={form.processing}>
                        {t('Add plot owner')}
                    </Button>
                </div>
            </form>
        </section>
    );
}
