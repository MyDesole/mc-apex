export const TIER_COLORS = {
    'S+': '#fbbf24',
    S: '#facc15',
    A: '#f97316',
    B: '#8b5cf6',
    C: '#06b6d4',
    D: '#22c55e',
    E: '#6b7280',
}

export function tierColor(tier) {
    return TIER_COLORS[tier] || '#6b7280'
}

export function aspectColor(value) {
    if (value >= 16) return 'linear-gradient(90deg, #22c55e, #4ade80)'
    if (value >= 12) return 'linear-gradient(90deg, #7c3aed, #a78bfa)'
    if (value >= 8) return 'linear-gradient(90deg, #f59e0b, #fbbf24)'
    if (value >= 1) return 'linear-gradient(90deg, #ef4444, #f87171)'
    return 'rgba(255, 255, 255, 0.06)'
}

export function percentColor(percent) {
    if (percent >= 90) return '#facc15'
    if (percent >= 80) return '#f97316'
    if (percent >= 70) return '#8b5cf6'
    if (percent >= 60) return '#06b6d4'
    if (percent >= 50) return '#22c55e'
    return '#6b7280'
}