import { Head } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access restricted',
        description:
            'Your account does not have access to this page. Contact your administrator if you need access.',
    },
    404: {
        title: 'Page not found',
        description:
            'This page may have moved, or the address may be incorrect.',
    },
    419: {
        title: 'Your session has expired',
        description:
            'Sign in again before continuing. Review your latest records before resubmitting an action.',
    },
    429: {
        title: 'Please wait a moment',
        description:
            'Too many requests were received. Wait a little before trying again.',
    },
    500: {
        title: 'Something went wrong',
        description:
            'We could not complete this request. Contact your administrator with the reference below if the problem continues.',
    },
    503: {
        title: 'Temporarily unavailable',
        description:
            'The workspace is temporarily unavailable. Please try again shortly.',
    },
};

export default function ErrorPage({
    status,
    requestId,
}: {
    status: number;
    requestId?: string | null;
}) {
    const message = messages[status] ?? messages[500];
    return (
        <>
            <Head title={message.title} />
            <main className="flex min-h-svh items-center justify-center bg-slate-50 p-6 dark:bg-background">
                <div className="w-full max-w-lg rounded-2xl border bg-card p-7 shadow-sm md:p-10">
                    <div className="mb-8 flex items-center gap-2 text-sm font-semibold">
                        <ShieldCheck className="size-5 text-blue-600" />
                        EstateFlow
                    </div>
                    <p className="text-sm font-medium text-muted-foreground">
                        {status}
                    </p>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight">
                        {message.title}
                    </h1>
                    <p className="mt-4 text-sm leading-7 text-muted-foreground">
                        {message.description}
                    </p>
                    <a
                        href={status === 419 ? '/login' : '/dashboard'}
                        className="mt-7 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-medium text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary"
                    >
                        <ArrowLeft className="size-4" />
                        {status === 419
                            ? 'Sign in again'
                            : 'Return to workspace'}
                    </a>
                    {requestId && (
                        <p className="mt-8 text-xs leading-6 break-all text-muted-foreground">
                            Request reference: {requestId}
                        </p>
                    )}
                </div>
            </main>
        </>
    );
}
