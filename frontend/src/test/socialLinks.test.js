import { describe, expect, it } from 'vitest'
import { socialDisplay, socialHref } from '@/utils/socialLinks.js'

describe('ссылки на соцсети из ника', () => {
    it('@ник и голый ник становятся ссылкой на профиль сети', () => {
        expect(socialHref('telegram', '@mydesole')).toBe('https://t.me/mydesole')
        expect(socialHref('telegram', 'mydesole')).toBe('https://t.me/mydesole')
        expect(socialHref('youtube', '@apex')).toBe('https://www.youtube.com/@apex')
        expect(socialHref('twitch', 'streamer_1')).toBe('https://www.twitch.tv/streamer_1')
        expect(socialHref('vk', '@id123')).toBe('https://vk.com/id123')
        expect(socialHref('steam', '76561198000000000')).toBe('https://steamcommunity.com/profiles/76561198000000000')
        expect(socialHref('steam', 'gaben')).toBe('https://steamcommunity.com/id/gaben')
        // Ключ из настроек сайта
        expect(socialHref('social_telegram', '@apex_tiers')).toBe('https://t.me/apex_tiers')
    })

    it('ссылки без https дополняются, полные — как есть', () => {
        expect(socialHref('telegram', 't.me/mydesole')).toBe('https://t.me/mydesole')
        expect(socialHref('discord', 'discord.gg/abc')).toBe('https://discord.gg/abc')
        expect(socialHref('website', 'mc-apex.ru')).toBe('https://mc-apex.ru')
        expect(socialHref('youtube', 'https://youtu.be/x')).toBe('https://youtu.be/x')
    })

    it('ник в Discord и мусор — не ссылка', () => {
        expect(socialHref('discord', 'mydesole')).toBeNull()
        expect(socialHref('website', 'просто текст')).toBeNull()
        expect(socialHref('telegram', '')).toBeNull()
    })

    it('на карточке — @ник', () => {
        expect(socialDisplay('telegram', 'mydesole')).toBe('@mydesole')
        expect(socialDisplay('telegram', 'https://t.me/mydesole')).toBe('@mydesole')
        expect(socialDisplay('discord', 'mydesole')).toBe('mydesole')
        expect(socialDisplay('website', 'mc-apex.ru/rules')).toBe('mc-apex.ru/rules')
    })
})
