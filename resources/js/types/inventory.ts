export type SocietyProfile = {
    name: string | null;
    office_name: string | null;
    phone?: string | null;
    email?: string | null;
    address?: string | null;
};
export type Block = { id: number; code: string; name: string };
export type PlotSize = { id: number; label: string; area_marla: string };
export type PlotOwner = {
    id: number;
    name: string;
    admin_user_id: number;
    default_percentage: string | null;
};
export type Plot = {
    plot_owner_id: number | null;
    owner_percentage: string | null;
    owner: { id: number; name: string } | null;
    active_booking?: { id: number; plot_id: number } | null;
    id: number;
    number: string;
    block_id: number;
    plot_size_id: number;
    list_price: string | null;
    block: Block;
    size: PlotSize;
    active_hold: {
        id: number;
        kind: 'reservation' | 'block';
        state: 'active';
    } | null;
};
export type InventoryFilters = {
    owner_id?: string;
    q?: string;
    block_id?: string;
    size_id?: string;
    hold?: string;
    sort?: string;
    view?: string;
};
