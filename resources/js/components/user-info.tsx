import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-9 w-9 overflow-hidden rounded-xl border border-white/15 shadow-sm group-data-[collapsible=icon]:size-8">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-xl bg-sidebar-primary font-bold text-sidebar-primary-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-start text-sm leading-tight">
                <span className="truncate font-semibold">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
