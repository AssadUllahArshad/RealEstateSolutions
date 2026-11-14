import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/lib/i18n';

export default function ExportMenu({
    href,
    label,
}: {
    href: string;
    label?: string;
}) {
    const t = useTranslate();
    const separator = href.includes('?') ? '&' : '?';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline">
                    <Download className="size-4" />
                    {label ?? t('Export')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                    <a href={`${href}${separator}format=csv`}>
                        {t('Export CSV')}
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={`${href}${separator}format=xlsx`}>
                        {t('Export Excel')}
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
