import { usePage } from '@inertiajs/react';
import { Languages, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export function WorkspacePreferences() {
    const { locale, csrfToken } = usePage().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    return (
        <div className="flex shrink-0 items-center gap-2">
            <form action="/locale" method="post">
                <input type="hidden" name="_token" value={String(csrfToken)} />
                <input
                    type="hidden"
                    name="locale"
                    value={locale === 'ur' ? 'en' : 'ur'}
                />
                <Button
                    variant="outline"
                    size="sm"
                    aria-label={
                        locale === 'ur'
                            ? 'Switch to English'
                            : 'اردو زبان منتخب کریں'
                    }
                >
                    <Languages className="size-4" />
                    <span lang={locale === 'ur' ? 'en' : 'ur'}>
                        {locale === 'ur' ? 'English' : 'اردو'}
                    </span>
                </Button>
            </form>
            <Button
                variant="outline"
                size="icon"
                aria-label={
                    resolvedAppearance === 'dark' ? 'Light theme' : 'Dark theme'
                }
                onClick={() =>
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                }
            >
                {resolvedAppearance === 'dark' ? (
                    <Sun className="size-4" />
                ) : (
                    <Moon className="size-4" />
                )}
            </Button>
        </div>
    );
}
