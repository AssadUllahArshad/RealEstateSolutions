import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useUnsavedChanges(dirty: boolean) {
    useEffect(() => {
        if (!dirty) return;
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', beforeUnload);
        const remove = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                !window.confirm('Discard your unsaved changes?')
            )
                event.preventDefault();
        });
        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            remove();
        };
    }, [dirty]);
}
