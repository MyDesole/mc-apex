<script>
/**
 * Текст с кликабельными ссылками.
 *
 * Рисует сегменты через render-функцию, а не v-html: пользовательский
 * текст никогда не превращается в разметку. Ссылки на сам сайт открываются
 * внутри приложения, чужие — в новой вкладке без передачи referrer.
 */
import { defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { internalPath, linkify } from '@/utils/linkify.js'

const SITE_HOSTS = ['mc-apex.ru', 'www.mc-apex.ru']

export default defineComponent({
    name: 'LinkifiedText',

    props: {
        text: { type: String, default: '' },
    },

    setup(props) {
        const router = useRouter()

        const hosts = () => {
            const own = typeof window !== 'undefined' ? window.location.hostname : ''

            return own ? [...SITE_HOSTS, own] : SITE_HOSTS
        }

        return () =>
            linkify(props.text).map((part) => {
                if (part.type === 'text') return part.text

                const path = internalPath(part.href, hosts())

                if (path && router) {
                    return h(
                        'a',
                        {
                            class: 'linkified linkified--inner',
                            href: part.href,
                            title: part.href,
                            onClick: (event) => {
                                // Ctrl/Cmd/колёсико — как у обычной ссылки
                                if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return

                                event.preventDefault()
                                router.push(path)
                            },
                        },
                        part.text,
                    )
                }

                return h(
                    'a',
                    {
                        class: 'linkified linkified--outer',
                        href: part.href,
                        title: part.href,
                        target: '_blank',
                        rel: 'noopener noreferrer nofollow ugc',
                    },
                    part.text,
                )
            })
    },
})
</script>

<style>
/* Не scoped: ссылки лежат внутри чужих блоков (тема, ответ, клан) */
a.linkified {
    color: var(--accent-light, #c4b5fd);
    text-decoration: underline;
    text-decoration-color: color-mix(in srgb, var(--accent-light, #c4b5fd) 55%, transparent);
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;

    padding: 0 2px;
    margin: 0 -2px;

    background: color-mix(in srgb, var(--accent-light, #c4b5fd) 10%, transparent);
    border-radius: 3px;

    overflow-wrap: anywhere;
    word-break: break-word;

    transition: background 0.15s ease, color 0.15s ease;
}

a.linkified:hover,
a.linkified:focus-visible {
    color: #fff;
    background: color-mix(in srgb, var(--accent-light, #c4b5fd) 30%, transparent);
    text-decoration-color: currentColor;
}

/* Чужой сайт: стрелка-подсказка, что ссылка откроется в новой вкладке */
a.linkified--outer::after {
    content: '\2197';
    margin-left: 3px;
    font-size: 0.85em;
    opacity: 0.7;
}
</style>
