export function formatPkr(value: string | null): string {
    if (value === null) return 'Not set';
    const [whole, fraction = '00'] = value.split('.');
    return `PKR ${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0')}`;
}
