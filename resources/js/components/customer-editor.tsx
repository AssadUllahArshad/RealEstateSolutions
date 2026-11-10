import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { useTranslate } from '@/lib/i18n';

export type CustomerRecord = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    assigned_to: number;
    agent: { id: number; name: string };
    created_at: string;
    cnic?: string | null;
    address?: string | null;
};
export type AgentOption = { id: number; name: string };
export default function CustomerEditor({
    customer,
    agents,
    creationKey,
    close,
    onDirtyChange,
}: {
    customer?: CustomerRecord;
    agents: AgentOption[];
    creationKey?: string;
    close: () => void;
    onDirtyChange: (dirty: boolean) => void;
}) {
    const t = useTranslate();
    const { auth } = usePage().props;
    const assign = auth.abilities.includes('customers.assign');
    const form = useForm({
        name: customer?.name ?? '',
        phone: customer?.phone ?? '',
        email: customer?.email ?? '',
        cnic: customer?.cnic ?? '',
        address: customer?.address ?? '',
        ...(assign
            ? { assigned_to: String(customer?.assigned_to ?? auth.user.id) }
            : {}),
        ...(!customer ? { creation_key: creationKey } : {}),
    });
    useUnsavedChanges(form.isDirty);
    useEffect(() => onDirtyChange(form.isDirty), [form.isDirty, onDirtyChange]);
    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                const options = { onSuccess: close };
                if (customer) form.patch(`/customers/${customer.id}`, options);
                else form.post('/customers', options);
            }}
        >
            <section className="ef-form-section space-y-5">
                <div>
                    <Label htmlFor="customer-name">{t('Full name *')}</Label>
                    <Input
                        id="customer-name"
                        className="mt-2"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                        maxLength={160}
                        autoFocus
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="customer-phone">
                            {t('Phone number')}
                        </Label>
                        <Input
                            id="customer-phone"
                            type="tel"
                            className="mt-2"
                            placeholder="03XX XXXXXXX"
                            maxLength={40}
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                        />
                        <InputError message={form.errors.phone} />
                    </div>
                    <div>
                        <Label htmlFor="customer-email">
                            {t('Email address')}
                        </Label>
                        <Input
                            id="customer-email"
                            type="email"
                            className="mt-2"
                            maxLength={160}
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>
                </div>
            </section>
            <section className="ef-form-section space-y-5">
                <div>
                    <Label htmlFor="customer-cnic">{t('CNIC')}</Label>
                    <Input
                        id="customer-cnic"
                        className="mt-2"
                        inputMode="numeric"
                        autoComplete="off"
                        maxLength={15}
                        value={form.data.cnic}
                        onChange={(e) => form.setData('cnic', e.target.value)}
                        placeholder="35202-1234567-1"
                    />
                    <InputError message={form.errors.cnic} />
                    <p className="mt-2 text-xs text-muted-foreground">
                        {t(
                            "13 digits, with or without hyphens. Identity details stay within the customer's assigned workspace.",
                        )}
                    </p>
                </div>
                <div>
                    <Label htmlFor="customer-address">{t('Address')}</Label>
                    <textarea
                        id="customer-address"
                        className="mt-2 min-h-20 w-full rounded-lg border bg-background p-3 text-sm"
                        maxLength={1000}
                        value={form.data.address}
                        onChange={(e) =>
                            form.setData('address', e.target.value)
                        }
                    />
                    <InputError message={form.errors.address} />
                </div>
            </section>
            {assign && (
                <div className="ef-form-section">
                    <Label htmlFor="customer-agent">
                        {t('Assigned staff member')}
                    </Label>
                    <select
                        id="customer-agent"
                        className="mt-2 w-full"
                        value={form.data.assigned_to}
                        onChange={(e) =>
                            form.setData('assigned_to', e.target.value)
                        }
                    >
                        {agents.map((agent) => (
                            <option key={agent.id} value={agent.id}>
                                {agent.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.assigned_to} />
                </div>
            )}
            <InputError
                message={(form.errors as Record<string, string>).conflict}
            />
            <p className="rounded-lg bg-muted/50 p-3 text-xs leading-5 text-muted-foreground">
                {t(
                    'Contact details are visible to assigned staff and authorized admins. Add follow-up notes after saving this record.',
                )}
            </p>
            <div className="ef-action-bar justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                        if (
                            !form.isDirty ||
                            window.confirm('Discard your unsaved changes?')
                        )
                            close();
                    }}
                >
                    {t('Cancel')}
                </Button>
                <Button disabled={form.processing}>
                    {form.processing
                        ? t('Saving…')
                        : customer
                          ? t('Save changes')
                          : t('Create customer')}
                </Button>
            </div>
        </form>
    );
}
