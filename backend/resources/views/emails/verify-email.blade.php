<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0a0a0f; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #0a0a0f; padding: 40px 20px;">
    <tr>
        <td align="center">

            <!-- Card -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 560px; background-color: #12121a; border: 1px solid #22222e; border-radius: 16px; overflow: hidden;">

                <!-- Header с градиентом -->
                <tr>
                    <td style="padding: 40px 40px 20px; text-align: center; background: linear-gradient(135deg, #7c3aed 0%, #06b6d4 100%);">
                        <div style="font-size: 32px; font-weight: 900; color: #ffffff; letter-spacing: -1px;">
                            APEX TIERS
                        </div>
                        <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                            Season 1 · Live
                        </div>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding: 32px 40px 20px;">
                        <h1 style="margin: 0 0 16px; font-size: 24px; font-weight: 800; color: #e2e2e8; letter-spacing: -0.5px;">
                            Привет, {{ $user->username }}! 👋
                        </h1>

                        <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.6; color: #8888a0;">
                            Спасибо за регистрацию на <b style="color: #a78bfa;">APEX TIERS</b>.
                            Осталось сделать один шаг — подтвердить свой email, чтобы активировать аккаунт.
                        </p>

                        <p style="margin: 0 0 28px; font-size: 14px; line-height: 1.6; color: #8888a0;">
                            Нажми на кнопку ниже:
                        </p>

                        <!-- CTA -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 28px;">
                            <tr>
                                <td align="center" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 12px; box-shadow: 0 8px 24px rgba(124, 58, 237, 0.35);">
                                    <a href="{{ $url }}"
                                       style="display: inline-block; padding: 16px 40px; font-size: 15px; font-weight: 800; color: #ffffff; text-decoration: none; letter-spacing: 0.3px;">
                                        ✅ Подтвердить email
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin: 0 0 16px; font-size: 13px; line-height: 1.6; color: #5e5e70;">
                            Ссылка действительна <b style="color: #8888a0;">60 минут</b>.
                            Если ты не регистрировался на APEX TIERS — просто проигнорируй это письмо.
                        </p>
                    </td>
                </tr>

                <!-- Divider -->
                <tr>
                    <td style="padding: 0 40px;">
                        <div style="height: 1px; background-color: #22222e;"></div>
                    </td>
                </tr>

                <!-- Альтернативная ссылка -->
                <tr>
                    <td style="padding: 20px 40px 32px;">
                        <p style="margin: 0 0 8px; font-size: 12px; color: #5e5e70;">
                            Кнопка не работает? Скопируй ссылку в браузер:
                        </p>
                        <p style="margin: 0; font-size: 12px; color: #7c3aed; word-break: break-all; font-family: monospace;">
                            {{ $url }}
                        </p>
                    </td>
                </tr>

            </table>

            <!-- Footer -->
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 560px; margin-top: 24px;">
                <tr>
                    <td align="center" style="padding: 0 20px;">
                        <p style="margin: 0 0 6px; font-size: 12px; color: #5e5e70;">
                            © 2026 APEX TIERS. Все права защищены.
                        </p>
                        <p style="margin: 0; font-size: 11px; color: #3a3a48;">
                            Это автоматическое письмо, не отвечай на него.
                        </p>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>
