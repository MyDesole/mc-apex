export const MODE_LABELS = {
    bedwars: 'BedWars', skywars: 'SkyWars', duels: 'Duels',
    pvp: 'PvP', survival: 'Survival', other: 'Other',
}

export const MODE_COLORS = {
    bedwars: '#8b5cf6', skywars: '#06b6d4', duels: '#f97316',
    pvp: '#ef4444', survival: '#22c55e', other: '#6b7280',
}

export function pluralDays(n) {
    const mod10 = n % 10
    const mod100 = n % 100
    if (mod10 === 1 && mod100 !== 11) return 'день'
    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'дня'
    return 'дней'
}

export function formatDate(date) {
    return new Date(date).toLocaleDateString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    })
}